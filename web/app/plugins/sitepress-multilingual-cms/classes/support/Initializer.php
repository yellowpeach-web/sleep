<?php

namespace WPML\Support;

use Exception;
use Throwable;
use WPML\Core\Component\MinimumRequirements\Application\Service\RequirementsService;

use function WPML\PHP\Logger\error;

class Initializer {

	public static function getData(): array {
		global $wpml_dic;
		$requirementsService = $wpml_dic->make( RequirementsService::class );

		try {
			$invalidRequirements = $requirementsService->getInvalidRequirements( true );
		} catch ( Throwable $e ) {
			error( 'Failed to get InvalidRequirements: ' . $e->getMessage() . ' ' . $e->getTraceAsString() );
			$invalidRequirements = [];
		}

		return[
			'showMinRequirementsComponent'  =>  count( $invalidRequirements ) > 0,
			'serializedInvalidRequirements' => self::serializeRequirements( $invalidRequirements )
		];
	}

	private static function serializeRequirements( $array ) {
		try {
			return esc_attr( (string) wp_json_encode( $array ) );
		} catch ( Exception $e ) {
			return '';
		}
	}
}
