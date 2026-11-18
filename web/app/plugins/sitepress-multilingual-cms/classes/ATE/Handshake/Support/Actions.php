<?php

namespace WPML\TM\ATE\Handshake\Support;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseLedger;
use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\API\ATE\Account;
use WPML\TM\ATE\AmsHostContract;
use WPML\TM\ATE\Handshake\AteClient;
use WPML\TM\ATE\Handshake\RetryAnswers;
use WPML\TM\ATE\Handshake\Routes;
use WPML\TM\ATE\Handshake\Runner;
use WPML\TM\ATE\Handshake\State;
use WPML\TM\ATE\Handshake\Notice;
use WPML\OutboundLinks\OutboundLinks;

class Actions {

	const COLLECT        = 'collect';
	const RETRY          = 'retry';
	const RESEND         = 'resend';
	const CANCEL_PREVIEW = 'cancel_preview';
	const CANCEL         = 'cancel';
	const REGISTER       = 'register';

	const ACTIONS = [ self::COLLECT, self::RETRY, self::RESEND, self::CANCEL_PREVIEW, self::CANCEL, self::REGISTER ];

	private $calls;

	public function __construct( array $calls = [] ) {
		$this->calls = array_merge( self::defaultCalls(), $calls );
	}

	public function run( $action ) {
		switch ( $action ) {
			case self::COLLECT:
				return $this->collect();
			case self::RETRY:
				return $this->retry();
			case self::RESEND:
				return $this->resend();
			case self::CANCEL_PREVIEW:
				return $this->cancelPreview();
			case self::CANCEL:
				return $this->cancel();
			case self::REGISTER:
				return [
					'action' => self::REGISTER,
					'ok'     => (bool) call_user_func( $this->calls['register'] ),
				];
			default:
				return [
					'action' => (string) $action,
					'ok'     => false,
					'error'  => 'unknown_action',
				];
		}
	}

	private function collect() {
		$counts = Check::collectCounts( call_user_func( $this->calls['run'] ) );

		return [
			'action'  => self::COLLECT,
			'ok'      => ! $counts['busy'],
			'applied' => $counts['applied'],
			'failed'  => $counts['failed'],
			'busy'    => $counts['busy'],
		];
	}

	private function retry() {
		$data    = (array) call_user_func( $this->calls['retry'] );
		$refused = isset( $data['refused'] ) && is_array( $data['refused'] ) ? $data['refused'] : [];
		$result  = [
			'action'      => self::RETRY,
			'ok'          => true,
			'retried'     => isset( $data['retried'] ) ? (int) $data['retried'] : 0,
			'failed'      => isset( $data['failed'] ) ? (int) $data['failed'] : 0,
			'in_progress' => isset( $data['in_progress'] ) ? (int) $data['in_progress'] : 0,
			'refused'     => $refused,
		];

		if ( isset( $refused[ RetryAnswers::REASON_NOT_ENOUGH ] ) ) {
			$balance                 = call_user_func( $this->calls['balance'] );
			$result['balance_words'] = null === $balance ? null : max( 0, (int) $balance );
		}

		return $result;
	}

	public static function balanceWords() {
		$credits = Account::getCredits( true )->getOrElse( null );

		if ( ! is_array( $credits ) || ! array_key_exists( 'available_balance', $credits ) ) {
			return null;
		}

		return (int) floor( max( 0, Account::getAvailableBalance( $credits ) ) / 4 );
	}

	private function resend() {
		$data = (array) call_user_func( $this->calls['resend'] );

		return [
			'action' => self::RESEND,
			'ok'     => ! isset( $data['error'] ),
			'resent' => isset( $data['resent'] ) ? (int) $data['resent'] : 0,
		];
	}

	private function cancelPreview() {
		$waiting = (array) call_user_func( $this->calls['waiting'] );

		return [
			'action' => self::CANCEL_PREVIEW,
			'ok'     => true,
			'count'  => count( $waiting ),
		];
	}

