<?php

use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;

class WPML_Global_AJAX extends WPML_SP_User {

	public function __construct( &$sitepress ) {
		parent::__construct( $sitepress );
		\WPML\Request\Adapter\Ajax::register( 'save_language_negotiation_type', \WPML\Request\Policy\Policy::capability( 'wpml_manage_languages', \WPML\Request\Policy\Authenticity::actionNonce( 'save_language_negotiation_type', 'nonce' ) ), array( $this, 'save_language_negotiation_type_action' ) );
	}

	private static function sanitize_language_domains( $domains, $stored = array(), array &$errors = array() ) {
		if ( ! is_array( $domains ) ) {
			return $domains;
		}

		$stored    = is_array( $stored ) ? $stored : array();
		$sanitized = array();

		foreach ( $domains as $code => $domain ) {
			$typed  = trim( wp_unslash( is_scalar( $domain ) ? (string) $domain : '' ) );
			$domain = WPML_Language_Domains::to_store( $typed, isset( $stored[ $code ] ) ? $stored[ $code ] : null );

			if ( WPML_Language_Domains::is_normalize_error( $domain ) ) {
				$errors[] = esc_html( WPML_Language_Domains::normalize_error_message( $domain, $typed ) );
				continue;
			}

			$sanitized[ $code ] = $domain;
		}

		return $sanitized;
	}

