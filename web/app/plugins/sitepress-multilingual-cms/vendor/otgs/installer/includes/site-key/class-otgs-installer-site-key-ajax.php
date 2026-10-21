<?php

use OTGS\Installer\Subscription\SubscriptionManagerFactory;
use OTGS\Installer\Api\Exception\InvalidSubscription;

class OTGS_Installer_Site_Key_Ajax {

	private $logger;
	private $repositories;
	private $subscription_factory;
	private $subscriptionManagerFactory;
	private $installer;

	private $removeService;

	public function __construct(
		OTGS_Installer_Logger $logger,
		OTGS_Installer_Repositories $repositories,
		OTGS_Installer_Subscription_Factory $subscription_factory,
		SubscriptionManagerFactory $subscriptionManagerFactory,
		OTGS_Installer_Site_Key_Remove_Service $removeService,
		$installer = null
	) {
		$this->logger                     = $logger;
		$this->repositories               = $repositories;
		$this->subscription_factory       = $subscription_factory;
		$this->subscriptionManagerFactory = $subscriptionManagerFactory;
		$this->removeService              = $removeService;
		$this->installer                  = $installer;
	}

	public function add_hooks() {
		add_action( 'wp_ajax_save_site_key', array( $this, 'save' ) );
		add_action( 'wp_ajax_remove_site_key', array( $this, 'remove' ) );
		add_action( 'wp_ajax_update_site_key', array( $this, 'update' ) );
		add_action( 'wp_ajax_find_account', [ $this, 'find' ] );
	}

	public function save() {
		if ( ! $this->current_user_can_manage_site_key() ) {
			$this->send_not_allowed();
			return;
		}

		$repositoryId = isset( $_POST['repository_id'] ) && $_POST['repository_id'] ? sanitize_text_field( $_POST['repository_id'] ) : null;
		$nonce      = isset( $_POST['nonce'] ) && $_POST['nonce'] ? sanitize_text_field( $_POST['nonce'] ) : null;
		$site_key   = isset( $_POST[ 'site_key_' . $repositoryId ] ) && $_POST[ 'site_key_' . $repositoryId ] ? sanitize_text_field( $_POST[ 'site_key_' . $repositoryId ] ) : '';
		$site_key   = preg_replace( '/[^A-Za-z0-9]/', '', $site_key );
		$error      = '';

		if ( ! $site_key ) {
			wp_send_json_success( [ 'error' => esc_html__( 'Empty site key!', 'installer' ) ] );
			return;
		}
		if ( ! $repositoryId || ! $nonce || ! wp_verify_nonce( $nonce, 'save_site_key_' . $repositoryId ) ) {
			wp_send_json_success( [ 'error' => esc_html__( 'Invalid request!', 'installer' ) ] );
			return;
		}
		$repository = $this->repositories->get( $repositoryId );
		try {
			list ($subscription, $site_key_data) = $this->getSubscriptionData( $repositoryId, $repository, WP_Installer::SITE_KEY_VALIDATION_SOURCE_REGISTRATION, $site_key );

			if ( $subscription ) {
				$subscription_data = $this->subscription_factory->create( array(
					'data'          => $subscription,
					'key'           => $site_key,
					'key_type'      => isset($site_key_data['type'])
						? (int) $site_key_data['type'] : OTGS_Installer_Subscription::SITE_KEY_TYPE_PRODUCTION,
					'site_url'      => get_site_url(),
					'registered_by' => get_current_user_id()
				) );

				$repository->set_subscription( $subscription_data );
				$this->repositories->save_subscription( $repository );
				$this->repositories->refresh();
				$this->clean_plugins_update_cache();
				do_action( 'otgs_installer_site_key_update', $repository->get_id() );

				do_action('check_posthog_should_record');
			} else {
				$error = __( 'Invalid site key for the current site.', 'installer' ) . '<br /><div class="installer-footnote">' . __( 'Please note that the site key is case sensitive.', 'installer' ) . '</div>';
			}
		} catch ( Exception $e ) {
			$error           = $this->get_error_message( $e, $repository );
		}

		$response = array( 'error' => $error );

		if ( $this->logger->get_api_log() ) {
			$response['debug'] = $this->logger->get_api_log();
		}

		wp_send_json_success( $response );
	}

