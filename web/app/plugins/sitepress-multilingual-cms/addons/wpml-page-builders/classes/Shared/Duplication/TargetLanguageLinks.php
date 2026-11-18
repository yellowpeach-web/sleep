<?php

namespace WPML\PB\Duplication;

class TargetLanguageLinks {

	private $translateLinkTargets;

	public function __construct( \WPML_Translate_Link_Targets $translateLinkTargets ) {
		$this->translateLinkTargets = $translateLinkTargets;
	}

	public function convert( $lang, callable $convert ) {
		do_action( 'wpml_switch_language', $lang );
		add_filter( 'wpml_force_translated_permalink', '__return_true' );

		try {
			return $convert( $this->translateLinkTargets );
		} finally {
			remove_filter( 'wpml_force_translated_permalink', '__return_true' );
			do_action( 'wpml_switch_language', null );
		}
	}
}
