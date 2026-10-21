<?php

namespace WPML\TM\ATE\AutoTranslate\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Left;
use WPML\FP\Right;
use WPML\TM\Editor\TranslationActionResolver;

class TranslationAction implements IHandler {

	private $resolver;

	public function __construct( TranslationActionResolver $resolver ) {
		$this->resolver = $resolver;
	}

	public function run( Collection $data ) {
		$trid     = $data->get( 'trid' );
		$language = $data->get( 'language' );

		if ( ! $trid || ! $language ) {
			return Left::of( 'invalid data' );
		}

		return Right::of( $this->resolver->resolve( [
			'trid'           => $trid,
			'targetLanguage' => $language,
			'verb'           => $data->get( 'verb' ) ?: TranslationActionResolver::VERB_TRANSLATE,
			'currentUrl'     => $data->get( 'currentUrl' ),
		] ) );
	}
}
