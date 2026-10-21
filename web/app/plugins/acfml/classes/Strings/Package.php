<?php

namespace ACFML\Strings;

use WPML\Element\API\Languages;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\LIB\WP\Cache;

class Package {

	const KIND_SLUG = 'acf-field-group';

	const FIELD_GROUP_PACKAGE_KIND         = 'ACF Field Group';
	const FIELD_GROUP_PACKAGE_KIND_SLUG    = 'acf-field-group';
	const FIELD_GROUP_PACKAGE_TITLE        = 'Field Group Labels %s';
	const FIELD_GROUP_PACKAGE_ELEMENT_TYPE = 'package_' . self::FIELD_GROUP_PACKAGE_KIND_SLUG;

	const CPT_PACKAGE_KIND      = 'ACF Post Type Labels';
	const CPT_PACKAGE_KIND_SLUG = 'acf-post-type-labels';
	const CPT_PACKAGE_TITLE     = 'Post Type Labels for %s';

	const TAXONOMY_PACKAGE_KIND      = 'ACF Taxonomy Labels';
	const TAXONOMY_PACKAGE_KIND_SLUG = 'acf-taxonomy-labels';
	const TAXONOMY_PACKAGE_TITLE     = 'Taxonomy Labels for %s';

	const OPTION_PAGE_PACKAGE_KIND      = 'ACF Options Page Labels';
	const OPTION_PAGE_PACKAGE_KIND_SLUG = 'acf-options-page-labels';
	const OPTION_PAGE_PACKAGE_TITLE     = 'Options Page Labels for %s';

	const OPTION_PACKAGE_KIND          = 'ACF Options';
	const OPTION_PACKAGE_KIND_SLUG     = 'acf-options';
	const OPTION_PACKAGE_TITLE         = 'ACF Options Values for %s';
	const OPTION_PACKAGE_TITLE_DEFAULT = 'ACF Options Values';
	const OPTION_PACKAGE_NAMESPACE     = 'options';
	const OPTION_PACKAGE_ELEMENT_TYPE  = 'package_' . self::OPTION_PACKAGE_KIND_SLUG;

	const STATUS_ST_INACTIVE          = 'st_inactive';
	const STATUS_NOT_REGISTERED       = 'not_registered';
	const STATUS_NOT_TRANSLATED       = 'not_translated';
	const STATUS_PARTIALLY_TRANSLATED = 'partially_translated';
	const STATUS_FULLY_TRANSLATED     = 'fully_translated';

	private $packageId;

	private $kind;

	private $title;

	public function __construct( $packageId, $kind = self::FIELD_GROUP_PACKAGE_KIND_SLUG ) {
		$this->packageId = $packageId;
		$this->kind      = $kind;
	}

	private function getPackageData() {
		switch ( $this->kind ) {
			case self::CPT_PACKAGE_KIND_SLUG:
				return [
					'kind'      => self::CPT_PACKAGE_KIND,
					'kind_slug' => self::CPT_PACKAGE_KIND_SLUG,
					'name'      => $this->packageId,
					'title'     => sprintf( self::CPT_PACKAGE_TITLE, $this->packageId ),
				];
			case self::TAXONOMY_PACKAGE_KIND_SLUG:
				return [
					'kind'      => self::TAXONOMY_PACKAGE_KIND,
					'kind_slug' => self::TAXONOMY_PACKAGE_KIND_SLUG,
					'name'      => $this->packageId,
					'title'     => sprintf( self::TAXONOMY_PACKAGE_TITLE, $this->packageId ),
				];
			case self::OPTION_PAGE_PACKAGE_KIND_SLUG:
				return [
					'kind'      => self::OPTION_PAGE_PACKAGE_KIND,
					'kind_slug' => self::OPTION_PAGE_PACKAGE_KIND_SLUG,
					'name'      => $this->packageId,
					'title'     => sprintf( self::OPTION_PAGE_PACKAGE_TITLE, $this->packageId ),
				];
			case self::OPTION_PACKAGE_KIND_SLUG:
				return [
					'kind'      => self::OPTION_PACKAGE_KIND,
					'kind_slug' => self::OPTION_PACKAGE_KIND_SLUG,
					'name'      => $this->packageId,
					'title'     => self::OPTION_PACKAGE_NAMESPACE === $this->packageId ? self::OPTION_PACKAGE_TITLE_DEFAULT : sprintf( self::OPTION_PACKAGE_TITLE, $this->packageId ),
				];
			default:
				return [
					'kind'      => self::FIELD_GROUP_PACKAGE_KIND,
					'kind_slug' => self::FIELD_GROUP_PACKAGE_KIND_SLUG,
					'name'      => $this->packageId,
					'title'     => $this->title ?? sprintf( self::FIELD_GROUP_PACKAGE_TITLE, $this->packageId ),
				];
		}
	}

