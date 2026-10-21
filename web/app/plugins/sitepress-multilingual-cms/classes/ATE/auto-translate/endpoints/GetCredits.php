<?php

namespace WPML\TM\ATE\AutoTranslate\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\ATE\Application\Service\CreditsService;
use WPML\Core\Component\ATE\Domain\Credits\DebtorBlock;
use WPML\TM\API\ATE\Account;

class GetCredits implements IHandler {

	public function run( Collection $data ) {
		$allowCached = (bool) $data->get( 'allowCached', false );

		return Account::getCredits( $allowCached )->map( function( $credits ) {
			global $wpml_dic;

			$creditsService = $wpml_dic->make( CreditsService::class );

			$credits['creditsInProgress'] = $creditsService->getCreditsInProgress()->getCount();
			$credits['debtor_block'] = DebtorBlock::fromCredits( $credits );

			return $credits;
		} );
	}
}
