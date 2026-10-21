<?php

namespace WPML\TM\ATE\Hooks;

use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use WPML\TM\ATE\ReturnedJobs;
use WPML\TM\Jobs\Authorization\AuthorizedJobResolver;
use function WPML\Container\make;
use function WPML\FP\partialRight;

class ReturnedJobActionsFactory implements \IWPML_Backend_Action_Loader {

	public function create() {
		$ateJobs  = make( \WPML_TM_ATE_Jobs::class );
		$resolver = new AuthorizedJobResolver( $ateJobs );

		$ateIdToAuthorizedWpmlId = function ( $ateJobId ) use ( $resolver ) {
			$job = $resolver->byAteId( ExecutionContextHolder::current(), $ateJobId );

			return $job ? $job->localId() : 0;
		};
		$removeTranslationDuplicateStatus = partialRight( [ ReturnedJobs::class, 'removeJobTranslationDuplicateStatus' ], $ateIdToAuthorizedWpmlId );

		$accessDenied = new \WPML_TM_AMS_Synchronize_Users_On_Access_Denied();
		$accessDenied->set_ate_jobs( $ateJobs );

		$resignTranslator = function ( $wpmlJobId ) {
			wpml_load_core_tm()->resign_translator( $wpmlJobId );
		};

		$returnDelivery = new \WPML\TM\ATE\PullDelivery\ReturnDelivery( null, null, $ateJobs );

		return [
			new ReturnedJobActions(),
			new ReturnCommand( $removeTranslationDuplicateStatus, $resolver, $accessDenied, $resignTranslator, null, null, [ $returnDelivery, 'deliver' ] ),
		];
	}
}
