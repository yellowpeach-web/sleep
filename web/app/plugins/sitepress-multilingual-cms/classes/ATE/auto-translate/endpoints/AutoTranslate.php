<?php

namespace WPML\TM\ATE\AutoTranslate\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Left;
use WPML\FP\Obj;
use WPML\FP\Right;
use WPML\TM\Editor\TranslationActionResolver;

class AutoTranslate implements IHandler {

	private $resolver;

	public function __construct( TranslationActionResolver $resolver ) {
		$this->resolver = $resolver;
	}

	public function run( Collection $data ) {
		$trid          = $data->get( 'trid' );
		$language_code = $data->get( 'language' );

		if ( ! $trid || ! $language_code ) {
			return Left::of( 'invalid data' );
		}

		$answer = $this->resolver->resolve( [
			'trid'           => $trid,
			'targetLanguage' => $language_code,
			'verb'           => TranslationActionResolver::VERB_TRANSLATE,
			'currentUrl'     => $data->get( 'currentUrl' ),
		] );

		switch ( Obj::prop( 'action', $answer ) ) {
			case TranslationActionResolver::ACTION_AUTO_TRANSLATE:
				return Right::of( [ 'jobId' => $answer['jobId'], 'automatic' => 1 ] );
			case TranslationActionResolver::ACTION_NAVIGATE:
				return Right::of( [
					'jobId'     => Obj::propOr( 0, 'jobId', $answer ),
					'automatic' => 0,
					'editUrl'   => Obj::prop( 'url', $answer ),
				] );
			default:
				return Left::of( Obj::prop( 'message', $answer ) );
		}
	}
}
