<?php

namespace WPML\TM\ATE\PullDelivery;

use WPML\Core\Component\ATE\Application\Service\CreditsService;
use WPML\Core\Component\ATE\Domain\Credits\DebtorBlock;
use WPML\FP\Obj;
use WPML\TM\API\ATE\Account;
use WPML\TM\ATE\API\SpendCap;
use WPML\TM\ATE\API\SpendCapState;
use WPML\TM\ATE\AutoTranslate\Endpoint\Resume;
use WPML\TM\Jobs\JobLog;

class NoCreditResume {

	private $resume;

	private $wake = false;

	private $creditsThisCheck = false;

	private $parkedSince = 0;

	public function __construct( Resume $resume ) {
		$this->resume = $resume;
	}

	private function floor( array $parked ) {
		if ( $this->wake && ( $parked || SpendCapState::isReached() ) ) {
			return Cadence::wakeFloor();
		}

		return Cadence::activeFloor();
	}

	private function readCredits() {
		return $this->wake ? $this->freshCredits() : $this->credits();
	}

	public function maybeResume( array $state, $wake = false ) {
		$this->creditsThisCheck = false;
		$this->wake             = (bool) $wake;
		$parked                 = $this->parkedJobs( $state );
		$floor                  = $this->floor( $parked );

		if ( $parked && ! $state['parked_since'] ) {
			$state = State::update( [ 'parked_since' => time() ] );
		} elseif ( ! $parked && $state['parked_since'] ) {
			$state = State::update( [ 'parked_since' => 0 ] );
		}
		$this->parkedSince = (int) $state['parked_since'];

		if ( ! $parked ) {

			if ( SpendCapState::isReached() && time() - (int) $state['resume_checked_at'] >= $floor ) {
				$state = State::update( [ 'resume_checked_at' => time() ] );
				$this->readCredits();
			}

			return $state;
		}

		$fingerprint = $this->fingerprint( $parked );

		$sameSet = $fingerprint === (string) $state['resume_attempted_for'] && ! SpendCapState::isReached();

		if ( time() - (int) $state['resume_checked_at'] < $floor ) {
			return $state;
		}

		$state = State::update( [ 'resume_checked_at' => time() ] );

		if ( $sameSet && ! $this->balanceGrewSince( (int) $state['resume_attempted_balance'] ) ) {
			return $state;
		}

		if ( ! $this->hasCreditsForWorkInProgress() ) {
			return $state;
		}

		$this->resume->run( wpml_collect( [ 'ateJobIds' => $parked ] ) );

		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_DOWNLOAD_JOBS, 'ATE pull delivery resume' );
		JobLog::add( 'pull_resume_no_credit_jobs', [ 'ateJobIds' => $parked ] );
		JobLog::finishCurrentGroup();

		if ( $this->wake ) {
			$paused = SpendCapState::get();
			if ( $paused ) {
				SpendCapState::save( $paused->asResumed() );
			}
		}

		return State::update(
			[
				'resume_attempted_for'             => $fingerprint,
				'resume_attempted_balance'         => $this->availableBalanceAlreadyRead(),
				'insufficient_balance_ate_job_ids' => [],
				'insufficient_balance_job_ids'     => [],
				'parked_since'                     => 0,
				'payment_parked_ate_job_ids'       => array_values( array_diff( array_map( 'intval', (array) $state['payment_parked_ate_job_ids'] ), $parked ) ),
			]
		);
	}

	private function parkedJobs( array $state ) {
		$ids = array_map( 'intval', (array) $state['insufficient_balance_ate_job_ids'] );

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private function fingerprint( array $parked ) {
		sort( $parked );

		return md5( implode( ',', $parked ) );
	}

	protected function hasCreditsForWorkInProgress() {
		$credits = $this->creditsForThisCheck();

		if ( ! is_array( $credits ) || ! $credits ) {
			return false;
		}

		if ( DebtorBlock::fromCredits( $credits ) ) {
			return false;
		}

		if ( Obj::propOr( false, 'pay_as_you_go', $credits ) ) {
			if ( 0 === (int) Obj::propOr( 0, 'subscription_debt', $credits ) ) {
				return SpendCapState::get()
					? $this->spendCapHasRoomAgain( $credits )
					: $this->isStuck( $credits );
			}

			return false;
		}

		$available  = (int) Obj::propOr( 0, 'available_balance', $credits );
		$inProgress = (int) $this->creditsInProgress();

		return $inProgress > 0 && $available >= $inProgress;
	}

	private function isStuck( array $credits ) {
		return empty( $credits['payment_required'] )
			&& $this->parkedSince > 0
			&& time() - $this->parkedSince >= Cadence::parkedGrace();
	}

	private function spendCapHasRoomAgain( array $credits ) {
		$paused = SpendCapState::get();
		if ( ! $paused ) {
			return false;
		}

		$cap = SpendCap::fromCredits( $credits );

		if ( null === $cap ) {
			return true;
		}

		return $paused->readingMovedSincePause( $cap );
	}

	private function balanceGrewSince( $balanceAtAttempt ) {
		$credits = $this->creditsForThisCheck();

		return is_array( $credits ) && (int) Obj::propOr( 0, 'available_balance', $credits ) > $balanceAtAttempt;
	}

	private function creditsForThisCheck() {
		if ( false === $this->creditsThisCheck ) {
			$this->creditsThisCheck = $this->readCredits();
		}

		return $this->creditsThisCheck;
	}

	private function availableBalanceAlreadyRead() {
		return is_array( $this->creditsThisCheck ) ? (int) Obj::propOr( 0, 'available_balance', $this->creditsThisCheck ) : 0;
	}

	protected function credits() {
		return Account::getCredits( true )->getOrElse( null );
	}

	protected function freshCredits() {
		return Account::getCredits( false )->getOrElse( null );
	}

	protected function creditsInProgress() {
		global $wpml_dic;

		if ( ! $wpml_dic ) {
			return 0;
		}

		try {
			$service = $wpml_dic->make( CreditsService::class );

			return (int) $service->getCreditsInProgress()->getCount();
		} catch ( \Throwable $e ) {
			return 0;
		}
	}
}
