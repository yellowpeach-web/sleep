<?php

namespace WPML\TM\ATE\Handshake;

use WPML\TM\ATE\API\SpendCapState;
use WPML\TM\ATE\PullDelivery\NoCreditResume;
use WPML\TM\ATE\PullDelivery\ParkedJobs;
use WPML\TM\ATE\PullDelivery\State as PullState;
use function WPML\Container\make;

class CreditResume {

	private $resumeFactory;

	private $parkedJobs;

	public function __construct( $resumeFactory = null, ?ParkedJobs $parkedJobs = null ) {
		$this->resumeFactory = $resumeFactory ?: function () {
			return make( NoCreditResume::class );
		};
		$this->parkedJobs    = $parkedJobs;
	}

	public function run( $wake = false ) {
		$state = PullState::get();

		if ( ! self::needsAsk( $state ) ) {
			return false;
		}

		try {
			$state = $this->parkedJobs()->prune( $state );

			$resume = call_user_func( $this->resumeFactory );
			$resume->maybeResume( $state, (bool) $wake );
		} catch ( \Throwable $e ) {
			Log::write( 'credit_resume', [ 'error' => get_class( $e ) ] );
		}

		return true;
	}

	public static function afterAdminLoad() {
		return ( new self() )->askAfterAdminLoad();
	}

	public function askAfterAdminLoad() {
		if ( ! self::needsAsk( PullState::get() ) ) {
			return false;
		}

		if ( Plan::phpFinishesEarly() ) {
			add_action(
				'shutdown',
				function () {
					if ( function_exists( 'fastcgi_finish_request' ) ) {
						if ( function_exists( 'ignore_user_abort' ) ) {
							ignore_user_abort( true );
						}
						fastcgi_finish_request();
					}

					$this->run( true );
				},
				Runner::POST_RESPONSE_PRIORITY
			);

			return true;
		}

		return self::onWpmlScreen() && $this->run( true );
	}

	private static function onWpmlScreen() {
		global $plugin_page;

		$page = is_string( $plugin_page ) ? $plugin_page : '';

		return 0 === strpos( $page, 'tm/menu/' ) || 0 === strpos( $page, 'sitepress-multilingual-cms/menu/' );
	}

	public static function needsAsk( array $state ) {
		return self::hasParkedSet( $state ) || SpendCapState::isReached();
	}

	public static function hasParkedSet( array $state ) {
		return isset( $state['insufficient_balance_ate_job_ids'] )
			&& (bool) array_filter( array_map( 'intval', (array) $state['insufficient_balance_ate_job_ids'] ) );
	}

	private function parkedJobs() {
		if ( ! $this->parkedJobs ) {
			$this->parkedJobs = new ParkedJobs();
		}

		return $this->parkedJobs;
	}
}
