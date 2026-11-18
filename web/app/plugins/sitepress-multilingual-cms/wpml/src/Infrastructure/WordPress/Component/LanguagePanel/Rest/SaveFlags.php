<?php

namespace WPML\Infrastructure\WordPress\Component\LanguagePanel\Rest;

/**
 * The ONE accessor for the flags a save carries (wpmldev-8629, S5): the
 * "Minor edit" tick and the per-post media-duplication choice.
 *
 * Two transports, one decision:
 * - the block editor's Language panel sends them as REST args on the post's
 *   own create/update (`wpml_minor_edit`, `wpml_duplicate_media`,
 *   `wpml_duplicate_featured`, `wpml_has_media_options`; registered by
 *   {@see SaveFlagsArgs}, sent by the panel's apiFetch middleware, J3). That
 *   request is captured on `rest_dispatch_request` - after the route's
 *   permission check passed, right before the controller callback runs - when
 *   the handler is a posts controller's create_item/update_item. A request the
 *   permission callback refuses never reaches that filter, so it is never
 *   captured. The autosaves and revisions controllers are no posts controller
 *   and never match, so background writes carry no flag;
 * - the classic Language box posts them as form fields (`icl_minor_edit`,
 *   `wpml_duplicate_media`, `wpml_duplicate_featured`, the hidden
 *   `wpml_has_media_options` marker). Each reader keeps reading those from
 *   `$_POST` exactly as it did (CL-1, CL-11), on its own unchanged line: the
 *   minor-edit readers branch on their `$_POST` read first and ask this class
 *   for the REST transport inside that branch, the media writer hands its
 *   classic reading in as the fallback. The form read stays next to the
 *   save_post hook WordPress reached after verifying the post's own nonce,
 *   which is where the security sniffs want it - not in a shared helper.
 *
 * While a captured save runs, its request answers alone for the flags it is
 * asked about: a classic `$_POST` value is not consulted for the media choice,
 * and the reader's own `$_POST['icl_minor_edit']` branch comes first only
 * because the readers read it first (a form-encoded REST body carrying
 * `icl_minor_edit=1` was a minor edit on develop already, and still is).
 * Absent means false. The capture is scoped to the dispatch: every reader runs
 * inside the controller callback (`save_post`, `wp_after_insert_post`,
 * `wpml_tm_save_post`, `wpml_after_save_post` all fire from `wp_insert_post`),
 * and the TEA closure that runs on `shutdown` reads nothing here - it is only
 * queued when the save was not minor. So `rest_request_after_callbacks`
 * releases the save at whatever depth it sits - together with anything still
 * stacked above it, because core applies that filter only when the callback
 * returned: a nested save whose callback threw and was caught by its caller
 * never had its after-callbacks, and must not keep the outer save captured
 * (M2 re-review P3-5). Nothing outlives its dispatch: a later
 * `wp_update_post()` in the same PHP process (WP-CLI, cron, an admin request
 * calling `rest_do_request()`, a `/batch/v1` body, a loop of saves) answers
 * from its own request or from `$_POST` again. A save dispatched from inside
 * another one (`rest_do_request()`) is scoped the same way - its own flags
 * answer while it runs, the outer save's are back when it is done
 * (wpmldev-9427 taught `wpml_language` the same lesson).
 *
 * The meta-box loader leg (P1-1 of the M2 review): the block editor follows
 * its REST save with a classic `post.php?…&meta-box-loader=1` editpost request
 * whenever a meta box without `__back_compat_meta_box` is registered - WPML's
 * own box until M5+M6, any third-party box after it. That leg carries no
 * `icl_minor_edit` and would mark the translations the REST save just spared,
 * because the classic minor edit refreshes NO md5 baseline (the TM reader
 * skips its whole marking block, {@see \WPML_TM_Post_Actions::save_post_actions()}),
 * so the leg's new md5 differs from the stored one. The REST minor edit must
 * mean exactly what the classic one means - no marking, no baseline refresh -
 * so the REST save leaves a short-lived per-post marker ({@see self::LEG_MARKER_TTL})
 * that the leg of the same post consumes; a non-minor REST save clears it.
 * The marker is touched ONLY when the released save carried the
 * `wpml_minor_edit` arg at all (true: leave one, false: clear, absent:
 * nothing) - the panel's middleware always sends it, one way or the other, on
 * the editor's save (J3), while a REST client, a mobile app or the Site
 * Editor's `wp_navigation` save sends nothing and pays no option query (M2
 * re-review P3-2). The marker is a small COUNTER per post, not a boolean: two
 * minor saves of the same post that land before either leg (two tabs, two
 * users) leave two, and each leg consumes one, so neither leg marks (P3-1).
 * The leg predicate itself lives with the sitepress host, next to the readers
 * ({@see \WPML\Posts\MetaBoxLoaderLeg}): this class only knows the marker.
 *
 * The same leg carries the fields of any third-party meta box (ACF, Yoast),
 * and Translate Everything must send AFTER those are written (wpmldev-11379).
 * So the panel's middleware also sends `wpml_meta_box_save_follows` on the
 * editor's own save whenever the editor will send that leg (its edit-post
 * store says the screen has meta boxes, and the save is no autosave). This
 * class only reads the flag ({@see self::metaBoxSaveFollows()}); what the
 * REST save does with it - leave a marker for the leg instead of sending -
 * is the Translate Everything dispatch's business
 * ({@see \WPML\TM\AutomaticTranslation\Actions\PendingMetaBoxSave}).
 *
 * Static on purpose: the readers are legacy sitepress classes built outside
 * the DIC (WPML_TM_Post_Actions, WPML_Admin_Post_Actions, the TEA Actions,
 * WPML_Blog_License_Translation_Status), and a request has one set of flags.
 */
