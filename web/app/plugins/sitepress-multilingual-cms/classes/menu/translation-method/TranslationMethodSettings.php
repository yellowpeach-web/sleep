<?php

namespace WPML\TM\Menu\TranslationMethod;

use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\Setup\Option;
use WPML\TranslationMode\Endpoint\SetTranslateEverything;
use WPML\WP\OptionManager;

class TranslationMethodSettings {

	public static function getModeSettingsData() {
		$hasPreferredTranslationService = \TranslationProxy::has_preferred_translation_service();
		Option::setDefaultTranslationMode( $hasPreferredTranslationService );

		return [
			'whoModes'                       => Option::getTranslationMode(),
			'defaultServiceName'             => self::getDefaultTranslationServiceName(),
			'hasPreferredTranslationService' => $hasPreferredTranslationService,
			'reviewMode'                     => Option::getReviewMode(),
			'isTMAllowed'                    => true,
			'translateEverything'            => Option::shouldTranslateEverything( null ),
			'translateEverythingChosen'      => (bool) ( new OptionManager() )->get(
				Option::OPTION_GROUP,
				SetTranslateEverything::KEY_TRANSLATE_EVERYTHING_CHOSEN,
				false
			),
		];
	}

	public static function getDefaultTranslationServiceName() {
		try {
			return Maybe::fromNullable( \TranslationProxy::get_tp_default_suid() )
				->map( [ \TranslationProxy_Service::class, 'get_service_by_suid' ] )
				->map( Obj::prop( 'name' ) )
				->getOrElse( '' );
		} catch ( \Exception $e ) {
			return '';
		}
	}
}
