<?php

namespace WPML\PB\BeTheme;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class TemplateFallbackHooks implements \IWPML_Action {

	const TYPES = [ 'header', 'footer' ];

	const POST_TYPE = 'template';

	private $secondaryLanguages;

	public function __construct( array $secondaryLanguages ) {
		$this->secondaryLanguages = $secondaryLanguages;
	}

	public function add_hooks() {
		foreach ( self::TYPES as $type ) {
			foreach ( $this->secondaryLanguages as $language ) {
				Hooks::onFilter( 'default_option_' . self::getOptionName( $type, $language ), 10, 1 )
					->then(
						spreadArgs(
							function ( $default ) use ( $type, $language ) {
								return $this->fallbackToDefaultLanguage( $default, $type, $language );
							}
						)
					);
			}
		}
	}

	public function fallbackToDefaultLanguage( $default, $type, $language ) {
		$defaultId = get_option( self::getOptionName( $type ) );
		if ( ! is_numeric( $defaultId ) || (int) $defaultId <= 0 ) {
			return $default;
		}

		$defaultId = (int) $defaultId;
		$id        = (int) apply_filters( 'wpml_object_id', $defaultId, self::POST_TYPE, true, $language );
		if ( $id <= 0 ) {
			return $default;
		}

		if ( $id !== $defaultId && 'publish' !== get_post_status( $id ) ) {
			return $default;
		}

		return $id;
	}

	public static function getOptionName( $type, $language = null ) {
		return 'mfn_' . $type . ( $language ? '_' . $language : '' ) . '_entire_site';
	}
}
