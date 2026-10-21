<?php

namespace WPML\TM\API;


use WPML\FP\Curryable;
use WPML\Translation\CancelJobsServiceFactory;

class Batch {

	use Curryable;

	public static function init() {

		self::curryN( 'rollback', 1, function ( $basketName ) {
			$batchId = \TranslationProxy_Batch::getBatchId( $basketName );

			if ( $batchId ) {
				$cancelJobsService = CancelJobsServiceFactory::create();
				$cancelJobsService->cancelJobsInBatch( $batchId );
			}

			\WPML\TM\TranslationProxy\TpBatchState::setBatchData( null );
			icl_cache_clear_preserving_language_names();
		} );


	}
}

Batch::init();
