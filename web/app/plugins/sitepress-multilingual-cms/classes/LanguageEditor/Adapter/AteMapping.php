<?php

namespace WPML\LanguageEditor\Adapter;

use WPML\Core\Component\LanguageEditor\Domain\Ate\AteMappingInterface;
use WPML\Element\API\Entity\LanguageMapping;
use WPML\Element\API\Languages;
use WPML\LanguageEditor\EngineConfirmedHead;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\Setup\Option;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\AutomaticTranslationCapabilities;

class AteMapping implements AteMappingInterface {

	const REFUSED_ONTO_DEFAULT_LANGUAGE = 'mapping_onto_default_language';

	const REFUSED_ONTO_ITSELF = 'mapping_onto_itself';

	private $headResolver;

	private $isCustomResolver;

	private $defaultCodeResolver;

	public function __construct( $headResolver = null, $isCustomResolver = null, $defaultCodeResolver = null ) {
		$this->headResolver        = $headResolver ?: [ EngineConfirmedHead::class, 'resolve' ];
		$this->isCustomResolver    = $isCustomResolver ?: [ $this, 'readIsCustom' ];
		$this->defaultCodeResolver = $defaultCodeResolver ?: [ Languages::class, 'getDefaultCode' ];
	}

	public function mappingRefusal( string $code, string $ateLang, ?string $ateCountry, string $defaultCode ): ?string {
		$ateLang = trim( $ateLang );
		if ( '' === $ateLang ) {
			return null;
		}

		$targetCode = $this->targetCodeOf( $ateLang, $ateCountry );
		if ( self::isOntoItself( $code, $targetCode ) ) {
			return self::REFUSED_ONTO_ITSELF;
		}

		return LanguageMappings::isOntoDefaultLanguage( $code, $targetCode, $defaultCode )
			? self::REFUSED_ONTO_DEFAULT_LANGUAGE
			: null;
	}

	private static function isOntoItself( string $code, string $targetCode ): bool {
		$fold = function ( $value ) {
			return str_replace( '_', '-', strtolower( trim( (string) $value ) ) );
		};
		if ( '' === $fold( $code ) || $fold( $code ) !== $fold( $targetCode ) ) {
			return false;
		}

		return '' !== LanguageCodeResolution::publishedIdentity( $code )['language'];
	}

	public function saveMapping( string $code, string $ateLang, ?string $ateCountry ): ?int {
		$code    = (string) $code;
		$ateLang = trim( (string) $ateLang );

		if ( '' === $ateLang && call_user_func( $this->isCustomResolver, $code ) ) {
			$head = (string) call_user_func( $this->headResolver, $code );
			if ( '' !== $head && strtolower( $head ) !== strtolower( (string) $code ) ) {
				$ateLang    = $head;
				$ateCountry = null;
			}
		}

		if ( '' !== $ateLang && null !== $this->mappingRefusal( $code, $ateLang, $ateCountry, $this->defaultCode() ) ) {
			$ateLang = '';
		}

		Option::removeLanguageMapping( $code );

		$mapping = null;
		if ( '' !== $ateLang ) {
			$targetCode = $this->targetCodeOf( $ateLang, $ateCountry );
			$mapping = new LanguageMapping( $code, $this->sourceName( $code ), $this->safeTargetId( $ateLang ), $targetCode );
			Option::addLanguageMapping( $mapping );
		}

		if ( AutomaticTranslationCapabilities::isAvailable() ) {
			try {
				$push = $mapping
					? $mapping
					: new LanguageMapping( $code, $this->sourceName( $code ), LanguageMappings::IGNORE_MAPPING_ID, '' );
				LanguageMappings::saveMapping( [ $push ] );
			} catch ( \Throwable $e ) {
			}
		}

		return null;
	}

	private function targetCodeOf( string $ateLang, ?string $ateCountry ): string {
		return $ateCountry ? $ateLang . '-' . strtolower( (string) $ateCountry ) : $ateLang;
	}

	private function defaultCode(): string {
		try {
			return (string) call_user_func( $this->defaultCodeResolver );
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	private function safeTargetId( string $ateLang ): int {
		try {
			return $this->targetId( $ateLang );
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	private function targetId( string $ateLang ): int {
		if ( ! AutomaticTranslationCapabilities::isAvailable() ) {
			return 0;
		}

		foreach ( (array) LanguageMappings::getAvailable() as $lang ) {
			$lang = (array) $lang;
			if ( isset( $lang['iso'] ) && strtolower( (string) $lang['iso'] ) === strtolower( $ateLang ) ) {
				return (int) ( isset( $lang['id'] ) ? $lang['id'] : 0 );
			}
		}

		return 0;
	}

	private function readIsCustom( string $code ): bool {
		global $wpdb;

		$isCustom = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT is_custom FROM {$wpdb->prefix}icl_languages WHERE code = %s",
				$code
			)
		);

		return (int) $isCustom === 1;
	}

	private function sourceName( string $code ): string {
		global $wpdb;

		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT english_name FROM {$wpdb->prefix}icl_languages WHERE code = %s",
				$code
			)
		);

		return (string) ( $name ? $name : $code );
	}
}
