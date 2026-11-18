<?php

namespace WPML\TM\ATE\AutoTranslate;

use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\TM\API\ATE\Account;
use WPML\TM\Editor\Dispatch\DispatchPlan;
use WPML\WP\OptionManager;
use WPML\TM\Jobs\Manual;
use WPML\TranslationPolicy\Policy\PolicyReaderInterface;
use WPML\TranslationPolicy\Policy\PolicyTable;

class PolicyEligibility extends EligibilityRules {

	private $reader;

	private $credits;

	public function __construct( \SitePress $sitepress, PolicyReaderInterface $reader, $credits = null ) {
		parent::__construct( $sitepress );
		$this->reader  = $reader;
		$this->credits = $credits ?: function ( $fresh ) {
			return $fresh
				? Account::getCredits( true )->getOrElse( null )
				: OptionManager::getOr( [], 'TM', 'Account::credits' );
		};
	}

	protected function postTranslatesAutomatically( $postId ) {
		return PolicyTable::METHOD_PTC === $this->reader->forPost( (int) $postId )->method();
	}

	public function refusesNewJob() {
		if ( self::hasWords( call_user_func( $this->credits, false ) ) ) {
			return null;
		}

		$fresh = call_user_func( $this->credits, true );
		if ( ! self::isAnswer( $fresh ) || self::hasWords( $fresh ) ) {
			return null;
		}

		return DispatchPlan::REFUSAL_NO_WORDS;
	}

	private static function hasWords( $credits ) {
		return self::isAnswer( $credits )
			&& ( Account::hasActiveSubscription( $credits ) || Account::getAvailableBalance( $credits ) > 0 );
	}

	private static function isAnswer( $credits ) {
		return is_array( $credits ) && array_key_exists( 'active_subscription', $credits );
	}

	protected function isTranslationOpen( $translation ) {
		$hasJob      = (bool) Obj::propOr( 0, 'job_id', $translation );
		$isAutomatic = (bool) Obj::propOr( 0, 'automatic', $translation );

		if ( ! $hasJob || $isAutomatic ) {
			return true;
		}

		return ! Lst::includes( (int) Obj::prop( 'status', $translation ), Manual::IN_PROGRESS_STATUSES );
	}
}
