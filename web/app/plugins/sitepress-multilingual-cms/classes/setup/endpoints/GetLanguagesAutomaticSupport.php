<?php

namespace WPML\Setup\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Element\API\Languages;
use WPML\FP\Either;
use WPML\FP\Obj;
use WPML\LanguageEditor\TranslationPause;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\AutomaticTranslationCapabilities;

class GetLanguagesAutomaticSupport implements IHandler {

	const REASON_MANUAL_ONLY     = 'manual-only';
	const REASON_SAME_AS_DEFAULT = 'same-as-default';
	const REASON_PAUSED          = 'paused';

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( 'forbidden' );
		}

		$input = array_map(
			function ( $lang ) {
				$lang = (array) $lang;

				return [ 'code' => isset( $lang['code'] ) ? (string) $lang['code'] : '' ];
			},
			array_values( (array) Languages::getActive() )
		);

		$capabilities = AutomaticTranslationCapabilities::withCapabilityInfo( $input );
		$defaultCode  = (string) Languages::getDefaultCode();

		$support = [];
		foreach ( (array) $capabilities as $lang ) {
			$code = (string) Obj::propOr( '', 'code', $lang );
			if ( '' === $code ) {
				continue;
			}

			$translatable = (bool) Obj::propOr( false, 'can_be_translated_automatically', $lang );
			$paused    = $translatable && $code !== $defaultCode && TranslationPause::isPaused( $code );
			$supported = $translatable && ! $paused;

			if ( $supported ) {
				$reason = null;
			} elseif ( $paused ) {
				$reason = self::REASON_PAUSED;
			} elseif ( LanguageMappings::resolvesToSameAteLanguageAsDefault( $code, $defaultCode ) ) {
				$reason = self::REASON_SAME_AS_DEFAULT;
			} else {
				$reason = self::REASON_MANUAL_ONLY;
			}

			$support[ $code ] = [
				'supported' => $supported,
				'reason'    => $reason,
			];
		}

		return Either::right( $support );
	}
}
