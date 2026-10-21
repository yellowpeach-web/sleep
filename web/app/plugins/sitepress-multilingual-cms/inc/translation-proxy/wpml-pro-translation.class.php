<?php

use WPML\Blocks\AttributeUrls;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Str;
use function WPML\Container\make;
use function WPML\FP\pipe;

class WPML_Pro_Translation extends WPML_TM_Job_Factory_User {

	private static $translated_links = [];

	private static $deferred_link_fixes = [];

	public $errors = array();
	private $tmg;

	private $cms_id_helper;

	private $xliff_reader_factory;


	private $sitepress;

	private $is_language_switched = false;

	private $update_pm;

	function __construct( &$job_factory ) {
		parent::__construct( $job_factory );
		global $iclTranslationManagement, $wpdb, $sitepress, $wpml_post_translations, $wpml_term_translations;

		$this->tmg                  =& $iclTranslationManagement;
		$this->xliff_reader_factory = new WPML_TM_Xliff_Reader_Factory( $this->job_factory );
		$wpml_tm_records            = new WPML_TM_Records( $wpdb, $wpml_post_translations, $wpml_term_translations );
		$this->cms_id_helper        = new WPML_TM_CMS_ID( $wpml_tm_records, $job_factory );
		$this->sitepress            = $sitepress;

		add_filter( 'xmlrpc_methods', array( $this, 'custom_xmlrpc_methods' ) );
		add_action(
			'post_submitbox_start',
			array(
				$this,
				'post_submitbox_start',
			)
		);

		add_action( 'wpml_minor_edit_for_gutenberg', array( $this, 'gutenberg_minor_edit' ), 10, 0 );
	}

	public static function reset_translated_links_cache() {
		self::$translated_links = [];
	}

	public static function fix_deferred_links() {
		$deferred                  = self::$deferred_link_fixes;
		self::$deferred_link_fixes = [];

		foreach ( $deferred as $fix ) {
			list( $pro_translation, $element_id, $target_lang_code, $element_type, $preLoaded ) = $fix;
			$pro_translation->fix_links_to_translated_content( $element_id, $target_lang_code, $element_type, $preLoaded );
		}
	}

	private static function is_before_init() {
		return function_exists( 'did_action' ) && 0 === did_action( 'init' );
	}

	private function defer_link_fix( $element_id, $target_lang_code, $element_type, $preLoaded ) {
		add_action( 'init', [ __CLASS__, 'fix_deferred_links' ], PHP_INT_MAX, 0 );

		self::$deferred_link_fixes[ $element_type . ':' . $element_id . ':' . $target_lang_code ] = [
			$this,
			$element_id,
			$target_lang_code,
			$element_type,
			is_array( $preLoaded ) ? $preLoaded : [],
		];
	}

	public function &get_cms_id_helper() {
		return $this->cms_id_helper;
	}

	public function get_current_project() {
		return TranslationProxy::get_current_project();
	}

	function send_post( $postOrPackage, $target_languages, $translator_id, $job_id, $tp_batch_info = null ) {
		global $sitepress, $iclTranslationManagement;

		$this->maybe_init_translation_management( $iclTranslationManagement );

		if ( is_numeric( $postOrPackage ) ) {
			$postOrPackage = get_post( $postOrPackage );
		}
		if ( ! $postOrPackage ) {
			return false;
		}

		$element_id          = $postOrPackage->ID;
		$element_type           = $postOrPackage->post_type;
		$element_type_prefix = $iclTranslationManagement->get_element_type_prefix_from_job_id( $job_id );
		$element_type        = $element_type_prefix . '_' . $element_type;

		if( $postOrPackage instanceof WPML_Package ) {
			$note = $postOrPackage->translator_note;
		} else {
			$note = WPML_TM_Translator_Note::get( $element_id );
		}

		if ( ! $note ) {
			$note = null;
		}
		$err             = false;
		$tp_job_id       = false;
		$source_language = $sitepress->get_language_for_element( $element_id, $element_type );
		$target_language = is_array( $target_languages ) ? end( $target_languages ) : $target_languages;
		if ( empty( $target_language ) || $target_language === $source_language ) {
			return false;
		}
		$translation = $this->tmg->get_element_translation( $element_id, $target_language, $element_type );
		if ( ! $translation ) {
			$err = true;
		}
		if ( ! $err && ( $translation->needs_update || $translation->status == ICL_TM_NOT_TRANSLATED || $translation->status == ICL_TM_WAITING_FOR_TRANSLATOR ) ) {
			$project = TranslationProxy::get_current_project();

			if ( $iclTranslationManagement->is_external_type( $element_type_prefix ) ) {
				$job_object = new WPML_External_Translation_Job( $job_id );
			} else {
				$job_object = new WPML_Post_Translation_Job( $job_id );
				$job_object->load_terms_from_post_into_job();
			}

			list( $err, $project, $tp_job_id ) = $job_object->send_to_tp( $project, $translator_id, $this->cms_id_helper, $this->tmg, $note, $tp_batch_info );
			if ( $err ) {
				$this->enqueue_project_errors( $project );
			}
		}

		return $err ? false : $tp_job_id;
	}

