<?php

namespace WPML\LanguageEditor\Adapter;

use WPML\Core\Component\LanguageEditor\Domain\Ate\AteMappingInterface;
use WPML\Element\API\Entity\LanguageMapping;
use WPML\LanguageEditor\EngineConfirmedHead;
use WPML\Setup\Option;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\AutomaticTranslationCapabilities;

class AteMapping implements AteMappingInterface {

	private $headResolver;

	private $isCustomResolver;

	public function __construct( $headResolver = null, $isCustomResolver = null ) {
		$this->headResolver     = $headResolver ?: [ EngineConfirmedHead::class, 'resolve' ];
		$this->isCustomResolver = $isCustomResolver ?: [ $this, 'readIsCustom' ];
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

		Option::removeLanguageMapping( $code );

		$mapping = null;
		if ( '' !== $ateLang ) {
			$targetCode = $ateCountry ? $ateLang . '-' . strtolower( (string) $ateCountry ) : $ateLang;
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
