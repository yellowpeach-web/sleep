<?php

namespace WPML\TM\Jobs\Authorization;

use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\Core\Security\ExecutionContext\ExecutionContextHolder;
use function WPML\Container\make;

final class AuthorizedJobResolver {

	const ACCESS_TRANSLATE = 'translate';

	const ACCESS_OWN = 'own';

	private $ateJobs;

	public function __construct( ?\WPML_TM_ATE_Jobs $ateJobs = null ) {
		$this->ateJobs = $ateJobs;
	}

	public static function make() {
		return new self();
	}

	public function byAteId( ExecutionContext $context, $ateJobId, $access = self::ACCESS_TRANSLATE ) {
		$ateJobId = (int) $ateJobId;
		if ( $ateJobId <= 0 ) {
			return null;
		}

		$localId = (int) $this->getAteJobs()->get_wpml_job_id( $ateJobId );
		if ( $localId <= 0 ) {
			return null;
		}

		return $this->authorize( $context, $localId, $ateJobId, null, $access );
	}

	public function localIdOfAteId( $ateJobId ) {
		$ateJobId = (int) $ateJobId;
		if ( $ateJobId <= 0 ) {
			return 0;
		}

		return (int) $this->getAteJobs()->get_wpml_job_id( $ateJobId );
	}

	public function byLocalId( ExecutionContext $context, $jobId, $access = self::ACCESS_TRANSLATE, $type = null ) {
		$jobId = (int) $jobId;
		if ( $jobId <= 0 ) {
			return null;
		}

		return $this->authorize( $context, $jobId, 0, $type, $access );
	}

	public function byBoundPair( ExecutionContext $context, $jobId, $ateJobId, $access = self::ACCESS_TRANSLATE ) {
		$job = $this->byAteId( $context, $ateJobId, $access );
		if ( ! $job || $job->localId() !== (int) $jobId ) {
			return null;
		}

		return $job;
	}

	public static function currentContext() {
		return ExecutionContextHolder::current();
	}

	private function authorize( ExecutionContext $context, $localId, $ateJobId, $type, $access ) {
		if ( ! in_array( $access, [ self::ACCESS_TRANSLATE, self::ACCESS_OWN ], true ) ) {
			return null;
		}

		if ( $context->isTrusted() ) {
			return new AuthorizedJob( $localId, $ateJobId, $type, $access );
		}

		$allowed = self::ACCESS_OWN === $access
			? JobAuthorization::currentUserOwnsJob( $localId, $type )
			: JobAuthorization::currentUserCanTranslateJob( $localId, $type );

		return $allowed ? new AuthorizedJob( $localId, $ateJobId, $type, $access ) : null;
	}

	private function getAteJobs() {
		if ( null === $this->ateJobs ) {
			$this->ateJobs = make( \WPML_TM_ATE_Jobs::class );
		}

		return $this->ateJobs;
	}
}
