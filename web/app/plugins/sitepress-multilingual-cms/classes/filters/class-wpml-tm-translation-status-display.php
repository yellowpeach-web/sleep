<?php

require_once __DIR__ . '/../../inc/constants-since-5-0.php';

use WPML\FP\Fns;
use WPML\FP\Maybe;
use WPML\Settings\PostType\Automatic;
use WPML\Setup\Option;
use WPML\TM\ATE\TranslateEverything;
use WPML\TM\ATE\TranslateEverything\Cutoff;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Logic;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\Element\API\Languages;
use WPML\LIB\WP\Post;
use WPML\API\PostTypes;
use WPML\TM\API\Jobs;
use WPML\TM\Menu\TranslationQueue\TranslationQueuePage;
use function WPML\FP\partial;
use WPML\LIB\WP\User;
use WPML\UIPage;
use WPML\FP\Relation;
use WPML\TranslationRoles\SelfTranslator;
use WPML\TranslationRoles\LangPairPermissions;
use function WPML\FP\pipe;

class WPML_TM_Translation_Status_Display {

	private $statuses        = array();
	private $stats_preloaded = false;

	private $lang_pair_allowed_cache = array();

	private $self_translator = null;

	private $lang_pair_permissions = null;

	private $user_rights_cache = array();

	private $status_helper;

	private $job_factory;

	protected $tm_api;

	private $post_translations;

	protected $sitepress;

	private $rendered_links  = array();
	private $integration_links = array();
	private $wpdb;

	private $untranslatedPosts;

	public function __construct(
		wpdb $wpdb,
		SitePress $sitepress,
		WPML_Post_Status $status_helper,
		WPML_Translation_Job_Factory $job_factory,
		WPML_TM_API $tm_api,
		TranslateEverything\UntranslatedPosts $untranslatedPosts
	) {
		$this->post_translations = $sitepress->post_translations();
		$this->wpdb              = $wpdb;
		$this->status_helper     = $status_helper;
		$this->job_factory       = $job_factory;
		$this->tm_api            = $tm_api;
		$this->sitepress         = $sitepress;
		$this->untranslatedPosts = $untranslatedPosts;
	}

	public function init() {
		add_action( 'wpml_cache_clear', array( $this, 'init' ), 11, 0 );
		add_filter(
			'wpml_css_class_to_translation',
			array(
				$this,
				'filter_status_css_class',
			),
			10,
			4
		);
		add_filter(
			'wpml_link_to_translation',
			array(
				$this,
				'filter_status_link',
			),
			1,
			4
		);
		add_filter(
			'wpml_link_to_translation',
			array(
				$this,
				'veto_status_link',
			),
			10,
			4
		);
		add_filter(
			'wpml_text_to_translation',
			array(
				$this,
				'filter_status_text',
			),
			10,
			4
		);

		add_filter( 'wpml_post_status_display_html', array( $this, 'add_links_data_attributes' ), 10, 4 );

		$this->statuses                = array();
		$this->lang_pair_allowed_cache = array();
		$this->user_rights_cache       = array();
		$this->stats_preloaded = false;
	}

	private function preload_stats() {
		$this->load_stats( $this->post_translations->get_trids() );
		$this->stats_preloaded = true;
	}

