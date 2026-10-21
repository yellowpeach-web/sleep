<?php

namespace WPML\TranslationRoles;

use WPML\Core\SharedKernel\Component\Translator\Domain\Translator;

class SelfTranslator {

	const PROVIDER_CLASS = 'WPML\\Legacy\\Component\\Translator\\Domain\\Query\\SelfTranslatorProvider';

	private $provider;

	private $translator = null;

	private $resolved = false;

	public function __construct( $provider = null ) {
		$this->provider = $provider ?: self::create_provider();
	}

	private static function create_provider() {
		$provider_class = self::PROVIDER_CLASS;

		return class_exists( $provider_class ) ? new $provider_class() : null;
	}

	public function get() {
		if ( ! $this->resolved ) {
			$this->resolved   = true;
			$this->translator = $this->resolve();
		}

		return $this->translator;
	}

	private function resolve() {
		if ( ! $this->provider || ! is_callable( array( $this->provider, 'get' ) ) ) {
			return null;
		}

		$translator = call_user_func( array( $this->provider, 'get' ) );

		return $translator instanceof Translator ? $translator : null;
	}

	public function can_translate_pair( $lang_from, $lang_to ) {
		$translator = $this->get();

		if ( ! $translator ) {
			return false;
		}

		foreach ( $translator->getLanguagePairs() as $language_pair ) {
			if ( $language_pair->getFrom() === $lang_from
				&& in_array( $lang_to, $language_pair->getTo(), true ) ) {
				return true;
			}
		}

		return false;
	}
}