	public function withTitle( $title ) {
		$this->title = $title;

		return $this;
	}

	public function register( $value, $stringData ) {
		if ( $value ) {
			do_action( 'wpml_register_string', $value, self::getStringName( $value, $stringData ), $this->getPackageData(), Obj::prop( 'title', $stringData ), Obj::prop( 'type', $stringData ) );
		}
	}

	public function recordRegisteredStrings() {
		do_action( 'wpml_start_string_package_registration', $this->getPackageData() );
	}

	public function cleanupUnusedStrings() {
		do_action( 'wpml_delete_unused_package_strings', $this->getPackageData() );
	}

	public function translate( $value, $stringData ) {
		if ( $value ) {
			return apply_filters( 'wpml_translate_string', $value, self::getStringName( $value, $stringData ), $this->getPackageData() );
		}

		return $value;
	}

	public function delete() {
		$packageData = $this->getPackageData();
		do_action( 'wpml_delete_package', $packageData['name'], $packageData['kind'] );
	}

	public static function getStringName( $value, $meta ) {
		return $meta['namespace'] . '-' . $meta['id'] . '-' . $meta['key'] . '-' . md5( $value );
	}

	private function getWpmlPackage() {
		return Factory::createWpmlPackage( $this->getPackageData() );
	}

	public function exists(): bool {
		return (bool) $this->getWpmlPackage()->get_package_id();
	}

	public function flushCache() {
		$this->getWpmlPackage()->flush_cache();
		Cache::flushGroup( 'WPML_ST_CACHE' );
	}

	public function getUntranslatedStrings( $languageCode ) {
		$package = $this->getWpmlPackage();
		$strings = $package->get_package_strings();
		if ( ! $strings ) {
			return [];
		}

		$translated = $package->get_translated_strings( [] );

		$results = [];

		foreach ( $strings as $string ) {
			if ( ! isset( $translated[ $string->name ][ $languageCode ] ) ) {
				$results[ $string->name ] = $string;
			}
		}

		return $results;
	}

	public function setStringTranslations( $translations ) {
		do_action( 'wpml_set_translated_strings', $translations, $this->getPackageData() );
	}

	public function getStatus() {
		$getPackageStringsCount = Fns::memorize( function() {
			$strings = $this->getWpmlPackage()->get_package_strings();
			return is_array( $strings ) ? count( $strings ) : 0;
		} );

		$getTranslatedStrings = Fns::memorize( function() {
			return $this->getWpmlPackage()->get_translated_strings( [] );
		} );

		$isFullyTranslated = function() use ( $getPackageStringsCount, $getTranslatedStrings ) {
			$translatedStrings   = $getTranslatedStrings();
			$secondaryLangsCount = count( Languages::getSecondaries() );

			if ( count( $translatedStrings ) < $getPackageStringsCount() ) {
				return false;
			}

			foreach ( $translatedStrings as $translatedStringGroup ) {
				if ( count( $translatedStringGroup ) < $secondaryLangsCount ) {
					return false;
				}
			}

			return true;
		};

		if ( ! defined( 'WPML_ST_VERSION' ) ) {
			return self::STATUS_ST_INACTIVE;
		} elseif ( ! $getPackageStringsCount() ) {
			return self::STATUS_NOT_REGISTERED;
		} elseif ( ! $getTranslatedStrings() ) {
			return self::STATUS_NOT_TRANSLATED;
		} elseif ( ! $isFullyTranslated() ) {
			return self::STATUS_PARTIALLY_TRANSLATED;
		}

		return self::STATUS_FULLY_TRANSLATED;
	}

	public static function create( $packageId, $kind = self::FIELD_GROUP_PACKAGE_KIND_SLUG ) {
		return new self( $packageId, $kind );
	}

	public static function status2text( $status ) {
		switch ( $status ) {
			case self::STATUS_FULLY_TRANSLATED:
				$text = __( 'Complete', 'sitepress' );
				break;
			case self::STATUS_PARTIALLY_TRANSLATED:
				$text = __( 'In progress', 'sitepress' );
				break;
			default:
				$text = __( 'Not translated', 'sitepress' );
		}

		return $text;
	}

}