	public function save_language_negotiation_type_action() {
		if ( \WPML\Setup\Initializer::rejectSettingsMutationAjax() ) {
			return;
		}

		if ( ! current_user_can( 'wpml_manage_languages' ) ) {
			wp_send_json_error( __( "You can't do that!", 'sitepress' ) );

			return;
		}

		$errors             = array();
		$response           = false;
		$saved_root_page_id = 0;
		$nonce              = filter_input( INPUT_POST, 'nonce' );
		$action             = filter_input( INPUT_POST, 'action' );
		$is_valid_nonce     = wp_verify_nonce( $nonce, $action );

		$postHogCaptureEventData = [];

		if ( $is_valid_nonce ) {
			$icl_language_negotiation_type = filter_input( INPUT_POST, 'icl_language_negotiation_type', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
			$domain_errors                 = array();
			$language_domains              = self::sanitize_language_domains(
				filter_input( INPUT_POST, 'language_domains', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY | FILTER_NULL_ON_FAILURE ),
				$this->sitepress->get_setting( 'language_domains', array() ),
				$domain_errors
			);
			$use_directory                 = filter_input( INPUT_POST, 'use_directory', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
			$show_on_root                  = filter_input( INPUT_POST, 'show_on_root', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
			$root_html_file_path           = filter_input( INPUT_POST, 'root_html_file_path', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );
			$hide_language_switchers       = filter_input( INPUT_POST, 'hide_language_switchers', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
			$icl_xdomain_data              = filter_input( INPUT_POST, 'xdomain', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );

			$domainModeRefused = \WPML\Core\LanguageNegotiation::DOMAIN === (int) $icl_language_negotiation_type
				&& ! \WPML\Core\LanguageNegotiation::isDomainModeAvailable();

			if ( $domainModeRefused ) {
				$errors[] = __( 'This option is not yet available for Multisite installs', 'sitepress' );
			}

			if ( $domain_errors ) {
				$errors = array_merge( $errors, $domain_errors );
			}

			if ( $icl_language_negotiation_type && ! $domainModeRefused && ! $domain_errors ) {
				$this->sitepress->set_setting( 'language_negotiation_type', $icl_language_negotiation_type );

				$postHogCaptureEventData['language_negotiation_type'] = \WPML\Core\LanguageNegotiation::getModeAsString( $icl_language_negotiation_type );
				$response = true;

				if ( ! empty( $language_domains ) ) {
					$this->sitepress->set_setting( 'language_domains', $language_domains );

					$postHogCaptureEventData['language_domains'] = $language_domains;
				}

				if ( 1 === (int) $icl_language_negotiation_type ) {
					$urls                                   = $this->sitepress->get_setting( 'urls' );
					$root_was_forgotten                     = WPML_Root_Page_Actions::is_forgotten_root( $urls );
					unset( $urls[ \WPML\UrlHandling\RootPage\RootPageCommand::PENDING_KEY ] );
					$urls['directory_for_default_language'] = $use_directory ? true : 0;

					$postHogCaptureEventData['directory_for_default_language'] = $use_directory ? true : false;

					if ( $use_directory ) {
						$urls['show_on_root'] = $use_directory ? $show_on_root : '';

						$postHogCaptureEventData['show_on_root'] = $urls['show_on_root'];

						if ( 'html_file' === $show_on_root ) {
							$root_page_url = $root_html_file_path ? $root_html_file_path : '';
							$response      = $this->validateRootPageUrl( $root_page_url, $errors );
							if ( $response ) {
								$urls['root_html_file_path'] = $root_page_url;

								$postHogCaptureEventData['root_html_file_path'] = $urls['root_html_file_path'];
							}
						} elseif ( 'page' === $show_on_root ) {
							$root_page_id = isset( $urls['root_page'] ) ? (int) $urls['root_page'] : 0;
							$root_page    = $root_page_id > 0 ? get_post( $root_page_id ) : null;

							if ( ! WPML_Root_Page_Actions::is_usable_root_page( $root_page ) ) {
								$has_page_to_edit = $root_page && 'trash' !== $root_page->post_status;

								$errors[]         = $has_page_to_edit
									? __( 'The root page is not published. Use the "Edit root page" link above to publish it, then save.', 'sitepress' )
									: __( 'Please create a root page before saving. Use the "Create root page" link above.', 'sitepress' );
								$response         = false;
							} else {
								$urls['hide_language_switchers'] = $hide_language_switchers ? $hide_language_switchers : 0;
								$saved_root_page_id              = $root_page_id;

								$postHogCaptureEventData['root_page_created'] = true;
								$postHogCaptureEventData['root_page_id'] = $root_page_id;
								$postHogCaptureEventData['hide_language_switchers'] = (bool) $urls['hide_language_switchers'];
							}
						} elseif ( $root_was_forgotten && in_array( $show_on_root, [ '', null, false ], true ) ) {
							$urls['show_on_root'] = '';
						} else {
							$errors[] = __( 'Please choose what to show for the root URL (an HTML file or a page).', 'sitepress' );
							$response = false;
						}
					}

					if ( $response ) {
						$this->sitepress->set_setting( 'urls', $urls );
					}
				}

				$this->sitepress->set_setting( 'xdomain_data', $icl_xdomain_data );

				$postHogCaptureEventData['xdomain_data'] = $icl_xdomain_data == WPML_XDOMAIN_DATA_GET ? 'GET' :
					( $icl_xdomain_data == WPML_XDOMAIN_DATA_POST ? 'POST' : 'OFF' );

			}

			if ( $response ) {
				$this->sitepress->save_settings();

				if ( $saved_root_page_id ) {
					WPML_Root_Page_Actions::delete_page_lang( $saved_root_page_id );
				}

				( new WPML_WP_Cache( WPML_Tax_Permalink_Filters::CACHE_GROUP ) )->flush_group_cache();

				$permalinks_settings_url = get_admin_url( null, 'options-permalink.php' );
				/* translators: Link text inside the sentence "You may need to re-save the site permalinks." It opens the WordPress permalinks screen and starts in lower case because it sits inside that sentence. */
				$save_permalinks_link    = '<a href="' . $permalinks_settings_url . '">' . _x( 're-save the site permalinks', 'You may need to {re-save the site permalinks} - 2/2', 'sitepress' ) . '</a>';
				/* translators: Notice shown after a change that affects the addresses of the site. %s: a link, already wrapped in its tags, whose text is "re-save the site permalinks". */
				$save_permalinks_message = sprintf( _x( 'You may need to %s.', 'You may need to {re-save the site permalinks} - 1/2', 'sitepress' ), $save_permalinks_link );

				$this->postHogCaptureLanguageNegotiationData($postHogCaptureEventData);

				( new \WPML\Notices\RootPageNotice( wpml_get_admin_notices() ) )->clear();

				wp_send_json_success( $save_permalinks_message );
			} else {
				if ( ! $errors ) {
					/* translators: Word in front of the details of something that went wrong; a colon and the details follow it. */
					$errors[] = __( 'Error', 'sitepress' );
				}

				$this->postHogCaptureFailedLanguageNegotiationSave( $errors );

				wp_send_json_error( $errors );
			}
		}
	}


	private function postHogCaptureLanguageNegotiationData( $eventData = [] ) {
		$eventData['source'] = 'languages_page';

		\WPML\PostHog\Event\CaptureEvent::capture(
			( new EventInstanceService() )->getSetLanguageUrlFormatEvent( $eventData )
		);
	}


	private function postHogCaptureFailedLanguageNegotiationSave( $errors ) {
		\WPML\PostHog\Event\CaptureEvent::capture(
			( new EventInstanceService() )->getSetLanguageUrlFormatFailedEvent( $errors )
		);
	}

	private function validateRootPageUrl( $url, array &$errors ) {
		$result = \WPML\UrlHandling\RootPage\HtmlFile::resolve( $url );
		if ( ! $result['valid'] ) {
			$errors[] = $result['error_message'];
			return false;
		}
		return true;
	}
}