class SaveFlags {

  const ARG_MINOR_EDIT         = 'wpml_minor_edit';
  const ARG_DUPLICATE_MEDIA    = 'wpml_duplicate_media';
  const ARG_DUPLICATE_FEATURED = 'wpml_duplicate_featured';
  const ARG_HAS_MEDIA_OPTIONS  = 'wpml_has_media_options';

  const ARG_META_BOX_SAVE_FOLLOWS = 'wpml_meta_box_save_follows';

  const LEG_MARKER_TTL = 180;

  const LEG_MARKER_PREFIX = 'wpml_rest_minor_edit_leg_';

  const NOT_A_POST_CONTROLLER = [
    'WP_REST_Attachments_Controller',
    'WP_REST_Global_Styles_Controller',
    'WP_REST_Font_Families_Controller',
    'WP_REST_Font_Faces_Controller',
    'WP_REST_Menu_Items_Controller',
  ];

  private static $requests = [];

  private static $legAnswers = [];

  private static $legAnswersRequest = null;


  public static function isPostSave( ?array $handler ): bool {
    if ( empty( $handler['callback'] ) || ! is_array( $handler['callback'] ) ) {
      return false;
    }

    if ( ! isset( $handler['callback'][0], $handler['callback'][1] ) ) {
      return false;
    }

    $controller = $handler['callback'][0];
    if ( ! $controller instanceof \WP_REST_Posts_Controller ) {
      return false;
    }

    $parents = class_parents( $controller );
    $lineage = array_merge( [ get_class( $controller ) ], $parents ? array_values( $parents ) : [] );
    if ( array_intersect( $lineage, self::NOT_A_POST_CONTROLLER ) ) {
      return false;
    }

    return in_array( $handler['callback'][1], [ 'create_item', 'update_item' ], true );
  }


  public static function onDispatch( $dispatchResult, $request, $route, ?array $handler ) {
    if ( $request instanceof \WP_REST_Request && self::isPostSave( $handler ) ) {
      self::$requests[] = $request;
    }

    return $dispatchResult;
  }


  public static function onAfterCallbacks( $response, ?array $handler, $request ) {
    $depth = self::depthOf( $request );
    if ( null !== $depth ) {
      $released = array_splice( self::$requests, $depth );
      self::rememberForLeg( $released[0], $response );
    }

    return $response;
  }


  public static function isRestSave(): bool {
    return [] !== self::$requests;
  }


  public static function isMinorEdit( bool $classic = false ): bool {
    $request = self::request();
    if ( $request ) {
      return self::truthy( $request->get_param( self::ARG_MINOR_EDIT ) );
    }

    return $classic;
  }


  public static function metaBoxSaveFollows(): bool {
    $request = self::request();

    return $request ? self::truthy( $request->get_param( self::ARG_META_BOX_SAVE_FOLLOWS ) ) : false;
  }


