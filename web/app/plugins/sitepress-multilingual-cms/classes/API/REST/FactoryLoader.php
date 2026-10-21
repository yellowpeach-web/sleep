<?php

namespace WPML\TM\REST;

use IWPML_Deferred_Action_Loader;
use IWPML_REST_Action_Loader;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\TeaLoggerInterface;
use WPML\Posts\UntranslatedCount;
use WPML\TM\ATE\REST\Retry;
use WPML\TM\ATE\REST\Sync;
use \WPML\TM\ATE\REST\FixJob;
use WPML\TM\ATE\REST\Download;
use WPML\TM\ATE\REST\PublicCreate;
use WPML\TM\ATE\REST\PublicReceive;
use WPML\TM\ATE\REST\PullCollect;
use WPML\TM\ATE\REST\PullPing;
use WPML\TM\ATE\TranslateEverything;
use WPML\TM\AutomaticTranslation\Actions\Actions;
use function WPML\Container\make;

class FactoryLoader implements IWPML_REST_Action_Loader, IWPML_Deferred_Action_Loader {

	const REST_API_INIT_ACTION = 'rest_api_init';

	public function get_load_action() {
		return self::REST_API_INIT_ACTION;
	}

	public function create() {
		return [
			Sync::class          => make( Sync::class ),
			Download::class      => make( Download::class ),
			Retry::class         => make( Retry::class ),
			PublicReceive::class => make( PublicReceive::class ),
			PublicCreate::class  => $this->makePublicCreate(),
			FixJob::class        => make( FixJob::class ),
			PullCollect::class   => make( PullCollect::class ),
			PullPing::class      => make( PullPing::class ),
		];
	}

	private function makePublicCreate(): PublicCreate {
		global $wpml_dic;

		return new PublicCreate(
			$wpml_dic->make( TeaLoggerInterface::class ),
			make( TranslateEverything::class ),
			make( Actions::class ),
			make( UntranslatedCount::class )
		);
	}
}