	private function cancel() {
		$waiting = (array) call_user_func( $this->calls['waiting'] );
		$jobIds  = array_keys( $waiting );

		if ( ! $jobIds ) {
			return [
				'action'      => self::CANCEL,
				'ok'          => true,
				'cancelled'   => 0,
				'words'       => null,
				'unconfirmed' => 0,
				'failed'      => 0,
			];
		}

		$result    = (array) call_user_func( $this->calls['cancel'], $jobIds );
		$cancelled = isset( $result['cancelled'] ) ? (int) $result['cancelled'] : 0;

		return [
			'action'      => self::CANCEL,
			'ok'          => $cancelled > 0,
			'cancelled'   => $cancelled,
			'words'       => isset( $result['words'] ) && null !== $result['words'] ? (int) $result['words'] : null,
			'unconfirmed' => isset( $result['unconfirmed'] ) ? max( 0, (int) $result['unconfirmed'] ) : 0,
			'failed'      => max( 0, count( $jobIds ) - $cancelled ),
		];
	}

	public static function waitingJobs() {
		$inFlight  = ( new AteClient() )->inFlight();
		$ateJobIds = isset( $inFlight['ateJobIds'] ) ? array_map( 'intval', (array) $inFlight['ateJobIds'] ) : [];
		$ateJobs   = \WPML\Container\make( \WPML_TM_ATE_Jobs::class );
		$out       = [];

		foreach ( $ateJobIds as $ateJobId ) {
			$jobId = (int) $ateJobs->get_wpml_job_id( $ateJobId );

			if ( $jobId > 0 ) {
				$out[ $jobId ] = [ 'ate_job_id' => $ateJobId ];
			}
		}

		return $out;
	}

	public static function cancelJobs( array $jobIds ) {
		$mark       = ReleaseLedger::instance()->mark();
		$owedBefore = PendingCancels::pending();
		$result = \WPML\Translation\CancelJobsServiceFactory::create()->cancelJobs( array_map( 'intval', $jobIds ), true, \WPML\TM\ATE\Cancel\CancelSource::SUPPORT_PAGE );

		$summary   = ReleaseLedger::instance()->summaryFrom( $mark );
		$cancelled = isset( $result['cancelledJobIds'] ) && is_array( $result['cancelledJobIds'] ) ? count( $result['cancelledJobIds'] ) : 0;

		State::recordInFlight( max( 0, (int) State::getKey( 'in_flight' ) - $cancelled ) );

		$owed = count( array_diff( PendingCancels::pending(), $owedBefore ) );

		return [
			'cancelled'   => $cancelled,
			'words'       => $summary->isConfirmed() ? $summary->getReleasedWords() : null,
			'unconfirmed' => max( $owed, $summary->getUnconfirmedJobs() ),
		];
	}

	public static function refusalUrl( $reason ) {
		switch ( $reason ) {
			case RetryAnswers::REASON_NOT_ENOUGH:
				return OutboundLinks::to(
					AmsHostContract::WORDS_PAGE_URL,
					[
						'medium'   => 'support',
						'campaign' => 'translation-delivery',
					]
				);
			case RetryAnswers::REASON_SPEND_CAP:
				return admin_url( \WPML\UIPage::getTMATE() );
			default:
				return '';
		}
	}

	public static function reregisterUrl() {
		return admin_url( 'plugin-install.php?tab=commercial#repository-wpml' );
	}

	public static function issueLink( array $systemState ) {
		return isset( $systemState['episode']['issue_link'] ) && is_string( $systemState['episode']['issue_link'] ) ? $systemState['episode']['issue_link'] : '';
	}

	public static function reportUrl( array $systemState ) {
		$issue = self::issueLink( $systemState );

		if ( '' !== $issue ) {
			return $issue;
		}

		return OutboundLinks::to(
			Notice::SUPPORT_URL,
			[
				'medium'   => 'support',
				'campaign' => 'translation-delivery',
			]
		);
	}

	private static function defaultCalls() {
		return [
			'run'     => function () {
				return ( new Runner() )->run( Runner::TRIGGER_MANUAL );
			},
			'retry'   => function () {
				return ( new Routes() )->retry( null )->get_data();
			},
			'resend'  => function () {
				return ( new Routes() )->resend( null )->get_data();
			},
			'waiting'  => [ self::class, 'waitingJobs' ],
			'cancel'   => [ self::class, 'cancelJobs' ],
			'register' => [ self::class, 'registerAgain' ],
			'balance'  => [ self::class, 'balanceWords' ],
		];
	}

	public static function registerAgain() {
		$ams = \WPML\Container\make( \WPML_TM_AMS_API::class );
		$ok  = $ams->register_again();

		SystemView::forget();
		Blocks::forget();

		return $ok;
	}
}
