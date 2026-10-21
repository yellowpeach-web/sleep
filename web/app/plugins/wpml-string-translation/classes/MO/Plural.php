<?php

namespace WPML\ST\MO;

use WPML\ST\TranslationFile\PluralForms;

class Plural implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public function add_hooks() {
		add_filter( 'ngettext', [ $this, 'handle_plural' ], 9, 5 );
		add_filter( 'ngettext_with_context', [ $this, 'handle_plural_with_context' ], 9, 6 );
	}

	public function handle_plural( $translation, $single, $plural, $number, $domain ) {
		return $this->get_translation( $translation, $single, $plural, $number, $domain, function ( $original ) use ( $domain ) {
			return __( $original, $domain );
		} );
	}

	public function handle_plural_with_context( $translation, $single, $plural, $number, $context, $domain ) {
		return $this->get_translation( $translation, $single, $plural, $number, $domain,
			function ( $original ) use ( $domain, $context ) {
				return _x( $original, $context, $domain );
			} );
	}

	private function get_translation( $translation, $single, $plural, $number, $domain, $callback ) {
		$form = PluralForms::getIndexForDomain( (string) $domain, (int) $number );

		if ( $form > 1 ) {
			return $translation;
		}

		$original = 0 === $form ? $single : $plural;

		$possible_translation = $callback( $original );

		if ( $possible_translation !== $original ) {
			return $possible_translation;
		}

		return $translation;
	}
}
