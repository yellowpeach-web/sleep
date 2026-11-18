<?php

namespace WPML\PostTranslation;

class BulkTranslateIndependently implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const ACTION_INDEPENDENT = 'wpml_translate_independently';
	const ACTION_OVERWRITE   = 'wpml_overwrite_with_original';

	const ARG_ACTION  = 'wpml_bulk_duplication';
	const ARG_CHANGED = 'wpml_bulk_changed';
	const ARG_SKIPPED = 'wpml_bulk_skipped';
	const ARG_NO_CAP  = 'wpml_bulk_no_capability';
	const ARG_PAUSED  = 'wpml_bulk_paused';

	const CHANGED = 'changed';
	const SKIPPED = 'skipped';
	const NO_CAP  = 'no_capability';
	const PAUSED  = 'paused';

	private $sitepress;

	private $tm;

	public function __construct( \SitePress $sitepress, \TranslationManagement $tm ) {
		$this->sitepress = $sitepress;
		$this->tm        = $tm;
	}

	public function add_hooks() {
		add_action( 'load-edit.php', [ $this, 'onListLoad' ] );
		add_filter( 'removable_query_args', [ $this, 'removableQueryArgs' ] );
	}

	public function onListLoad() {
		global $typenow;

		$postType = is_string( $typenow ) && '' !== $typenow ? $typenow : 'post';

		if ( ! $this->sitepress->is_translated_post_type( $postType ) ) {
			return;
		}

		add_filter( 'bulk_actions-edit-' . $postType, [ $this, 'addActions' ] );
		add_filter( 'handle_bulk_actions-edit-' . $postType, [ $this, 'handle' ], 10, 3 );
		add_action( 'admin_notices', [ $this, 'renderNotice' ] );
		add_action( 'admin_footer', [ $this, 'printOverwriteConfirm' ] );
	}

	public function addActions( $actions ) {
		if ( ! is_array( $actions ) || ! self::mayTranslate() ) {
			return $actions;
		}

		/* translators: Bulk action on a list of posts, and a button on the editing screen of a post WPML keeps as a copy: stop keeping the duplicate as a copy of its original and translate it on its own. Verb, imperative. */
		$actions[ self::ACTION_INDEPENDENT ] = __( 'Translate independently', 'sitepress' );
		/* translators: Bulk action on a list of posts: replace the text of each selected translation with the text of its original. %s: the name of the site's default language. */
		$actions[ self::ACTION_OVERWRITE ] = sprintf( __( 'Overwrite with %s content', 'sitepress' ), $this->getDefaultLanguageName() );

		return $actions;
	}

	public function handle( $redirect, $action, $postIds ) {
		if ( ! in_array( $action, [ self::ACTION_INDEPENDENT, self::ACTION_OVERWRITE ], true ) ) {
			return $redirect;
		}

		$counts       = [
			self::CHANGED => 0,
			self::SKIPPED => 0,
			self::NO_CAP  => 0,
			self::PAUSED  => 0,
		];
		$mayTranslate = self::mayTranslate();

		foreach ( array_map( 'intval', (array) $postIds ) as $postId ) {
			$result = self::ACTION_INDEPENDENT === $action
				? $this->makeIndependent( $postId, $mayTranslate )
				: $this->overwriteWithOriginal( $postId, $mayTranslate );

			$counts[ $result ]++;
		}

		return add_query_arg(
			[
				self::ARG_ACTION  => self::ACTION_INDEPENDENT === $action ? 'independent' : 'overwrite',
				self::ARG_CHANGED => $counts[ self::CHANGED ],
				self::ARG_SKIPPED => $counts[ self::SKIPPED ],
				self::ARG_NO_CAP  => $counts[ self::NO_CAP ],
				self::ARG_PAUSED  => $counts[ self::PAUSED ],
			],
			remove_query_arg( self::queryArgs(), (string) $redirect )
		);
	}

	private function makeIndependent( $postId, $mayTranslate ) {
		if ( $postId <= 0 || ! self::isDuplicate( $postId ) ) {
			return self::SKIPPED;
		}

		if ( ! $mayTranslate || ! current_user_can( 'edit_post', $postId ) ) {
			return self::NO_CAP;
		}

		$this->tm->reset_duplicate_flag( $postId );

		return self::CHANGED;
	}

	private function overwriteWithOriginal( $postId, $mayTranslate ) {
		$post = $postId > 0 ? get_post( $postId ) : null;
		if ( ! $post instanceof \WP_Post ) {
			return self::SKIPPED;
		}

		$elementType = 'post_' . $post->post_type;
		$originalId  = (int) apply_filters( 'wpml_original_element_id', null, $postId, $elementType );

		if ( ! $originalId || $originalId === $postId || self::isDuplicate( $postId ) ) {
			return self::SKIPPED;
		}

		if ( ! $mayTranslate || ! current_user_can( 'edit_post', $postId ) || ! current_user_can( 'edit_post', $originalId ) ) {
			return self::NO_CAP;
		}

		$language = $this->sitepress->get_language_for_element( $postId, $elementType );
		if ( ! $language ) {
			return self::SKIPPED;
		}

		$duplicateId = $this->tm->set_duplicate( $originalId, $language );

		if ( false === $duplicateId ) {
			return self::PAUSED;
		}

		return $duplicateId ? self::CHANGED : self::SKIPPED;
	}

	public function renderNotice() {
		$action = sanitize_key( (string) filter_input( INPUT_GET, self::ARG_ACTION ) );
		if ( ! in_array( $action, [ 'independent', 'overwrite' ], true ) ) {
			return;
		}

		$count = function ( $arg ) {
			return absint( filter_input( INPUT_GET, $arg, FILTER_SANITIZE_NUMBER_INT ) );
		};

		$sentences = self::sentences(
			$action,
			$count( self::ARG_CHANGED ),
			$count( self::ARG_SKIPPED ),
			$count( self::ARG_NO_CAP ),
			$count( self::ARG_PAUSED )
		);

		if ( ! $sentences ) {
			return;
		}

		$skippedAny = $count( self::ARG_SKIPPED ) + $count( self::ARG_NO_CAP ) + $count( self::ARG_PAUSED ) > 0;

		echo '<div class="notice ' . ( $skippedAny ? 'notice-warning' : 'notice-success' ) . ' is-dismissible">';
		foreach ( $sentences as $sentence ) {
			echo '<p>' . esc_html( $sentence ) . '</p>';
		}
		echo '</div>';
	}

	public static function sentences( $action, $changed, $skipped, $noCapability, $paused ) {
		$sentences   = [];
		$independent = 'independent' === $action;

		if ( $changed ) {
			$sentences[] = $independent
				/* translators: Result of the "Translate independently" bulk action on a list of posts. %d: how many posts it changed. */
				? sprintf( _n( '%d post is now translated independently.', '%d posts are now translated independently.', $changed, 'sitepress' ), $changed )
				/* translators: Result of the "Overwrite with ... content" bulk action on a list of posts. %d: how many translations it replaced with their original's content. */
				: sprintf( _n( '%d translation was overwritten with the content of its original.', '%d translations were overwritten with the content of their originals.', $changed, 'sitepress' ), $changed );
		}

		if ( $skipped ) {
			$sentences[] = $independent
				/* translators: Result of the "Translate independently" bulk action on a list of posts. %d: how many selected posts it left alone because WPML does not keep them as duplicates. */
				? sprintf( _n( '%d post was skipped because it is not a duplicate.', '%d posts were skipped because they are not duplicates.', $skipped, 'sitepress' ), $skipped )
				/* translators: Result of the "Overwrite with ... content" bulk action on a list of posts. %d: how many selected posts it left alone because they are originals or already duplicates. */
				: sprintf( _n( '%d post was skipped because it is not a translation or is already a duplicate.', '%d posts were skipped because they are not translations or are already duplicates.', $skipped, 'sitepress' ), $skipped );
		}

		if ( $noCapability ) {
			/* translators: Result of a bulk action on a list of posts. %d: how many selected posts it left alone because the current user may not edit them. */
			$sentences[] = sprintf( _n( '%d post was skipped because you cannot edit it.', '%d posts were skipped because you cannot edit them.', $noCapability, 'sitepress' ), $noCapability );
		}

		if ( $paused ) {
			/* translators: Result of the "Overwrite with ... content" bulk action on a list of posts. %d: how many translations it left alone because translation into their language is paused. */
			$sentences[] = sprintf( _n( '%d translation was skipped because its language is paused.', '%d translations were skipped because their languages are paused.', $paused, 'sitepress' ), $paused );
		}

		return $sentences;
	}

	public function printOverwriteConfirm() {
		if ( ! self::mayTranslate() ) {
			return;
		}

		/* translators: Question asked before the "Overwrite with ... content" bulk action runs on the selected translations. %s: the name of the site's default language. */
		$warning = sprintf( __( 'The current content of the selected translations will be permanently lost. WPML will copy the %s content and replace the current content.', 'sitepress' ), $this->getDefaultLanguageName() );

		$script = '(function(){var f=document.getElementById("posts-filter");if(!f){return;}'
			. 'f.addEventListener("submit",function(e){'
			. 'var t=f.querySelector("select[name=action]"),b=f.querySelector("select[name=action2]");'
			. 'var v=t&&t.value!=="-1"?t.value:(b?b.value:"");'
			. 'if(v===' . wp_json_encode( self::ACTION_OVERWRITE ) . '&&!window.confirm(' . wp_json_encode( $warning ) . ')){e.preventDefault();}'
			. '});})();';

		wp_print_inline_script_tag( $script );
	}

	public function removableQueryArgs( $args ) {
		return is_array( $args ) ? array_merge( $args, self::queryArgs() ) : $args;
	}

	private static function queryArgs() {
		return [ self::ARG_ACTION, self::ARG_CHANGED, self::ARG_SKIPPED, self::ARG_NO_CAP, self::ARG_PAUSED ];
	}

	private static function mayTranslate() {
		return current_user_can( 'translate' ) || current_user_can( 'manage_translations' );
	}

	private static function isDuplicate( $postId ) {
		return \WPML\TranslationPolicy\Duplicate\DuplicateReaders::isKeptInStep( (int) $postId );
	}

	private function getDefaultLanguageName() {
		$details = $this->sitepress->get_language_details( $this->sitepress->get_default_language() );

		return is_array( $details ) && isset( $details['display_name'] ) ? (string) $details['display_name'] : '';
	}
}
