<?php

namespace WPML\TM\ATE\Network;

use WPML\LIB\WP\Nonce;
use WPML\TM\ATE\AutoTranslate\Endpoint\EnableATE;
use WPML\TM\ATE\ClonedSites\ReconnectState;
use WPML\TM\ATE\Network\Endpoints\UseSeparateAccount;

class SubsiteAttachState {

	const OPTION = 'wpml_ate_subsite_attach';

	const ATTACHED = 'attached';
	const PENDING  = 'pending';
	const REFUSED  = 'refused';
	const SEPARATE = 'separate';

	public static function recordFailure( string $reason ) {
		$state    = self::get();
		$now      = time();
		$pending  = self::PENDING === ( $state['status'] ?? '' );
		$attempts = $pending ? (int) ( $state['attempts'] ?? 0 ) + 1 : 1;

		self::save( [
			'status'           => self::PENDING,
			'attempts'         => $attempts,
			'first_failure_at' => $pending ? (int) ( $state['first_failure_at'] ?? $now ) : $now,
			'next_attempt_at'  => $now + ReconnectState::delayFor( $attempts ),
			'last_error'       => $reason,
		] );
	}

	public static function markAttached() {
		self::save( [ 'status' => self::ATTACHED ] );
	}

	public static function markRefused() {
		self::save( [ 'status' => self::REFUSED ] );
	}

	public static function chooseSeparate() {
		self::save( [ 'status' => self::SEPARATE ] );
	}

	public static function dismissSuggestion() {
		self::save( [ 'suggestion_dismissed' => true ] + self::get() );
	}

	public static function status(): string {
		return (string) ( self::get()['status'] ?? '' );
	}

	public static function isPending(): bool {
		return self::PENDING === self::status();
	}

	public static function isDue(): bool {
		return self::isPending() && time() >= (int) ( self::get()['next_attempt_at'] ?? 0 );
	}

	public static function canUseSeparateAccount(): bool {
		$state = self::get();

		return self::isPending()
			&& time() - (int) ( $state['first_failure_at'] ?? time() ) >= ReconnectState::ESCALATE_AFTER_SECONDS;
	}

	public static function suggestsConnecting(): bool {
		$state = self::get();

		return empty( $state['suggestion_dismissed'] )
			&& ! in_array( $state['status'] ?? '', [ self::ATTACHED, self::PENDING, self::SEPARATE ], true );
	}

	public static function clientState(): array {
		return [
			'canUseSeparateAccount' => self::canUseSeparateAccount(),
			'endpoints'             => [
				'retry'              => self::endpoint( EnableATE::class ),
				'useSeparateAccount' => self::endpoint( UseSeparateAccount::class ),
			],
		];
	}

	private static function endpoint( string $class ): array {
		return [
			'endpoint' => $class,
			'nonce'    => Nonce::create( $class ),
		];
	}

	private static function get(): array {
		$state = get_option( self::OPTION, [] );

		return is_array( $state ) ? $state : [];
	}

	private static function save( array $state ) {
		update_option( self::OPTION, $state, true );
	}
}