  public static function mediaOptions( ?array $classic = null ): ?array {
    $request = self::request();
    if ( ! $request ) {
      return $classic;
    }

    if ( ! self::truthy( $request->get_param( self::ARG_HAS_MEDIA_OPTIONS ) ) ) {
      return null;
    }

    return [
      'duplicate_media'    => self::truthy( $request->get_param( self::ARG_DUPLICATE_MEDIA ) ),
      'duplicate_featured' => self::truthy( $request->get_param( self::ARG_DUPLICATE_FEATURED ) ),
    ];
  }


  public static function consumeMinorEditForLeg( int $postId ): bool {
    self::forgetAnotherRequestsAnswers();

    if ( array_key_exists( $postId, self::$legAnswers ) ) {
      return self::$legAnswers[ $postId ];
    }

    $key    = self::legMarkerKey( $postId );
    $stored = get_transient( $key );
    $answer = false;

    if ( false !== $stored && null !== $stored ) {
      $marker = self::liveMarker( $stored );
      if ( $marker ) {
        $answer = true;
        if ( $marker['count'] > 1 ) {
          set_transient( $key, [ 'count' => $marker['count'] - 1, 'at' => $marker['at'] ], self::LEG_MARKER_TTL );
        } else {
          delete_transient( $key );
        }
      } else {
        delete_transient( $key );
      }
    }

    self::$legAnswers[ $postId ] = $answer;

    return $answer;
  }


  public static function reset(): void {
    self::$requests          = [];
    self::$legAnswers        = [];
    self::$legAnswersRequest = null;
  }


  private static function request(): ?\WP_REST_Request {
    return self::$requests ? end( self::$requests ) : null;
  }


  private static function depthOf( $request ): ?int {
    if ( ! $request instanceof \WP_REST_Request ) {
      return null;
    }

    foreach ( array_reverse( self::$requests, true ) as $depth => $captured ) {
      if ( $captured === $request ) {
        return $depth;
      }
    }

    return null;
  }


  private static function forgetAnotherRequestsAnswers(): void {
    $now = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? floatval( $_SERVER['REQUEST_TIME_FLOAT'] ) : 0.0;
    if ( $now !== self::$legAnswersRequest ) {
      self::$legAnswers        = [];
      self::$legAnswersRequest = $now;
    }
  }


  private static function rememberForLeg( \WP_REST_Request $request, $response ): void {
    if ( $response instanceof \WP_Error ) {
      return;
    }

    if ( ! $request->has_param( self::ARG_MINOR_EDIT ) ) {
      return;
    }

    $id     = $request->get_param( 'id' );
    $postId = is_numeric( $id ) ? (int) $id : 0;
    if ( $postId <= 0 && is_object( $response ) && method_exists( $response, 'get_data' ) ) {
      $data   = $response->get_data();
      $postId = is_array( $data ) && isset( $data['id'] ) ? (int) $data['id'] : 0;
    }
    if ( $postId <= 0 ) {
      return;
    }

    $key = self::legMarkerKey( $postId );
    if ( self::truthy( $request->get_param( self::ARG_MINOR_EDIT ) ) ) {
      $marker = self::liveMarker( get_transient( $key ) );
      $count  = $marker ? $marker['count'] + 1 : 1;
      set_transient( $key, [ 'count' => $count, 'at' => time() ], self::LEG_MARKER_TTL );
    } else {
      delete_transient( $key );
    }
  }


  private static function liveMarker( $stored ): ?array {
    if ( is_array( $stored ) && isset( $stored['count'], $stored['at'] ) && is_numeric( $stored['count'] ) && is_numeric( $stored['at'] ) ) {
      $marker = [ 'count' => (int) $stored['count'], 'at' => (int) $stored['at'] ];
    } elseif ( is_numeric( $stored ) ) {
      $marker = [ 'count' => 1, 'at' => (int) $stored ];
    } else {
      return null;
    }

    if ( $marker['count'] < 1 || ( time() - $marker['at'] ) > self::LEG_MARKER_TTL ) {
      return null;
    }

    return $marker;
  }


  private static function legMarkerKey( int $postId ): string {
    return self::LEG_MARKER_PREFIX . $postId;
  }


  private static function truthy( $value ): bool {
    return is_bool( $value ) ? $value : (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
  }


}
