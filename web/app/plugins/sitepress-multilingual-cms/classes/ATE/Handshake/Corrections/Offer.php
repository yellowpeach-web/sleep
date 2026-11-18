<?php

namespace WPML\TM\ATE\Handshake\Corrections;

use WPML\TM\ATE\Handshake\Clock;
use WPML\TM\ATE\Handshake\Log;
use WPML\TM\ATE\Handshake\Notice;
use WPML\TM\ATE\Handshake\Plan;
use WPML\TM\ATE\Handshake\State;

class Offer {

	const READ_EVERY = 3600;

	private $client;

	private $local;

	public function __construct( ?Client $client = null, ?LocalRows $local = null ) {
		$this->client = $client ?: new Client();
		$this->local  = $local ?: new LocalRows();
	}

	public function read() {
		$answer = $this->client->offered();

		if ( ! $answer['ok'] ) {
			State::update( [ 'corrections_read_at' => Clock::now() ] );
			Log::write( 'corrections_read', [ 'ok' => false, 'error' => $answer['error'] ] );

			return $answer;
		}

		$rows    = $this->local->enrich( $answer['rows'] );
		$changes = self::summaryChanges( $rows );

		if ( (int) $changes['corrections_count'] !== (int) State::getKey( 'corrections_count' )
			|| (string) $changes['corrections_batch_id'] !== (string) State::getKey( 'corrections_batch_id' ) ) {
			Log::write(
				'corrections_read',
				[
					'ok'       => true,
					'count'    => (int) $changes['corrections_count'],
					'pages'    => (int) $changes['corrections_pages'],
					'batch_id' => (string) $changes['corrections_batch_id'],
				]
			);
		}

		State::update( $changes );

		return [
			'ok'    => true,
			'rows'  => $rows,
			'error' => '',
		];
	}

	public static function summaryChanges( array $rows ) {
		$pages  = [];
		$newest = null;

		foreach ( $rows as $row ) {
			$pages[ LocalRows::pageKey( $row ) ] = true;

			if ( null === $newest || (int) $row['corrected_at'] > (int) $newest['corrected_at'] ) {
				$newest = $row;
			}
		}

		return [
			'corrections_count'    => count( $rows ),
			'corrections_pages'    => count( $pages ),
			'corrections_batch_id' => $newest ? (string) $newest['batch_id'] : '',
			'corrections_reason'   => $newest ? (string) $newest['reason'] : '',
			'corrections_read_at'  => Clock::now(),
		];
	}

	public static function readAgainChanges() {
		return [ 'corrections_read_at' => 0 ];
	}

	public static function count() {
		return max( 0, (int) State::getKey( 'corrections_count' ) );
	}

	public static function pages() {
		return max( 0, (int) State::getKey( 'corrections_pages' ) );
	}

	public static function isDue() {
		$readAt = (int) State::getKey( 'corrections_read_at' );

		return $readAt < 1 || $readAt <= Clock::now() - self::READ_EVERY;
	}

	public static function onAdminLoad( $offer = null, $registered = null ) {
		if ( wp_doing_ajax() || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		$offer      = $offer instanceof self ? $offer : null;
		$registered = is_callable( $registered ) ? $registered : [ self::class, 'siteIsRegistered' ];

		if ( ! self::isDue() || ! Notice::userMayAct() || ! call_user_func( $registered ) ) {
			return;
		}

		State::update( [ 'corrections_read_at' => Clock::now() ] );

		if ( Plan::phpFinishesEarly() ) {
			add_action(
				'shutdown',
				function () use ( $offer ) {
					if ( function_exists( 'fastcgi_finish_request' ) ) {
						fastcgi_finish_request();
					}

					( $offer ?: new self() )->read();
				},
				\WPML\TM\ATE\Handshake\Runner::POST_RESPONSE_PRIORITY
			);

			return;
		}

		( $offer ?: new self() )->read();
	}

	public static function siteIsRegistered() {
		try {
			return (bool) \WPML\Container\make( \WPML_TM_ATE_Authentication::class )->has_keys();
		} catch ( \Throwable $e ) {
			return false;
		}
	}
}