	public function remove() {
		if ( ! $this->current_user_can_manage_site_key() ) {
			$this->send_not_allowed();
			return;
		}

		$repository   = isset( $_POST['repository_id'] ) ? sanitize_text_field( $_POST['repository_id'] ) : null;
		$nonce        = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : null;
		$nonce_action = 'remove_site_key_' . $repository;

		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_success();
			return;
		}

		if ( WP_Installer::site_key_belongs_to_network( $repository ) ) {
			wp_send_json_error( array( 'error' => WP_Installer::network_site_key_notice() ), 403 );
			return;
		}

		$this->removeService->remove( $repository );

		$response = array();
		if ( OTGS_Installer_Site_Key_Remove_Service::pending( $repository ) ) {
			$repository_data              = $this->repositories->get( $repository );
			$response['unregister_pending'] = true;
			$response['message']            = OTGS_Installer_Site_Key_Remove_Service::pending_sentence(
				$repository_data->get_product_name(),
				self::host_of( $repository_data->get_api_url() )
			);
		}

		wp_send_json_success( $response );
	}

	public function update() {
		if ( ! $this->current_user_can_refresh_site_key() ) {
			$this->send_not_allowed();
			return;
		}

		$error          = '';
		$invalidSiteKey = false;
		$nonce          = isset( $_POST['nonce'] ) ? $_POST['nonce'] : null;
		$repositoryId   = isset( $_POST['repository_id'] ) ? sanitize_text_field( $_POST['repository_id'] ) : null;

		if ( $nonce && $repositoryId && wp_verify_nonce( $nonce, 'update_site_key_' . $repositoryId ) ) {
			$repository = $this->repositories->get( $repositoryId );
			$site_key        = $repository->get_subscription()->get_site_key();

			if ( $site_key ) {
				try {
					list( $subscription, $site_key_data ) = $this->getSubscriptionData( $repositoryId, $repository, WP_Installer::SITE_KEY_VALIDATION_SOURCE_REVALIDATION, $site_key );

					if ( $subscription ) {
						$subscription_data = $this->subscription_factory->create( array(
							'data'          => $subscription,
							'key'           => $site_key,
							'key_type'      => isset($site_key_data['type'])
								? (int) $site_key_data['type'] : OTGS_Installer_Subscription::SITE_KEY_TYPE_PRODUCTION,
							'site_url'      => get_site_url(),
							'registered_by' => get_current_user_id(),
						) );
						$repository->set_subscription( $subscription_data );

						do_action('check_posthog_should_record');
					} else {
						do_action( 'otgs_installer_before_site_key_removal', $repository->get_id() );
						$repository->set_subscription( null );
						do_action( 'otgs_installer_site_key_update', $repository->get_id() );
						$error = __( 'Invalid site key for the current site. If the error persists, try to un-register first and then register again with the same site key.', 'installer' );
					}

					$this->repositories->save_subscription( $repository );
					$messages = $this->repositories->refresh( true );

					if ( is_array( $messages ) ) {
						$error .= implode( '', $messages );
					}


					$this->clean_plugins_update_cache();
				} catch ( Exception $e ) {
					if ( $e->getPrevious() instanceof InvalidSubscription && $this->installer ) {
						$this->installer->mark_site_key_as_invalid( $repositoryId );
						$this->installer->save_settings();
						$error          = __( 'Your site key is no longer valid. Please register a new key.', 'installer' );
						$invalidSiteKey = true;
					} else {
						$error = $this->get_error_message( $e, $repository );
					}
				}
			}

		}

		$response = array( 'error' => $error );
		if ( $invalidSiteKey ) {
			$response['invalid_site_key'] = true;
		}

		wp_send_json_success( $response );
	}

	public function find() {
		if ( ! $this->current_user_can_manage_site_key() ) {
			$this->send_not_allowed();
			return;
		}

		$repository = isset( $_POST['repository_id'] ) ? sanitize_text_field( $_POST['repository_id'] ) : null;
		$nonce      = isset( $_POST['nonce'] ) ? $_POST['nonce'] : null;
		$email      = isset( $_POST['email'] ) ? sanitize_text_field( $_POST['email'] ) : null;
		$success    = false;

		if ( $nonce && $repository && $email && wp_verify_nonce( $nonce, 'find_account_' . $repository ) ) {
			$repository_data = $this->repositories->get( $repository );
			$siteKey         = $repository_data->get_subscription()->get_site_key();

			$args['body'] = [
				'action'   => 'user_email_exists',
				'umail'    => md5( $email . $siteKey ),
				'site_key' => $siteKey,
				'site_url' => get_site_url()
			];

			$response = wp_remote_post( $repository_data->get_api_url(), $args );
			if ( $response ) {
				$body    = json_decode( wp_remote_retrieve_body( $response ) );
				$success = isset( $body->success ) ? 'Success' === $body->success : false;
			}
		}

		wp_send_json_success( [ 'found' => $success ] );

	}


	private function current_user_can_manage_site_key() {
		return is_multisite() ? is_super_admin() : current_user_can( 'manage_options' );
	}

	/**
	 * Refreshing the key (`update`) changes nothing a caller could not see: it
	 * re-reads the subscription the site already holds. WPML's Support screen
	 * offers it as "Refresh license" to whoever may open that screen, which is
	 * `wpml_manage_support` alone - so a role given only that capability saw
	 * the button and was refused here (WPML TS-10b). The capability is WPML's:
	 * on a site without WPML nobody holds it and nothing changes. Registering,
	 * removing and finding the key stay with `manage_options`. Multisite stays
	 * with super admins for the same reason as above.
	 *
	 * @return bool
	 */
	private function current_user_can_refresh_site_key() {
		if ( is_multisite() ) {
			return is_super_admin();
		}

		return current_user_can( 'manage_options' ) || current_user_can( 'wpml_manage_support' );
	}

	private function send_not_allowed() {
		wp_send_json_error(
			array( 'error' => __( 'You are not allowed to manage the site key.', 'installer' ) ),
			403
		);
	}

	private function get_error_message( Exception $e, OTGS_Installer_Repository $repository_data ) {
		if ( $e instanceof OTGS_Installer_Fetch_Subscription_Postponed_Exception ) {
			return OTGS_Installer_Fetch_Subscription_Postponed_Exception::user_message();
		}

		$error = $e->getMessage();
		if ( OTGS_Installer_Fetch_Subscription_Exception::is_service_unavailable( $e ) ) {
			$matches = array( '', self::host_of( $repository_data->get_api_url() ) );
		}
		if ( ! empty( $matches ) || preg_match( '#Could not resolve host: (.*)#', $error, $matches ) || preg_match( '#Couldn\'t resolve host \'(.*)\'#', $error, $matches ) ) {
			$error = sprintf( __( "%s cannot access %s to register. Try again to see if it's a temporary problem. If the problem continues, make sure that this site has access to the Internet. You can still use the plugin without registration, but you will not receive automated updates.", 'installer' ),
				'<strong><i>' . $repository_data->get_product_name() . '</i></strong>',
				'<strong><i>' . $matches[1] . '</i></strong>'
			);
		}

		return $error;
	}

	private function clean_plugins_update_cache() {
		do_action( 'otgs_installer_clean_plugins_update_cache' );
	}

	public static function host_of( $api_url ) {
		$host = wp_parse_url( (string) $api_url, PHP_URL_HOST );

		return $host ? $host : (string) $api_url;
	}

	private function getSubscriptionData( $repositoryId, OTGS_Installer_Repository $repository, $source, $site_key ) {
		$subscriptionManager = $this->subscriptionManagerFactory->create( $repositoryId, $repository->get_api_url() );
		// Registering and "Refresh license" are a person asking: once a minute inside a wait (wpmldev-10865).
		list ( $subscription, $site_key_data ) = $subscriptionManager->fetch( $site_key, $source, true );

		return array( $subscription, $site_key_data );
	}
}
