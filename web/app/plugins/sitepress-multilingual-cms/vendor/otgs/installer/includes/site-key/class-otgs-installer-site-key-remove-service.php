<?php

class  OTGS_Installer_Site_Key_Remove_Service {

	const RETRY_CRON_HOOK     = 'otgs_installer_site_key_remove_retry';
	const RETRY_CRON_INTERVAL = 10 * MINUTE_IN_SECONDS;

	const RETRY_WINDOW = DAY_IN_SECONDS;

	const PENDING_OPTION = 'otgs_installer_site_key_removal_pending';

	private $repositories;

	private $removeApi;

	public function __construct(
		OTGS_Installer_Repositories $repositories,
		OTGS_Installer_Site_Key_Remove_Request $removeApi
	) {
		$this->repositories = $repositories;
		$this->removeApi    = $removeApi;

		add_action( self::RETRY_CRON_HOOK, [ $this, 'cron_retry_handler' ], 10, 2 );

		add_action( 'otgs_installer_site_key_update', [ $this, 'site_key_registered' ] );
	}


	public function remove( string $repository = 'wpml', bool $notifyExternalApi = true ) {
		if ( $notifyExternalApi ) {
			do_action( 'otgs_installer_before_site_key_removal', $repository );
		}
		$repository = $this->repositories->get( $repository );

		if ( $notifyExternalApi ) {
			$site_key = $repository->get_subscription()->get_site_key();
			list( $url, $params ) = $this->removeApi->build_params( $repository, $site_key );
		}

		$repository->set_subscription( null );
		$this->repositories->save_subscription( $repository );

		if ( $notifyExternalApi ) {
			$response = $this->removeApi->run( $url, $params );

			if ( self::is_confirmed( $response ) ) {
				self::clear_pending( $repository->get_id() );
			} else {
				self::remember_pending( $repository->get_id(), $site_key, self::describe_answer( $response ) );
				$this->schedule_retry( $repository->get_id(), $site_key );
			}
		}

		do_action( 'otgs_installer_clean_plugins_update_cache' );
		do_action( 'otgs_installer_site_key_update', $repository->get_id() );

		$this->repositories->refresh();
	}

	public function site_key_registered( $repository_id ) {
		$pending = self::pending( $repository_id );

		if ( ! $pending || ! $this->has_subscription( $repository_id ) ) {
			return;
		}

		wp_clear_scheduled_hook( self::RETRY_CRON_HOOK, [ $repository_id, $pending['site_key'] ] );
		self::clear_pending( $repository_id );
	}

	private function has_subscription( $repository_id ) {
		$repository = $this->repositories->get( $repository_id );

		if ( ! $repository ) {
			return false;
		}

		$subscription = $repository->get_subscription();

		return (bool) ( $subscription && $subscription->get_site_key() );
	}

	public static function is_confirmed( $response ) {
		if ( is_wp_error( $response ) || ! $response ) {
			return false;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status > 299 ) {
			return false;
		}

		$body = maybe_unserialize( wp_remote_retrieve_body( $response ) );

		if ( is_object( $body ) ) {
			return ! empty( $body->success );
		}

		if ( is_array( $body ) ) {
			return ! empty( $body['success'] );
		}

		return false;
	}

	public static function describe_answer( $response ) {
		if ( is_wp_error( $response ) ) {
			return 'the request did not get an answer: ' . $response->get_error_message();
		}

		if ( ! $response ) {
			return 'the request did not get an answer';
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status > 299 ) {
			return sprintf( 'wpml.org answered HTTP %d', $status );
		}

		$body = maybe_unserialize( wp_remote_retrieve_body( $response ) );
		if ( is_object( $body ) && ! empty( $body->error ) ) {
			return 'wpml.org refused the request: ' . (string) $body->error;
		}

		return 'wpml.org answered, but not with a confirmed release';
	}

	public static function pending( $repository_id ) {
		$pending = get_option( self::PENDING_OPTION, [] );
		$entry   = is_array( $pending ) && isset( $pending[ $repository_id ] ) ? $pending[ $repository_id ] : null;

		return is_array( $entry ) && ! empty( $entry['site_key'] ) ? $entry : null;
	}

	public static function pending_sentence( $product_name, $api_host ) {
		return sprintf(
		// translators: %1$s Product name (ex. WPML) %2$s host name (ex. wpml.org)
			__( 'This site is unregistered, but %2$s has not confirmed it yet, so your account may still count this site against your limit. %1$s keeps trying in the background. Reload this page in a few minutes to see whether it went through.', 'installer' ),
			$product_name,
			$api_host
		);
	}

	private static function remember_pending( $repository_id, $site_key, $reason ) {
		$pending = get_option( self::PENDING_OPTION, [] );
		$pending = is_array( $pending ) ? $pending : [];
		$current = self::pending( $repository_id );

		$same  = $current && (string) $current['site_key'] === (string) $site_key;
		$since = $same ? (int) $current['since'] : time();
		$count = $same ? (int) $current['attempts'] + 1 : 1;

		$pending[ $repository_id ] = [
			'site_key' => (string) $site_key,
			'since'    => $since,
			'attempts' => $count,
			'reason'   => (string) $reason,
		];

		update_option( self::PENDING_OPTION, $pending, false );
	}

	private static function clear_pending( $repository_id ) {
		$pending = get_option( self::PENDING_OPTION, [] );
		if ( ! is_array( $pending ) || ! isset( $pending[ $repository_id ] ) ) {
			return;
		}

		unset( $pending[ $repository_id ] );
		update_option( self::PENDING_OPTION, $pending, false );
	}

	private function schedule_retry( $repository_id, $site_key ) {
		$pending = self::pending( $repository_id );
		if ( $pending && time() - (int) $pending['since'] > self::RETRY_WINDOW ) {
			return;
		}

		if ( ! wp_next_scheduled( self::RETRY_CRON_HOOK, [ $repository_id, $site_key ] ) ) {
			wp_schedule_single_event( time() + self::RETRY_CRON_INTERVAL, self::RETRY_CRON_HOOK, [ $repository_id, $site_key ] );
		}
	}

	public function cron_retry_handler( $repository, $site_key ) {
		if ( is_object( $repository ) ) {
			$repository_id     = $repository->get_id();
			$repository_object = $repository;
		} else {
			$repository_id     = (string) $repository;
			$repository_object = $this->repositories->get( $repository_id );
		}

		if ( ! $repository_object ) {
			return;
		}

		if ( $this->has_subscription( $repository_id ) ) {
			self::clear_pending( $repository_id );

			return;
		}

		list( $url, $params ) = $this->removeApi->build_params( $repository_object, $site_key );
		$response = $this->removeApi->run( $url, $params );

		if ( self::is_confirmed( $response ) ) {
			self::clear_pending( $repository_id );

			return;
		}

		self::remember_pending( $repository_id, $site_key, self::describe_answer( $response ) );
		$this->schedule_retry( $repository_id, $site_key );
	}

}
