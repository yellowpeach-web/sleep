<?php

namespace WPML\TM\ATE\Handshake\Support;

use WPML\Core\Component\ATE\Domain\Credits\DebtorBlock;
use WPML\TM\ATE\API\ClientRestrictionState;
use WPML\TM\ATE\API\SpendCapState;
use WPML\TM\ATE\AmsHostContract;
use WPML\TM\ATE\Handshake\State;
use WPML\TM\ATE\PullDelivery\State as PullState;
use WPML\WP\OptionManager;

class Stalled {

	const NONE             = 'none';
	const CREDITS          = 'credits';
	const PAYMENT_PENDING  = 'payment_pending';
	const PAYMENT_DECLINED = 'payment_declined';
	const SPEND_CAP        = 'spend_cap';
	const CLIENT_BLOCKED   = 'client_blocked';
	const PAYMENT_METHOD   = 'payment_method';

	const DOOR_BUY    = 'buy';
	const DOOR_ASSIGN = 'assign';

	const DOOR_ADMIN   = 'admin';
	const DOOR_ACCOUNT = 'account';

	const ATTRIBUTE = [
		self::CREDITS          => 'credits',
		self::PAYMENT_PENDING  => 'payment-pending',
		self::PAYMENT_DECLINED => 'payment-declined',
		self::SPEND_CAP        => 'spend-cap',
		self::CLIENT_BLOCKED   => 'blocked',
		self::PAYMENT_METHOD   => 'payment-method',
	];

	private $sources;

	public function __construct( array $sources = [] ) {
		$this->sources = array_merge( self::defaultSources(), $sources );
	}

	public function read() {
		$none  = [ 'cause' => self::NONE, 'count' => 0, 'since' => 0 ];
		$pull  = array_merge( PullState::defaults(), (array) $this->call( 'pull' ) );
		$short = self::ids( $pull['insufficient_balance_job_ids'], $pull['insufficient_balance_ate_job_ids'] );
		$held  = self::ids( $pull['payment_pending_job_ids'], $pull['payment_pending_ate_job_ids'] );
		$in    = max( 0, (int) $this->call( 'inFlight' ) );

		if ( $in < 1 && ! $short && ! $held ) {
			return $none;
		}

		if ( $this->call( 'restricted' ) ) {
			return [
				'cause' => self::CLIENT_BLOCKED,
				'count' => max( $in, $short + $held ),
				'since' => (int) $this->call( 'sentSince' ),
			];
		}

		$credits     = (array) $this->call( 'credits' );
		$shortSince  = $short ? (int) $pull['insufficient_balance_since'] : 0;
		$heldSince   = $held ? (int) $pull['payment_pending_since'] : 0;
		$payAsYouGo  = ! empty( $credits['pay_as_you_go'] );
		$bothSince   = min( array_filter( [ $shortSince, $heldSince, PHP_INT_MAX ] ) );
		$bothSince   = PHP_INT_MAX === $bothSince ? 0 : $bothSince;

		if ( $short && '' !== DebtorBlock::fromCredits( $credits ) ) {
			return [ 'cause' => self::CLIENT_BLOCKED, 'count' => $short + $held, 'since' => $bothSince ];
		}

		$unpaidCharge = (int) ( isset( $credits['subscription_debt'] ) ? $credits['subscription_debt'] : 0 ) > 0;
		if ( ( $short || $held ) && ( $unpaidCharge || ( $payAsYouGo && ! empty( $credits['payment_required'] ) ) ) ) {
			return [ 'cause' => self::PAYMENT_DECLINED, 'count' => $short + $held, 'since' => $bothSince ];
		}

		if ( $held && $credits && ! $payAsYouGo && empty( $credits['active_subscription'] ) ) {
			return [ 'cause' => self::PAYMENT_METHOD, 'count' => $held, 'since' => $heldSince, 'door' => $this->wordsOnAccount() ? self::DOOR_ACCOUNT : self::DOOR_ADMIN ];
		}

		if ( $held ) {
			return [ 'cause' => self::PAYMENT_PENDING, 'count' => $held, 'since' => $heldSince ];
		}

		if ( ! $short ) {
			return $none;
		}

		if ( $payAsYouGo ) {
			return $this->call( 'spendCapHolds' )
				? [ 'cause' => self::SPEND_CAP, 'count' => $short, 'since' => $shortSince, 'door' => self::DOOR_ADMIN ]
				: $none;
		}

		return [ 'cause' => self::CREDITS, 'count' => $short, 'since' => $shortSince, 'door' => $this->wordsOnAccount() ? self::DOOR_ASSIGN : self::DOOR_BUY ];
	}

	private function wordsOnAccount() {
		$balances = $this->call( 'balances' );
		$credits  = is_array( $balances ) && isset( $balances['account_balance'] ) ? (int) $balances['account_balance'] : 0;

		return (int) floor( $credits / 4 ) > 0;
	}