	private function load_stats( $trids ) {
		$trids = array_values( array_filter( array_map( 'intval', (array) $trids ) ) );
		if ( ! $trids ) {
			return;
		}

		foreach ( $trids as $requested_trid ) {
			if ( ! isset( $this->statuses[ $requested_trid ] ) ) {
				$this->statuses[ $requested_trid ] = array();
			}
		}

		$wpdb  = $this->wpdb;
		$stats = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT translation_status.status,
       				languages.code,
       				translation_status.translator_id,
       				translation_status.translation_service,
       				translation_status.needs_update,
       				translations.trid,
       			    translate_job.job_id,
       				translate_job.editor,
       				translate_job.automatic,
       				job_error.error_type,
       				job_error.error_message,
       				job_error.counter AS error_counter
				FROM {$wpdb->prefix}icl_languages languages
				LEFT JOIN {$wpdb->prefix}icl_translations translations
					ON languages.code = translations.language_code
				JOIN {$wpdb->prefix}icl_translation_status translation_status
					ON translations.translation_id = translation_status.translation_id
				JOIN {$wpdb->prefix}icl_translate_job translate_job
					ON translate_job.rid = translation_status.rid AND translate_job.revision IS NULL
				LEFT JOIN {$wpdb->prefix}icl_translate_unsolvable_jobs job_error
				ON job_error.job_id = translate_job.job_id
				AND translation_status.status IN (1, 2, 41)
				WHERE languages.active = 1
					AND (
						translations.trid IN ( " . implode( ', ', array_fill( 0, count( $trids ), '%d' ) ) . " )
						OR translations.trid IS NULL
					)",
				$trids
			),
			ARRAY_A
		);
		foreach ( $stats as $element ) {
			$this->statuses[ $element['trid'] ][ $element['code'] ] = $element;
		}

	}

	public function filter_status_css_class( $css_class, $post_id, $lang, $trid ) {
		$this->maybe_load_stats( $trid );

		$element_id  = $this->post_translations->get_element_id( $lang, $trid );
		$source_lang = $this->post_translations->get_source_lang_code( $element_id );

		if ( $this->has_unsolvable_error( $trid, $lang ) ) {
			$css_class = 'otgs-ico-warning';
			if ( ! $this->is_lang_pair_allowed( $lang, $source_lang, $post_id ) ) {
				$css_class .= ' otgs-ico-warning-disabled';
			}
			return $css_class;
		}

		$post_status = $this->get_post_status( $post_id );

		if ( $this->is_in_progress( $trid, $lang ) ) {
			$css_class = 'otgs-ico-in-progress';
			if ( $this->has_no_control( $post_id, $lang, $trid, $source_lang ) ) {
				$css_class .= ' otgs-ico-in-progress-disabled';
			}
		} elseif ( $this->is_original_element( $element_id, $source_lang ) ) {
			if ( ! $this->can_edit_original( $element_id ) ) {
				$css_class .= ' otgs-ico-edit-disabled';
			}
		} elseif ( ! $this->is_lang_pair_allowed( $lang, $source_lang, $post_id ) && $element_id ) {
			$css_class .= ' otgs-ico-edit-disabled';
		} elseif ( ! $this->is_lang_pair_allowed( $lang, $source_lang, $post_id ) && ! $element_id ) {
			$css_class .= ' otgs-ico-add-disabled';
		} elseif ( ! $this->has_user_rights_to_translate( $trid, $lang ) ) {
			$css_class .= ' otgs-ico-edit-disabled';
		}

		if ( ( $this->isTranslateEverythingInProgress( $trid, $post_id, $lang ) && ( 'draft' !== $post_status || $this->is_in_progress( $trid, $lang ) ) ) ) {
			$css_class .= ' otgs-ico-waiting';
		}

		return $css_class;
	}

	private function get_post_status( $post_id ) {
		return Maybe::of( $post_id )
			->map( 'get_post' )
			->map( Obj::prop( 'post_status' ) )
			->getOrElse( partial( 'get_post_status', $post_id ) );
	}

	public function filter_status_text( $text, $original_post_id, $lang, $trid ) {
		$source_lang = $this->post_translations->get_element_lang_code( $original_post_id );
		$element_id  = $this->post_translations->get_element_id( $lang, $trid );

		$this->maybe_load_stats( $trid );

		if ( $this->has_unsolvable_error( $trid, $lang ) ) {
			$text = __( 'This translation job hit an error and could not be completed. Send the content for translation again from the Translation Dashboard.', 'sitepress' );
			return $text;
		}
		if ( ( $this->is_remote( $trid, $lang ) && $this->is_in_progress( $trid, $lang ) ) || $this->it_needs_retry( $trid, $lang ) ) {
			if ( $this->is_remote( $trid, $lang ) ) {
				$ts_name = TranslationProxy::get_service_name( intval( $this->statuses[ $trid ][ $lang ]['translation_service'] ) );
				/* translators: Status of a translation in the list of content: it was sent out and has not come back yet. %s: the name of the translation service. */
				$text    = sprintf( __( 'Waiting for translation from %s', 'sitepress' ), $ts_name );
			} else {
				$language = $this->sitepress->get_language_details( $lang );
				$text     = sprintf(
					/* translators: %s is the language name. */
					__( '%s: Sending this content for automatic translation did not go through. WPML retries automatically - you don\'t need to do anything.', 'sitepress' ),
					$language['display_name']
				);
			}

		} elseif ( $this->is_lang_pair_allowed( $lang, null, $original_post_id ) && $this->is_in_progress( $trid, $lang ) ) {
			$language = $this->sitepress->get_language_details( $lang );

			if ( $this->shouldAutoTranslate( $trid, $original_post_id, $lang ) && $this->has_no_manual_translation( $trid, $lang ) ) {
				/* translators: Status of a translation in the list of content: the machine has not translated it yet. %s: the name of the language. */
				$text = sprintf( __( '%s: Waiting for automatic translation', 'sitepress' ), $language['display_name'] );
			} else {
				$text = $this->get_in_progress_status_txt( $trid, $lang, $language );
			}
		} elseif ( $this->is_original_element( $element_id, $this->post_translations->get_source_lang_code( $element_id ) ) ) {
			if ( ! $this->can_edit_original( $element_id ) ) {
				/* translators: Hover text of the greyed icon in the list of content that would open the original of a translation, shown to a user who may not edit that original. */
				$text = __( 'You don\'t have the rights to edit this content. Ask your site administrator to give you access to it.', 'sitepress' );
			}
		} elseif ( ! $this->is_lang_pair_allowed( $lang, $source_lang, $original_post_id ) ) {
			$language        = $this->sitepress->get_language_details( $lang );
			$source_language = $this->sitepress->get_language_details( $source_lang );
			$text            = sprintf(
				/* translators: Message shown to a translator who may not work on this pair of languages. %1$s: the language the content is written in, %2$s: the language it is to be translated into. */
				__( 'You don\'t have the rights to translate from %1$s to %2$s. Ask your translation manager to give you access to this language pair.', 'sitepress' ),
				$source_language['display_name'],
				$language['display_name']
			);
		} elseif ( ! $this->has_user_rights_to_translate( $trid, $lang ) ) {
			$text = __( 'You can only edit translations assigned to you. Ask your translation manager to assign this translation to you.', 'sitepress' );
		}

		if ( $this->isTranslateEverythingInProgress( $trid, $original_post_id, $lang ) ) {
			$text = __( 'WPML is translating your content automatically. You can monitor the progress in the admin bar.', 'sitepress' );
		}

		return $text;
	}

	private function has_no_manual_translation( $trid, $lang ) {
		if (
			! array_key_exists( $trid, $this->statuses ) ||
			! array_key_exists( $lang, $this->statuses[ $trid ] )
		) {
			return true;
		}

		$job_is_canceled  = pipe(
			Obj::path( [ $trid, $lang, 'status' ] ),
			Relation::equals( ICL_TM_NOT_TRANSLATED )
		);
		$job_is_automatic = Obj::path( [ $trid, $lang, 'automatic' ] );

		return Logic::anyPass(
			[
				$job_is_canceled,
				$job_is_automatic,
			],
			$this->statuses
		);
	}

	public function filter_status_link( $link, $post_id, $lang, $trid ) {
		$produced = $this->default_status_link( $link, $post_id, $lang, $trid );

		$this->rendered_links[ $post_id ][ $lang ][ $trid ] = (string) $produced;

		return $produced;
	}

	private function default_status_link( $link, $post_id, $lang, $trid ) {
		$translated_element_id = $this->post_translations->get_element_id( $lang, $trid );
		$source_lang = $this->post_translations->get_source_lang_code( $translated_element_id ) ?: null;

		if ( (bool) $translated_element_id && (bool) $source_lang === false ) {
			return $link;
		}

		if ( $this->translation_is_in_trash( $translated_element_id ) ) {
			return $link;
		}

		$this->maybe_load_stats( $trid );

		if ( $this->has_no_control( $post_id, $lang, $trid, $source_lang ) ) {
			return '';
		}

		$is_remote      = $this->is_remote( $trid, $lang );
		$is_in_progress = $this->is_in_progress( $trid, $lang );

		$source_lang_code = $this->post_translations->get_element_lang_code( $post_id );

		if ( $source_lang_code !== $lang ) {
			if ( ( $is_in_progress && ! $is_remote ) || (bool) $translated_element_id ) {
				$job_id = $this->job_factory->job_id_by_trid_and_lang( $trid, $lang );
				if ( $job_id && ! is_admin() ) {
					$job_object = $this->job_factory->get_translation_job( $job_id, false, 0, true );
					if ( $job_object && ! $job_object->user_can_translate( wp_get_current_user() ) ) {
						return $link;
					}
				}
			}

			return $this->get_intent_link( $trid, $lang, $source_lang_code );
		}

		return $link;
	}

	public function veto_status_link( $link, $post_id, $lang, $trid ) {
		if ( isset( $this->rendered_links[ $post_id ][ $lang ][ $trid ] ) ) {
			$this->integration_links[ $post_id ][ $lang ][ $trid ] =
				(string) $link !== $this->rendered_links[ $post_id ][ $lang ][ $trid ];
		}

		if ( ! $link ) {
			return $link;
		}

		$translated_element_id = $this->post_translations->get_element_id( $lang, $trid );
		$source_lang = $this->post_translations->get_source_lang_code( $translated_element_id ) ?: null;

		if ( (bool) $translated_element_id && (bool) $source_lang === false ) {
			return $link;
		}

		$this->maybe_load_stats( $trid );

		return $this->has_no_control( $post_id, $lang, $trid, $source_lang ) ? '' : $link;
	}

	private function translation_is_in_trash( $translated_element_id ) {
		$translated_element_id = (int) $translated_element_id;

		if ( $translated_element_id <= 0 ) {
			return false;
		}

		return 'trash' === $this->get_post_status( $translated_element_id );
	}

	private function is_original_element( $element_id, $source_lang ) {
		return (bool) $element_id && ! $source_lang;
	}

	private function can_edit_original( $element_id ) {
		return (bool) current_user_can( 'edit_post', (int) $element_id );
	}

	private function has_no_control( $post_id, $lang, $trid, $source_lang ) {
		return $this->has_unsolvable_error( $trid, $lang )
		       || ( $this->is_remote( $trid, $lang ) && $this->is_in_progress( $trid, $lang ) )
		       || ! $this->is_lang_pair_allowed( $lang, $source_lang, $post_id )
		       || $this->it_needs_retry( $trid, $lang );
	}

	private function shouldUseTMEditor( $postId ) {
		static $cachedKeys;
		if ( ! isset( $cachedKeys[ $postId ] ) ) {
			$cachedKeys[ $postId ] = ! \WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $postId );
		}

		return $cachedKeys[ $postId ];
	}

	public function add_links_data_attributes( $html, $post_id, $lang, $trid ) {
		if ( ! isset( $this->rendered_links[ $post_id ][ $lang ][ $trid ] ) ) {
			return $html;
		}

		$this->maybe_load_stats( $trid );

		$status = $this->status_helper->get_status( false, $trid, $lang );
		$action = 0 === $status ? 'add' : 'edit';

		$Attributes = [
			'user-can-translate' => $this->is_lang_pair_allowed( $lang, null, $post_id ) ? 'yes' : 'no',
			'should-ate-sync'    => ( ! $this->has_unsolvable_error( $trid, $lang ) && $this->shouldATESync( $trid, $lang ) ) ? '1' : '0',
			'source_lang'        => $this->post_translations->get_element_lang_code( $post_id ),
			'action'             => $action,
			'has-error'          => $this->has_unsolvable_error( $trid, $lang ) ? '1' : '0',
			'error-type'         => isset( $this->statuses[ $trid ][ $lang ]['error_type'] )
				? esc_attr( $this->statuses[ $trid ][ $lang ]['error_type'] )
				: '',
		];
		if ( isset( $this->statuses[ $trid ][ $lang ]['job_id'] ) ) {
			$Attributes['tm-job-id'] = esc_attr( $this->statuses[ $trid ][ $lang ]['job_id'] );
		}

		if (
			empty( $this->integration_links[ $post_id ][ $lang ][ $trid ] )
			&& ! $this->translation_is_in_trash( $this->post_translations->get_element_id( $lang, $trid ) )
			&& $lang !== $this->post_translations->get_source_lang_code( $post_id )
		) {
			$Attributes = array_merge(
				[
					'trid'     => $trid,
					'language' => $lang,
					'post-id'  => $post_id,
					'verb'     => $this->get_intent_verb( $html, $action ),
				],
				$Attributes
			);
		}

		$createAttribute = function ( $item, $key ) {
			return 'data-' . $key . '="' . $item . '"';
		};

		$data = wpml_collect( $Attributes )
			->map( $createAttribute )
			->implode( ' ' );

		return str_replace( '<a ', '<a ' . $data . ' ', $html );
	}

	private function get_intent_verb( $html, $action ) {
		if ( false !== strpos( $html, 'otgs-ico-needs-review' ) ) {
			return 'review';
		}

		if ( false !== strpos( $html, 'otgs-ico-edit' ) || false !== strpos( $html, 'otgs-ico-in-progress' ) ) {
			return 'edit';
		}

		if ( false !== strpos( $html, 'otgs-ico-add' ) || false !== strpos( $html, 'otgs-ico-refresh' ) ) {
			return 'translate';
		}

		return 'add' === $action ? 'translate' : 'edit';
	}

	private function get_intent_link( $trid, $lang, $source_lang_code ) {
		$args = array(
			'trid'                 => $trid,
			'language_code'        => $lang,
			'source_language_code' => $source_lang_code,
		);

		return add_query_arg( $args, self::get_tm_editor_base_url() );
	}

	public static function get_link_for_existing_job( $job_id ) {
		$args = array( 'job_id' => $job_id );

		return add_query_arg( $args, self::get_tm_editor_base_url() );
	}

	private static function get_tm_editor_base_url() {
		$returnUrl = self::get_return_url();
		$returnUrl = $returnUrl ? rawurlencode( esc_url_raw( stripslashes( $returnUrl ) ) ) : '';

		$args = array(
			'return_url' => $returnUrl,
			'lang'       => Languages::getCurrentCode(),
		);

		return add_query_arg( $args, TranslationQueuePage::base() );
	}

	private static function get_return_url() {
		$getBaseUrl = function (): string {
			$removeUnwantedArgs = function ( $url = false ) {
				$args = [ 'wpml_tm_saved', 'wpml_tm_cancel' ];

				return remove_query_arg( $args, $url );
			};

			$returnToTMDashboard = function () use ( $removeUnwantedArgs ) {
				return $removeUnwantedArgs( admin_url( UIPage::getTMDashboard() ) );
			};

			$returnToThePageWhichTriggeredAjaxIfThatPageCanBeDetermined = function () use ( $removeUnwantedArgs ) {
				if ( wpml_is_ajax() && isset( $_SERVER['HTTP_REFERER'] ) ) {
					return $removeUnwantedArgs( $_SERVER['HTTP_REFERER'] );
				}
			};

			$returnToTMDashboardIfItIsAjaxAndTriggeringPageCannotBeDetermined = function () use ( $returnToTMDashboard ) {
				if ( wpml_is_ajax() && ! isset( $_SERVER['HTTP_REFERER'] ) ) {
					return $returnToTMDashboard();
				}
			};

			$otherwiseReturnToCurrentPage = function () use ( $removeUnwantedArgs ) {
				$currentUrl = \WPML\TM\API\Jobs::getCurrentUrl();

				return $removeUnwantedArgs( $currentUrl );
			};

			$strategies = [
				$returnToThePageWhichTriggeredAjaxIfThatPageCanBeDetermined,
				$returnToTMDashboardIfItIsAjaxAndTriggeringPageCannotBeDetermined,
				$otherwiseReturnToCurrentPage,
			];

			return Logic::firstSatisfying( Logic::isTruthy(), $strategies, null );
		};

		return add_query_arg(
			[
				'lang'         => Languages::getCurrentCode(),
				'referer'      => 'ate',
				'wpml_version' => ICL_SITEPRESS_VERSION,
			],
			$getBaseUrl()
		);
	}

	protected function is_lang_pair_allowed( $lang_to, $lang_from = null, $post_id = 0 ) {
		$lang_from = $lang_from ?: Languages::getCurrentCode();
		$cache_key = $lang_from . ':' . $lang_to . ':' . (int) $post_id;

		if ( ! array_key_exists( $cache_key, $this->lang_pair_allowed_cache ) ) {
			$this->lang_pair_allowed_cache[ $cache_key ] =
				$this->get_lang_pair_permissions()->is_allowed( $lang_from, $lang_to, $post_id );
		}

		return $this->lang_pair_allowed_cache[ $cache_key ];
	}

	private function get_lang_pair_permissions() {
		if ( ! $this->lang_pair_permissions ) {
			$this->lang_pair_permissions = new LangPairPermissions(
				function ( array $args ) {
					return (bool) $this->tm_api->is_translator_filter(
						false,
						$this->sitepress->get_wp_api()->get_current_user_id(),
						$args
					);
				},
				$this->get_self_translator()
			);
		}

		return $this->lang_pair_permissions;
	}

	private function get_self_translator() {
		if ( ! $this->self_translator ) {
			$this->self_translator = new SelfTranslator();
		}

		return $this->self_translator;
	}

	private function has_user_rights_to_translate( $trid, $lang ) {
		$cache_key = $trid . ':' . $lang;

		if ( ! array_key_exists( $cache_key, $this->user_rights_cache ) ) {
			$this->user_rights_cache[ $cache_key ] = $this->check_user_rights_to_translate( $trid, $lang );
		}

		return $this->user_rights_cache[ $cache_key ];
	}

	private function check_user_rights_to_translate( $trid, $lang ) {
		$user = User::getCurrent();
		if ( User::isAdministrator( $user ) || User::isEditor( $user ) ) {
			return true;
		}

		$job = Jobs::getTridJob( $trid, $lang );
		if ( ! $job ) {
			return true;
		}

		if ( ! Obj::prop( 'translator_id', $job ) ) {
			return true;
		}

		if ( (int) Obj::propOr( 0, 'translator_id', $job ) === (int) $user->ID ) {
			return true;
		}

		return false;
	}

	private function maybe_load_stats( $trid ) {
		if ( ! $this->stats_preloaded ) {
			$this->preload_stats();
		}

		if ( ! isset( $this->statuses[ $trid ] ) ) {
			$this->statuses[ $trid ] = array();
			$this->load_stats( array( $trid ) );
		}
	}

	private function is_remote( $trid, $lang ) {

		return isset( $this->statuses[ $trid ][ $lang ]['translation_service'] )
		       && (bool) $this->statuses[ $trid ][ $lang ]['translation_service'] !== false
		       && $this->statuses[ $trid ][ $lang ]['translation_service'] !== 'local';
	}

	public function has_local_job_in_progress( $trid, $lang ) {
		$this->maybe_load_stats( $trid );

		return $this->is_in_progress( $trid, $lang ) && ! $this->is_remote( $trid, $lang );
	}

	private function is_in_progress( $trid, $lang ) {
		return Lst::includes(
            (int) Obj::path( [ $trid, $lang, 'status' ], $this->statuses ),
            [
				ICL_TM_IN_PROGRESS,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_ATE_NEEDS_RETRY,
			]
        );
	}

	private function it_needs_retry( $trid, $lang ) {
		return (int) Obj::path( [ $trid, $lang, 'status' ], $this->statuses ) === ICL_TM_ATE_NEEDS_RETRY;
	}

	private function isTranslateEverythingInProgress( $trid, $postId, $language ) {
		$postType = Post::getType( $postId );
		return $postType
			   && Option::shouldTranslateEverything()
		       && $this->shouldAutoTranslate( $trid, $postId, $language )
		       && ! $this->untranslatedPosts->isPostTypeProcessedForTypeAndLanguage( $postType, $language )
		       && $this->isPostWithinTeaSinceDate( $postId, $postType )
		       && Lst::includes( Post::getType( $postId ), PostTypes::getAutomaticTranslatable() );
	}

	private function isPostWithinTeaSinceDate( $postId, $postType ) {
		$sinceDate = Option::getTranslateEverythingPostSinceDate( $postType );

		if ( Option::SINCE_DATE_SKIP_TYPE === $sinceDate ) {
			return false;
		}

		if ( '0000-00-00' === $sinceDate ) {
			return true;
		}

		$post = get_post( $postId );
		if ( ! $post ) {
			return false;
		}

		return Cutoff::isPostWithin( $post, (string) $sinceDate );
	}

	private function shouldAutoTranslate( $trid, $postId, $targetLang ) {
		if ( ! AutomaticTranslationCapabilities::isAvailable() ) {
			return false;
		}

		$isOriginalPost = ! (bool) $this->post_translations->get_source_lang_code( $postId );
		$postLanguage   = $this->post_translations->get_element_lang_code( $postId );

		return $isOriginalPost &&
		       $postLanguage === Languages::getDefaultCode() &&
		       $this->shouldUseTMEditor( $postId ) &&
		       Automatic::shouldTranslate( get_post_type( $postId ) ) &&
		       AutomaticTranslationCapabilities::isLanguageEligible( $targetLang, $postLanguage ) &&
		       $this->isExistingTranslationOpenToAte( $trid, $targetLang );
	}

	private function isExistingTranslationOpenToAte( $trid, $targetLang ): bool {
		if ( Jobs::isDeliveredAndCurrent( Obj::path( [ $trid, $targetLang ], $this->statuses ) ) ) {
			return false;
		}

		$jobId = isset( $this->statuses[ $trid ][ $targetLang ]['job_id'] )
			? $this->statuses[ $trid ][ $targetLang ]['job_id']
			: null;

		if ( ! $jobId ) {
			return true;
		}

		return ! wpml_tm_load_old_jobs_editor()->shouldStickToWPMLEditor( $jobId, Jobs::get( $jobId ) );
	}

	private function shouldATESync( $trid, $lang ) {
		$job = Obj::path( [ $trid, $lang ], $this->statuses );

		return Jobs::shouldBeATESynced( $job );
	}

	private function get_in_progress_status_txt( $trid, $lang, $language_details ) {
		if ( Obj::path( [ $trid, $lang, 'automatic' ], $this->statuses ) ) {
			return sprintf(
				/* translators: Tooltip on the translation status of a piece of content, while an automatic translation is under way. %s: the name of the language it is being translated into. Verb, imperative. */
				__( 'Complete the %s translation', 'sitepress' ),
				$language_details['display_name']
			);
		}

		$translator_id = Obj::path( [ $trid, $lang, 'translator_id' ], $this->statuses );
		$status_txt    = $translator_id
			// Translators: %s: Language display name.
			? __( '%s translation assigned to local translator', 'sitepress' )
			// Translators: %s: Language display name.
			: __( '%s translation awaiting first available translator', 'sitepress' );

		return sprintf( $status_txt, $language_details['display_name'] );
	}

	private function has_unsolvable_error( $trid, $lang ) {
		if ( (int) Obj::path( [ $trid, $lang, 'status' ], $this->statuses ) === ICL_TM_ATE_UNSOLVABLE ) {
			return true;
		}

		if ( ! isset( $this->statuses[ $trid ][ $lang ]['error_type'] ) ) {
			return false;
		}

		$error_type = $this->statuses[ $trid ][ $lang ]['error_type'];

		if ( $error_type === 'SyncError' ) {
			return true;
		}

		$counter = isset( $this->statuses[ $trid ][ $lang ]['error_counter'] )
			? (int) $this->statuses[ $trid ][ $lang ]['error_counter']
			: 0;

		if ( $error_type === 'ApplyError' ) {
			return $counter >= 1;
		}

		if ( $error_type === 'DownloadError' ) {
			return $counter >= 3;
		}

		return false;
	}
}
