<?php

namespace WPML\TranslationPolicy\Ptc;

use WPML\Core\Component\ATE\Application\Query\AccountInterface;
use WPML\Request\Adapter\Rest;
use WPML\Request\Policy\Authenticity;
use WPML\Request\Policy\Policy;
use WPML\UIPage;

class PtcConfirm {

	const REST_NAMESPACE = 'wpml/v1';

	const ROUTE = '/translation-policy/ptc-confirm';

	const USER_META = 'wpml_translation_policy_ptc_confirmed';

	const CREDITS_PER_WORD = 4;

	private $isAccountUsable;

	private $credits;

	public function __construct( callable $isAccountUsable, callable $credits ) {
		$this->isAccountUsable = $isAccountUsable;
		$this->credits         = $credits;
	}

	public static function create(): self {
		return new self( [ \WPML_TM_ATE_Status::class, 'is_account_usable' ], [ self::class, 'readCredits' ] );
	}

	public static function readCredits() {
		global $wpml_dic;

		try {
			$account = $wpml_dic->make( AccountInterface::class );

			return $account->getAccountBalances( true )->getAccountBalance();
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	public function registerRoute() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'state' ],
				'permission_callback' => Rest::permission(
					Policy::authorize( [ self::class, 'mayRead' ], Authenticity::restTransport(), 'a user who may edit posts' ),
					self::REST_NAMESPACE . self::ROUTE
				),
			]
		);
	}

	public static function mayRead(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function state(): array {
		$isAccountUsable = (bool) call_user_func( $this->isAccountUsable );
		$credits         = $isAccountUsable ? call_user_func( $this->credits ) : null;

		return [
			'account_usable' => $isAccountUsable,
			'words_left'     => is_int( $credits ) ? Words::wordsLeft( self::words( $credits ) ) : '',
			'remedy_url'     => admin_url( UIPage::getTMDashboard() ),
		];
	}

	public static function words( int $credits ): int {
		return (int) floor( max( $credits, 0 ) / self::CREDITS_PER_WORD );
	}

	public static function isConfirmedBy( int $userId ): bool {
		return $userId > 0 && (bool) get_user_meta( $userId, self::USER_META, true );
	}

	public static function markConfirmed( int $userId ) {
		if ( $userId > 0 ) {
			update_user_meta( $userId, self::USER_META, 1 );
		}
	}

	public static function dialogWords(): array {
		return [
			'title'               => Words::title(),
			'send'                => Words::send(),
			'update'              => Words::update(),
			'words'               => Words::usesWords(),
			'translateEverything' => Words::translateEverything(),
			'use'                 => Words::usePtc(),
			'cancel'              => Words::cancel(),
			'noAccount'           => Words::noAccount(),
			'turnOn'              => Words::turnOn(),
			'loading'             => Words::loading(),
		];
	}
}