	public static function view( array $facts, $formatTime = null, $actionUrl = null ) {
		$cause = isset( $facts['cause'] ) && isset( self::ATTRIBUTE[ $facts['cause'] ] ) ? $facts['cause'] : self::NONE;

		if ( self::NONE === $cause ) {
			return [
				'cause'       => self::NONE,
				'attribute'   => '',
				'count'       => 0,
				'since'       => 0,
				'line'        => '',
				'adminBar'    => '',
				'label'       => '',
				'hold'        => '',
				'actionLabel' => '',
				'actionUrl'   => '',
				'door'        => '',
				'assignLabel' => '',
				'assignUrl'   => '',
				'reasonAssign' => '',
			];
		}

		$formatTime = $formatTime ?: [ Box::class, 'siteTime' ];
		$actionUrl  = $actionUrl ?: [ self::class, 'actionUrl' ];
		$count      = max( 0, isset( $facts['count'] ) ? (int) $facts['count'] : 0 );
		$since      = max( 0, isset( $facts['since'] ) ? (int) $facts['since'] : 0 );
		$hold       = $count > 0 ? Texts::stalledHold( $count, $since > 0 ? (string) call_user_func( $formatTime, $since ) : '' ) : '';
		$door       = self::door( $cause, isset( $facts['door'] ) ? (string) $facts['door'] : '' );
		$label      = self::DOOR_ASSIGN === $door ? Texts::stalledAssign() : Texts::stalledAction( $cause );
		$assignUrl  = self::CREDITS === $cause ? (string) call_user_func( $actionUrl, $cause, self::DOOR_ASSIGN ) : '';

		return [
			'cause'       => $cause,
			'attribute'   => self::ATTRIBUTE[ $cause ],
			'count'       => $count,
			'since'       => $since,
			'line'        => trim( ( self::DOOR_ASSIGN === $door ? Texts::stalledReasonUnassigned() : Texts::stalledReason( $cause ) ) . ' ' . $hold ),
			'reasonAssign' => self::CREDITS === $cause ? Texts::stalledReasonUnassigned() : '',
			'adminBar'    => Texts::stalledAdminBar( $cause ),
			'label'       => Texts::stalledLabel( $cause ),
			'hold'        => $hold,
			'actionLabel' => $label,
			'actionUrl'   => '' === $label ? '' : (string) call_user_func( $actionUrl, $cause, $door ),
			'door'        => $door,
			'assignLabel' => self::CREDITS === $cause ? Texts::stalledAssign() : '',
			'assignUrl'   => $assignUrl,
		];
	}

	private static function door( $cause, $named ) {
		switch ( $cause ) {
			case self::CREDITS:
				return self::DOOR_ASSIGN === $named ? self::DOOR_ASSIGN : self::DOOR_BUY;
			case self::SPEND_CAP:
				return self::DOOR_ADMIN;
			case self::PAYMENT_METHOD:
				return self::DOOR_ACCOUNT === $named ? self::DOOR_ACCOUNT : self::DOOR_ADMIN;
			default:
				return '';
		}
	}

	public static function forPage() {
		try {
			return self::view( ( new self() )->read() );
		} catch ( \Throwable $e ) {
			return self::view( [ 'cause' => self::NONE ] );
		}
	}

	public static function parkedLists() {
		try {
			$pull = PullState::get();
		} catch ( \Throwable $e ) {
			$pull = PullState::defaults();
		}

		$ids = function ( $list ) {
			return array_values( array_unique( array_filter( array_map( 'intval', (array) $list ) ) ) );
		};

		return [
			'insufficientBalanceJobIds' => $ids( $pull['insufficient_balance_job_ids'] ),
			'paymentPendingJobIds'      => $ids( $pull['payment_pending_job_ids'] ),
		];
	}

	public static function actionUrl( $cause, $door = '' ) {
		$tag = function ( $url, $content ) {
			return \WPML\OutboundLinks\OutboundLinks::to(
				$url,
				[
					'medium'   => 'dashboard',
					'campaign' => 'automatic-translation',
					'content'  => $content,
				]
			);
		};

		switch ( $cause ) {
			case self::CREDITS:
				return self::DOOR_ASSIGN === $door
					? $tag( AmsHostContract::WORDS_PAGE_URL . '?manage_credits=1', 'translations-waiting-assign' )
					: $tag( AmsHostContract::WORDS_PAGE_URL, 'translations-waiting-words' );
			case self::SPEND_CAP:
			case self::PAYMENT_DECLINED:
				return admin_url( \WPML\UIPage::getTMATE() );
			case self::CLIENT_BLOCKED:
				return $tag( 'https://app.wpml.org/support', 'translations-waiting-blocked' );
			case self::PAYMENT_METHOD:
				return self::DOOR_ACCOUNT === $door
					? $tag( AmsHostContract::WORDS_PAGE_URL, 'translations-waiting-method' )
					: admin_url( \WPML\UIPage::getTMATE() );
			default:
				return '';
		}
	}

	private static function ids( $jobIds, $ateJobIds ) {
		$count = function ( $ids ) {
			return count( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
		};

		return max( $count( $jobIds ), $count( $ateJobIds ) );
	}

	private function call( $source ) {
		try {
			return call_user_func( $this->sources[ $source ] );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private static function defaultSources() {
		return [
			'pull'          => [ PullState::class, 'get' ],
			'inFlight'      => function () {
				return State::getKey( 'in_flight' );
			},
			'sentSince'     => function () {
				return State::getKey( 'sent_uncollected_since' );
			},
			'credits'       => function () {
				return OptionManager::getOr( [], 'TM', 'Account::credits' );
			},
			'spendCapHolds' => [ SpendCapState::class, 'holds' ],
			'balances'      => function () {
				return \WPML\LIB\WP\Transient::get( \WPML\TM\API\ATE\Account::BALANCES_CACHE_KEY );
			},
			'restricted'    => [ ClientRestrictionState::class, 'isRestricted' ],
		];
	}
}