	function server_languages_map( $language_name, $server2plugin = false ) {
		if ( is_array( $language_name ) ) {
			return array_map( array( $this, 'server_languages_map' ), $language_name );
		}
		$map = array(
			'Norwegian Bokmål'     => 'Norwegian',
			'Portuguese, Brazil'   => 'Portuguese',
			'Portuguese, Portugal' => 'Portugal Portuguese',
		);

		$map = $server2plugin ? array_flip( $map ) : $map;

		return isset( $map[ $language_name ] ) ? $map[ $language_name ] : $language_name;
	}

	public function custom_xmlrpc_methods( $methods ) {
		$icl_methods = \WPML\Request\Adapter\XmlRpc::method(
			array(),
			'translationproxy.test_xmlrpc',
			\WPML\Request\Policy\Policy::publicAccess( 'Translation Proxy connectivity probe: answers true, no state change' ),
			'__return_true'
		);
		$icl_methods = \WPML\Request\Adapter\XmlRpc::method(
			$icl_methods,
			'translationproxy.updated_job_status',
			\WPML\Request\Policy\Policy::machine(
				array( $this, 'authenticate_updated_job_status_call' ),
				'Translation Proxy job-status callback signed with the project access key (sha1, hash_equals)'
			),
			array(
				$this,
				'xmlrpc_updated_job_status',
			)
		);

		$methods = array_merge( $methods, $icl_methods );
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST && preg_match( '#<methodName>([^<]+)</methodName>#i', $this->sitepress->get_wp_api()->get_raw_post_data(), $matches ) ) {
			$method = $matches[1];
			if ( array_key_exists( $method, $icl_methods ) ) {
				set_error_handler( array( $this, 'translation_error_handler' ), E_ERROR | E_USER_ERROR );
			}
		}

