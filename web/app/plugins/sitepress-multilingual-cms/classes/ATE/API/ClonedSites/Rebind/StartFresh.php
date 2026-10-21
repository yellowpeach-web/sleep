<?php

namespace WPML\TM\ATE\ClonedSites\Rebind;

use WPML\FP\Fns;
use WPML\LIB\WP\User;
use WPML\TM\ATE\API\CachedAMSAPI;
use WPML\TM\ATE\ClonedSites\IdentitySnapshot;
use WPML\TM\ATE\ClonedSites\OldIdentityJobs;

use function WPML\Container\make;

class StartFresh {

	private $amsApi;

	private $amsUsers;

	private $auth;

	public function __construct(
		\WPML_TM_AMS_API $amsApi,
		\WPML_TM_AMS_Users $amsUsers,
		\WPML_TM_ATE_Authentication $auth
	) {
		$this->amsApi   = $amsApi;
		$this->amsUsers = $amsUsers;
		$this->auth     = $auth;
	}

	public function run(): bool {
		$snapshot = IdentitySnapshot::capture( $this->auth );
		$previous = $snapshot->websiteUuid();

		$succeeded = $this->amsApi
			->register_manager(
				User::getCurrent(),
				$this->amsUsers->get_translators(),
				$this->amsUsers->get_managers(),
				true
			)
			->bimap( Fns::always( false ), Fns::always( true ) )
			->getOrElse( false );

		if ( ! $succeeded ) {
			$snapshot->restore();

			return false;
		}

		if ( '' !== $previous && (string) $this->auth->get_site_id() === $previous ) {
			return false;
		}

		CachedAMSAPI::clearCache();

		State::clear();

		OldIdentityJobs::cancel();

		return true;
	}

	public static function build(): self {
		return new self(
			make( \WPML_TM_AMS_API::class ),
			make( \WPML_TM_AMS_Users::class ),
			make( \WPML_TM_ATE_Authentication::class )
		);
	}
}
