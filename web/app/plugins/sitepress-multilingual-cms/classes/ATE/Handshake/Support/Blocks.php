<?php

namespace WPML\TM\ATE\Handshake\Support;

class Blocks {

	const TRANSIENT = 'wpml_ate_delivery_box_blocks';
	const TTL       = 60;

	const OK          = 'ok';
	const UNREACHABLE = 'unreachable';

	const NONE      = 'none';
	const THIS_SITE = 'this_site';
	const ALL       = 'all';

	private $reader;

	public function __construct( $reader = null ) {
		$this->reader = $reader ?: function () {
			return \WPML\Container\make( \WPML_TM_AMS_API::class )->getNotificationBlocks();
		};
	}

	public function read( $fresh = false ) {
		if ( ! $fresh ) {
			$kept = get_transient( self::TRANSIENT );

			if ( is_array( $kept ) && isset( $kept['status'] ) ) {
				return $kept;
			}
		}

		try {
			$answer = call_user_func( $this->reader );
		} catch ( \Throwable $e ) {
			$answer = null;
		}

		$view = self::fromAnswer( $answer );

		set_transient( self::TRANSIENT, $view, self::TTL );

		return $view;
	}

	public static function fromAnswer( $answer ) {
		if ( ! is_array( $answer ) || ! isset( $answer['recipients'] ) || ! is_array( $answer['recipients'] ) ) {
			return [
				'status'      => self::UNREACHABLE,
				'recipients'  => [],
				'all_blocked' => false,
			];
		}

		$rows = [];

		foreach ( $answer['recipients'] as $row ) {
			if ( ! is_array( $row ) || empty( $row['address'] ) ) {
				continue;
			}

			$state = isset( $row['notifications_blocked'] ) ? (string) $row['notifications_blocked'] : self::NONE;

			$rows[] = [
				'address'               => (string) $row['address'],
				'notifications_blocked' => in_array( $state, [ self::THIS_SITE, self::ALL ], true ) ? $state : self::NONE,
				'blocked_since'         => isset( $row['blocked_since'] ) ? (string) $row['blocked_since'] : '',
				'resume_url'            => isset( $row['resume_url'] ) ? (string) $row['resume_url'] : '',
			];
		}

		$blocked = array_filter(
			$rows,
			function ( $row ) {
				return self::NONE !== $row['notifications_blocked'];
			}
		);

		return [
			'status'      => self::OK,
			'recipients'  => $rows,
			'all_blocked' => isset( $answer['all_blocked'] ) ? (bool) $answer['all_blocked'] : ( $rows && count( $blocked ) === count( $rows ) ),
		];
	}

	public static function forget() {
		delete_transient( self::TRANSIENT );
	}
}