		return $methods;
	}

	public function xmlrpc_updated_job_status( $args ) {
		global $wpdb;

		$tp_id     = isset( $args[0] ) ? $args[0] : 0;
		$cms_id    = isset( $args[1] ) ? $args[1] : 0;
		$status    = isset( $args[2] ) ? $args[2] : '';
		$signature = isset( $args[3] ) ? $args[3] : '';

		\WPML\TM\Jobs\JobLog::maybeInitRequest();
		\WPML\TM\Jobs\JobLog::createNewGroup(
			\WPML\TM\Jobs\JobLog::GROUP_ID_DOWNLOAD_JOBS,
			'TP push delivery (XML-RPC)',
			array(
				'tp_id'  => $tp_id,
				'cms_id' => $cms_id,
				'status' => $status,
			)
		);

		if ( ! $this->authenticate_request( $tp_id, $cms_id, $status, $signature ) ) {
			\WPML\TM\Jobs\JobLog::addError( 'tp_push_bad_signature', array( 'tp_id' => $tp_id, 'cms_id' => $cms_id ) );
			\WPML\TM\Jobs\JobLog::finishCurrentGroup();

			return new IXR_Error( 401, 'Wrong signature' );
		}

		try {

			$jobs_repository = wpml_tm_get_jobs_repository();

			$job_match = $jobs_repository->get(
				new WPML_TM_Jobs_Search_Params(
					array(
						'scope' => 'remote',
						'tp_id' => $tp_id,
					)
				)
			);

			if ( $job_match ) {
				$jobs_array = $job_match->toArray();
				$job        = $jobs_array[0];
				$job->set_status( WPML_TP_Job_States::map_tp_state_to_local( $status ) );

				$tp_sync_updated_job = new WPML_TP_Sync_Update_Job( $wpdb, $this->sitepress );
				$job_updated         = $tp_sync_updated_job->update_state( $job );

				\WPML\TM\Jobs\JobLog::add(
					'tp_push_state_updated',
					array(
						'job_id'      => \WPML\TM\Jobs\JobLog::safeCall( $job, 'get_id' ),
						'job_updated' => (bool) $job_updated,
					)
				);

				if ( $job_updated && WPML_TP_Job_States::CANCELLED !== $status ) {
					$apply_tp_translation = new WPML_TP_Apply_Single_Job(
						wpml_tm_get_tp_translations_repository(),
						new WPML_TP_Apply_Translation_Strategies( $wpdb )
					);
					$apply_tp_translation->apply( $job );

					\WPML\TM\Jobs\JobLog::add(
						'tp_push_applied',
						array( 'job_id' => \WPML\TM\Jobs\JobLog::safeCall( $job, 'get_id' ) )
					);
				}

				\WPML\TM\Jobs\JobLog::finishCurrentGroup();

				return 1;

			}

			\WPML\TM\Jobs\JobLog::addError(
				'tp_push_job_not_found',
				array( 'tp_id' => $tp_id, 'cms_id' => $cms_id )
			);
		} catch ( Exception $e ) {
			\WPML\TM\Jobs\JobLog::addError(
				'tp_push_failed',
				array( 'code' => $e->getCode(), 'message' => $e->getMessage() )
			);
			\WPML\TM\Jobs\JobLog::finishCurrentGroup();

			return new IXR_Error( $e->getCode(), $e->getMessage() );
		}

		\WPML\TM\Jobs\JobLog::finishCurrentGroup();

		return 0;
	}

	public function authenticate_updated_job_status_call( $args ) {
		$args = is_array( $args ) ? $args : array();

		if ( $this->authenticate_request(
			isset( $args[0] ) ? $args[0] : 0,
			isset( $args[1] ) ? $args[1] : 0,
			isset( $args[2] ) ? $args[2] : '',
			isset( $args[3] ) ? $args[3] : ''
		) ) {
			return true;
		}

		return class_exists( 'IXR_Error' ) ? new IXR_Error( 401, 'Wrong signature' ) : false;
	}

	private function authenticate_request( $tp_id, $cms_id, $status, $signature ) {
		$project = TranslationProxy::get_current_project();

		if ( ! is_object( $project ) || empty( $project->access_key ) || ! is_string( $signature ) ) {
			return false;
		}

		$expected = sha1( $project->id . $project->access_key . $tp_id . $cms_id . $status );

		return hash_equals( $expected, $signature );
	}

	function get_wpml_wp_api() {
		return $this->sitepress->get_wp_api();
	}

	private static function content_get_link_paths( $body ) {

		$regexp_links = array(
			"/<a[^>]*href\s*=\s*([\"\']??)([^\"^>]+)[\"\']??([^>]*)>/i",
		);

		$links = array();

		foreach ( $regexp_links as $regexp ) {
			if ( preg_match_all( $regexp, is_null( $body ) ? '' : $body, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$links[] = $match;
				}
			}
		}

		return $links;
	}

	private static function content_get_block_attribute_urls( $body ) {
		return AttributeUrls::fromBody( $body );
	}

	private static function replace_block_attribute_urls( $body, array $replacements ) {
		return AttributeUrls::replaceInDelimiters( $body, $replacements );
	}

	private static function get_href_from_tag( $tag ) {
		return preg_match( '/href\s*=\s*["\']([^"\'>]+)["\']/i', (string) $tag, $matches )
			? $matches[1]
			: false;
	}

	private static function get_url_replacements( $translated_links ) {
		$replacements = [];

		foreach ( (array) $translated_links as $link ) {
			if ( ! is_array( $link ) || ! isset( $link['from'], $link['to'] ) ) {
				continue;
			}

			$from = self::get_href_from_tag( $link['from'] );
			$to   = self::get_href_from_tag( $link['to'] );

			if ( $from && $to && $from !== $to ) {
				$replacements[ $from ] = $to;
			}
		}

		return $replacements;
	}

	public function fix_links_to_translated_content( $element_id, $target_lang_code, $element_type = 'post', $preLoaded = [] ) {
		global $wpdb, $sitepress;

		if ( self::is_before_init() ) {
			$this->defer_link_fix( $element_id, $target_lang_code, $element_type, $preLoaded );

			return 0;
		}

		$wpml_element_type          = $element_type;
		$body                       = '';
		$postExcerpt = '';
		$string_type                = null;
		$links_fixed_status_factory = new WPML_Links_Fixed_Status_Factory( $wpdb, new WPML_WP_API() );
		$links_fixed_status         = $links_fixed_status_factory->create( $element_id, $wpml_element_type );


		if ( strpos( $element_type, 'post' ) === 0 ) {
			if (
				is_array( $preLoaded )
				&& array_key_exists( 'post_type', $preLoaded )
				&& array_key_exists( 'post_content', $preLoaded )
				&& array_key_exists( 'post_excerpt', $preLoaded )
			) {
				$wpml_element_type = 'post_' . $preLoaded['post_type'];
				$body              = $preLoaded['post_content'];
				$postExcerpt       = $preLoaded['post_excerpt'];
			} else {
				$post = $wpdb->get_row(
					$wpdb->prepare( "SELECT post_content, post_excerpt, post_type FROM {$wpdb->posts} WHERE ID=%d", array( $element_id ) )
				);

				if ( ! $post ) {
					$links_fixed_status->set( true );

					return 0;
				}

				$body              = $post->post_content;
				$postExcerpt       = $post->post_excerpt;
				$wpml_element_type = 'post_' . $post->post_type;
			}
		} elseif ( $element_type == 'string' ) {
			if (
				is_array( $preLoaded )
				&& array_key_exists( 'value', $preLoaded )
				&& array_key_exists( 'string_id', $preLoaded )
			) {
				$body               = $preLoaded['value'];
				$original_string_id = $preLoaded['string_id'];
			} else {
				$data = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT string_id, value
						FROM {$wpdb->prefix}icl_string_translations
						WHERE id=%d",
						array( $element_id )
					)
				);

				if ( ! $data ) {
					$links_fixed_status->set( true );
					return 0;
				}

				$body               = $data->value;
				$original_string_id = $data->string_id;
			}

			$string_type = $wpdb->get_var( $wpdb->prepare( "SELECT type FROM {$wpdb->prefix}icl_strings WHERE id=%d", $original_string_id ) );
			if ( 'LINK' === $string_type ) {
				$body = '<a href="' . $body . '">removeit</a>';
			}
		}

		$blockAttributeUrls = self::content_get_block_attribute_urls( $body );

		$hasSomethingToFix = AbsoluteLinks::has_href_attribute( $body )
			|| AbsoluteLinks::has_href_attribute( $postExcerpt )
			|| $blockAttributeUrls;

		if ( ! $hasSomethingToFix ) {
			$links_fixed_status->set( true );
			return 0;
		}

		$translate_link_targets = make( 'WPML_Translate_Link_Targets' );
		$absolute_links         = make( 'AbsoluteLinks' );

		$is_language_switched = false;

		$sticky_link_resolved_without_change = false;
		$sticky_link_unresolved = false;
		$sticky_settled_overrides = [];

		try {
			$getTranslatedLink = function ( $link ) use ( $translate_link_targets, $absolute_links, $element_type, $target_lang_code, $sitepress, $string_type, &$is_language_switched, &$sticky_link_resolved_without_change, &$sticky_link_unresolved, &$sticky_settled_overrides ) {
				$id_link = $target_lang_code . '-' . $link[0];
				if ( isset( self::$translated_links[ $id_link ] ) ) {
					return [
						'from' => $link[0],
						'to'   => self::$translated_links[ $id_link ],
					];
				}

				if ( ! $is_language_switched ) {
					$is_language_switched = true;
					$sitepress->switch_lang( $target_lang_code );
				}

				$link_url = WPML_Same_Site_Url_Normalizer::normalize_url( $link[2] );

				$sticky_source_id = self::hostless_sticky_link_source_id( $link_url );

				$sticky_source_post_type = $sticky_source_id ? get_post_type( $sticky_source_id ) : false;

				if ( $sticky_source_post_type ) {
					$translated_id = apply_filters( 'wpml_object_id', $sticky_source_id, $sticky_source_post_type, false, $target_lang_code );

					if ( $translated_id && (int) $translated_id !== $sticky_source_id ) {
						$translatedLink = Str::replace(
							$link[2],
							preg_replace( '/([?&](?:p|page_id)=)' . $sticky_source_id . '\b/', '${1}' . (int) $translated_id, $link[2], 1 ),
							$link[0]
						);
					} elseif ( $translated_id ) {
						$translatedLink                      = $link[0];
						$sticky_link_resolved_without_change = true;

						$permalink = get_permalink( $translated_id );
						if ( is_string( $permalink ) && '' !== $permalink && $permalink !== $link[2] ) {
							$sticky_settled_overrides[ $permalink ] = $link[2];
						}
					} else {
						$translatedLink        = $link[0];
						$sticky_link_unresolved = true;
					}
				} elseif ( $absolute_links->is_home( $link_url ) ) {
					$translatedLink = $absolute_links->convert_url( $link_url, $target_lang_code );
					$translatedLink = Str::replace( $link[2], $translatedLink, $link[0] );
				} else {
					add_filter( 'wpml_force_translated_permalink', '__return_true' );
					try {
						$translatedLink = $translate_link_targets->convert_text( $link[0] );
					} finally {
						remove_filter( 'wpml_force_translated_permalink', '__return_true' );
					}

					$is_single_url = 'LINK' === $string_type || ! empty( $link['single_url'] );
					if ( $is_single_url && false !== strpos( $translatedLink, '<a href="' ) && $translate_link_targets->met_gone_target() ) {
						$translatedLink = $link[0];
					}

					if ( empty( $link['single_url'] ) && self::should_links_be_converted_back_to_sticky( $element_type ) ) {
						$translatedLink = $absolute_links->convert_text( $translatedLink );
					}
				}

				self::$translated_links[ $id_link ] = $translatedLink;

				return $translatedLink !== $link[0]
					? [
						'from' => $link[0],
						'to'   => $translatedLink,
					]
					: null;
			};

			$getTranslatedLinks = pipe(
				Fns::map( $getTranslatedLink ),
				Fns::filter( Fns::identity() )
			);

			$links            = self::content_get_link_paths( $body );
			$postExcerptLinks = self::content_get_link_paths( $postExcerpt );

			$blockLinks = array_map(
				function ( $url ) {
					return [ '<a href="' . $url . '">removeit</a>', '"', $url, '', 'single_url' => true ];
				},
				$blockAttributeUrls
			);

			$translatedLinks            = $getTranslatedLinks( $links );
			$translatedBlockLinks       = $getTranslatedLinks( $blockLinks );
			$postExcerptTranslatedLinks = $getTranslatedLinks( $postExcerptLinks );

			$replaceLink = function ( $body, $link ) {
				return str_replace( $link['from'], $link['to'], $body );
			};

			$urlReplacements = self::get_url_replacements(
				array_merge( (array) $translatedBlockLinks, (array) $translatedLinks )
			);

			foreach ( $sticky_settled_overrides as $permalink => $settled_href ) {
				$urlReplacements[ $permalink ] = $settled_href;
			}

			$new_body   = Fns::reduce( $replaceLink, $body, $translatedLinks );
			$new_body   = self::replace_block_attribute_urls( $new_body, $urlReplacements );
			$newExcerpt = Fns::reduce( $replaceLink, $postExcerpt, $postExcerptTranslatedLinks );

			$contentChanged = $sticky_link_resolved_without_change;

			if ( strpos( $element_type, 'post' ) === 0 ) {
				$updatePost = [];

				if ( $new_body != $body ) {
					$updatePost['post_content'] = $new_body;
				}

				if ( $newExcerpt != $postExcerpt ) {
					$updatePost['post_excerpt'] = $newExcerpt;
				}

				if ( ! empty( $updatePost ) ) {
					$contentChanged = true;
					$updated        = $wpdb->update(
						$wpdb->posts,
						$updatePost,
						[ 'ID' => $element_id ]
					);

					if ( false !== $updated ) {
						clean_post_cache( $element_id );

						$recordFields = [];
						if ( isset( $updatePost['post_content'] ) ) {
							$recordFields['body'] = $updatePost['post_content'];
						}
						if ( isset( $updatePost['post_excerpt'] ) ) {
							$recordFields['excerpt'] = $updatePost['post_excerpt'];
						}
						\WPML\TM\Jobs\TranslationRecord::followPostTranslation( $element_id, $wpml_element_type, $recordFields );
					}
				}
			} elseif ( $element_type == 'string' && $new_body != $body ) {
				$contentChanged = true;

				if ( 'LINK' === $string_type && false === strpos( $new_body, '<a href="' ) ) {
					$contentChanged  = false;
					$translatedLinks = [];
				} elseif ( 'LINK' === $string_type ) {
					$new_body = str_replace( array( '<a href="', '">removeit</a>' ), array( '', '' ), $new_body );
					$wpdb->update(
						$wpdb->prefix . 'icl_string_translations',
						array(
							'value'  => $new_body,
							'status' => ICL_TM_COMPLETE,
						),
						array( 'id' => $element_id )
					);
					do_action( 'icl_st_add_string_translation', $element_id );
					\WPML\TM\Jobs\TranslationRecord::followStringTranslation( $element_id, $new_body );
				} else {
					$wpdb->update( $wpdb->prefix . 'icl_string_translations', array( 'value' => $new_body ), array( 'id' => $element_id ) );
					\WPML\TM\Jobs\TranslationRecord::followStringTranslation( $element_id, $new_body );
				}
			}

			if ( $contentChanged && ! $sticky_link_unresolved ) {
				$links_fixed_status_factory = new WPML_Links_Fixed_Status_Factory( $wpdb, new WPML_WP_API() );
				$links_fixed_status         = $links_fixed_status_factory->create( $element_id, $wpml_element_type );
				$links_fixed_status->set( true );
			}

			return count( $translatedLinks );
		} finally {
			if ( $is_language_switched ) {
				$sitepress->switch_lang();
			}
		}
	}

	function translation_error_handler( $error_number, $error_string, $error_file, $error_line ) {
		switch ( $error_number ) {
			case E_ERROR:
			case E_USER_ERROR:
				throw new Exception( $error_string . ' [code:e' . $error_number . '] in ' . $error_file . ':' . $error_line );
			case E_WARNING:
			case E_USER_WARNING:
				return true;
			default:
				return true;
		}

	}

	private static function should_links_be_converted_back_to_sticky( $element_type ) {
		return 'string' !== $element_type && ! empty( $GLOBALS['WPML_Sticky_Links'] );
	}

	private static function hostless_sticky_link_source_id( $url ) {
		if ( ! is_string( $url ) || $url === '' || preg_match( '/^https?:\/\//', $url ) === 1 ) {
			return 0;
		}

		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! is_string( $query ) || $query === '' ) {
			return 0;
		}

		parse_str( $query, $vars );
		$id = isset( $vars['p'] ) ? $vars['p'] : ( isset( $vars['page_id'] ) ? $vars['page_id'] : null );

		return ( null !== $id && ctype_digit( (string) $id ) ) ? (int) $id : 0;
	}

	function post_submitbox_start() {
		$show_box_style = $this->get_show_minor_edit_style();
		if ( false !== $show_box_style ) {
			?>
			<p id="icl_minor_change_box" style="float:left;padding:0;margin:3px;<?php echo $show_box_style; ?>">
				<label><input type="checkbox" name="icl_minor_edit" value="1" style="min-width:15px;"/>&nbsp;
					<?php esc_html_e( 'Minor edit - don\'t update translation', 'sitepress' ); ?>
				</label>
				<br clear="all"/>
			</p>
			<?php
		}
	}

	public function gutenberg_minor_edit() {
		$show_box_style = $this->get_show_minor_edit_style();
		if ( false !== $show_box_style ) {
			?>
			<div id="icl_minor_change_box" style="<?php echo $show_box_style; ?>" class="icl_box_paragraph">
				<p>
					<strong><?php /* translators: Label of the option that marks a change as small, so the translators need not be told about it. */ esc_html_e( 'Minor edit', 'sitepress' ); ?></strong>
				</p>
				<label><input type="checkbox" name="icl_minor_edit" value="1" style="min-width:15px;"/>&nbsp;
					<?php esc_html_e( "Don't update translation", 'sitepress' ); ?>
				</label>
			</div>
			<?php
		}
	}

	private function get_show_minor_edit_style() {
		global $post, $iclTranslationManagement;
		if ( empty( $post ) || ! $post->ID ) {
			return false;
		}

		$element_language_details = $this->sitepress->get_element_language_details( $post->ID, 'post_' . $post->post_type );
		if ( $element_language_details && ! empty( $element_language_details->source_language_code ) ) {
			return false;
		}

		$translations = $iclTranslationManagement->get_element_translations( $post->ID, 'post_' . $post->post_type );
		foreach ( $translations as $t ) {
			if ( $t->status == ICL_TM_COMPLETE && ! $t->needs_update ) {
				return '';
			}
		}

		return 'display:none';
	}


	private function add_error( $project_error ) {
		$this->errors[] = $project_error;
	}

	function enqueue_project_errors( $project ) {
		if ( isset( $project ) && isset( $project->errors ) && $project->errors ) {
			foreach ( $project->errors as $project_error ) {
				$this->add_error( $project_error );
			}
		}
	}

	private function maybe_init_translation_management( $iclTranslationManagement ) {
		if ( empty( $this->tmg->settings ) ) {
			$iclTranslationManagement->init();
		}
	}
}
