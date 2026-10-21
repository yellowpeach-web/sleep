<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Obj;
use WPML\Posts\CountPerPostType;
use WPML\TM\ATE\AutomaticTranslationCapabilities;

class AddLanguageTranslationInfo implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		$code = sanitize_text_field( (string) $data->get( 'code', '' ) );
		if ( '' === $code ) {
			return Either::left( array( 'error' => 'missing_code' ) );
		}

		$supported = false;
		$caps      = AutomaticTranslationCapabilities::withCapabilityInfo( array( array( 'code' => $code ) ) );
		foreach ( (array) $caps as $lang ) {
			if ( (string) Obj::propOr( '', 'code', $lang ) === $code ) {
				$supported = (bool) Obj::propOr( false, 'can_be_translated_automatically', $lang );
				break;
			}
		}

		global $wpdb;
		$counts = ( new CountPerPostType() )->run( wpml_collect( array() ), $wpdb );

		$types = array();
		$total = 0;
		foreach ( $counts as $label => $count ) {
			$count = (int) $count;
			if ( $count > 0 ) {
				$types[] = array(
					'label' => (string) $label,
					'count' => $count,
				);
				$total  += $count;
			}
		}

		return Either::right(
			array(
				'supported' => $supported,
				'total'     => $total,
				'types'     => $types,
			)
		);
	}
}
