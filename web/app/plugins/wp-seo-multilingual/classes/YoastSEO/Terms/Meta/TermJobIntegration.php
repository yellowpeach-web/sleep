<?php

namespace WPML\WPSEO\YoastSEO\Terms\Meta;

use SitePress;
use WPSEO_Taxonomy_Meta;
use WPML\LIB\WP\Hooks as WPHooks;
use WPML\WPSEO\YoastSEO\Indexable\Hooks as IndexableHooks;

use function WPML\FP\spreadArgs;

class TermJobIntegration implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_AJAX_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	private $sitepress;

	private $wpSeoOptionName;

	private $stringLanguage;

	private $indexableHooks;

	private $hasOptionValue = false;

	private $optionValue;

	public function __construct( SitePress $sitepress, StringLanguage $stringLanguage, IndexableHooks $indexableHooks ) {
		$this->sitepress      = $sitepress;
		$this->stringLanguage = $stringLanguage;
		$this->indexableHooks = $indexableHooks;
	}

	public function add_hooks() {
		WPHooks::onAction( 'wpml_string_package_owner_changed', 10, 3 )
			->then( spreadArgs( [ $this, 'invalidateTermIndexablesOnOwnerChange' ] ) );
		WPHooks::onFilter( 'wpml_translatable_term_meta_values', 10, 3 )
			->then( spreadArgs( [ $this, 'addTermJobValues' ] ) );
		WPHooks::onAction( 'update_option', 10, 3 )
			->then( spreadArgs( [ $this, 'onOptionUpdated' ] ) );

		WPHooks::onFilter( 'wpml_apply_translated_term_meta', 10, 5 )
			->then( spreadArgs( [ $this, 'applyTranslatedValue' ] ) );

		WPHooks::onAction( 'update_option', 10, 1 )
			->then( spreadArgs( [ $this, 'forgetOptionValue' ] ) );
		WPHooks::onAction( 'add_option', 10, 1 )
			->then( spreadArgs( [ $this, 'forgetOptionValue' ] ) );
		WPHooks::onAction( 'delete_option', 10, 1 )
			->then( spreadArgs( [ $this, 'forgetOptionValue' ] ) );
	}

	public function addTermJobValues( $values, $termId, $taxonomy ) {
		if ( \WPML\WPSEO\YoastSEO\Loaders::supportsPerTermPackages() ) {
			return $values;
		}
		if ( ! is_array( $values ) || ! $termId || ! $taxonomy ) {
			return $values;
		}

		$termMeta = $this->getTermEntry( (string) $taxonomy, (int) $termId );
		if ( ! $termMeta ) {
			return $values;
		}

		foreach ( array_keys( Hooks::FIELDS ) as $field ) {
			if ( ! empty( $termMeta[ $field ] ) && is_string( $termMeta[ $field ] ) ) {
				$values[ $field ] = $termMeta[ $field ];
			}
		}

		return $values;
	}

	public function applyTranslatedValue( $handled, $targetTermId, $key, $value, $context ) {
		if ( true === $handled ) {
			return $handled;
		}

		if ( ! array_key_exists( (string) $key, Hooks::FIELDS ) ) {
			return $handled;
		}

		$sourceTermId = isset( $context['sourceTermId'] ) ? (int) $context['sourceTermId'] : 0;
		$taxonomy     = isset( $context['taxonomy'] ) ? (string) $context['taxonomy'] : '';
		$targetLang   = isset( $context['targetLang'] ) ? (string) $context['targetLang'] : '';

		if ( ! $sourceTermId || ! $taxonomy || ! $targetLang ) {
			return true;
		}

		$perTermPackages = \WPML\WPSEO\YoastSEO\Loaders::supportsPerTermPackages();
		if ( ! function_exists( 'icl_add_string_translation' ) ) {
			return true;
		}

		$termMeta    = $this->getTermEntry( $taxonomy, $sourceTermId );
		$sourceValue = isset( $termMeta[ $key ] ) && is_string( $termMeta[ $key ] ) ? $termMeta[ $key ] : '';
		if ( '' === $sourceValue ) {
			return true;
		}

		$stringName = Hooks::buildStringName( $taxonomy, $sourceTermId, (string) $key );

		$term = Hooks::withoutTermAdjustId(
			function () use ( $sourceTermId, $taxonomy ) {
				return get_term( $sourceTermId, $taxonomy );
			}
		);
		if ( $perTermPackages && $term instanceof \WP_Term && ! apply_filters( 'wpml_tm_term_units_in_flight', [], (int) $term->term_taxonomy_id, 't_' ) ) {
			return $handled;
		}

		if ( $term instanceof \WP_Term ) {
			$package = Hooks::PACKAGE;
			if ( $perTermPackages ) {
				$package['name'] = 'term-' . $term->term_taxonomy_id;
			}
			do_action(
				'wpml_register_string',
				$sourceValue,
				$stringName,
				$package,
				Hooks::FIELDS[ $key ] . ': ' . $term->name,
				'LINE'
			);

			$this->setSourceStringLanguage( $stringName, $term );
		}

		if ( $perTermPackages ) {
			if ( ! $term instanceof \WP_Term ) {
				return true;
			}
			$stringId = (int) apply_filters( 'wpml_string_id', null, [ 'context' => Hooks::stringContext( $term->term_taxonomy_id ), 'name' => $stringName ] );
		} elseif ( function_exists( 'icl_get_string_id' ) ) {
			$stringId = icl_get_string_id( $sourceValue, Hooks::stringContext(), $stringName );
		} else {
			return true;
		}
		if ( $stringId ) {
			icl_add_string_translation( $stringId, $targetLang, (string) $value, ICL_TM_COMPLETE );
			$this->indexableHooks->rebuildTermIndexable( (int) $targetTermId );
		}

		return true;
	}

	private function setSourceStringLanguage( $stringName, \WP_Term $sourceTerm ) {
		$language = $this->stringLanguage->getTermLanguage( $sourceTerm->term_taxonomy_id, $sourceTerm->taxonomy );
		if ( $language ) {
			$ttId = \WPML\WPSEO\YoastSEO\Loaders::supportsPerTermPackages() ? $sourceTerm->term_taxonomy_id : null;
			$this->stringLanguage->apply( [ $stringName => $language ], $ttId );
		}
	}

	public function onOptionUpdated( $option, $oldValue, $value ) {
		if ( \WPML\WPSEO\YoastSEO\Loaders::supportsPerTermPackages() ) {
			return;
		}
		if ( $option !== $this->getWpSeoOptionName() || ! is_array( $value ) ) {
			return;
		}

		$oldValue = is_array( $oldValue ) ? $oldValue : [];

		foreach ( $value as $taxonomy => $terms ) {
			if ( ! is_array( $terms ) || ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
				continue;
			}

			$oldTerms = isset( $oldValue[ $taxonomy ] ) && is_array( $oldValue[ $taxonomy ] ) ? $oldValue[ $taxonomy ] : [];

			foreach ( $terms as $termId => $termMeta ) {
				if ( ! is_array( $termMeta ) ) {
					continue;
				}

				$oldTermMeta = isset( $oldTerms[ $termId ] ) && is_array( $oldTerms[ $termId ] ) ? $oldTerms[ $termId ] : [];

				if ( $this->translatableFieldsChanged( $oldTermMeta, $termMeta ) ) {
					do_action( 'wpml_taxonomy_term_content_changed', (int) $termId );
					$this->invalidateTranslatedTermIndexablesOfSourceTerm( (string) $taxonomy, (int) $termId );
				}
			}
		}
	}

	public function invalidateTermIndexablesOnOwnerChange( $elementType, $elementId ) {
		if ( ! \WPML\WPSEO\YoastSEO\Loaders::supportsPerTermPackages() ) {
			return;
		}
		if ( 0 !== strpos( (string) $elementType, 'tax_' ) ) {
			return;
		}
		$taxonomy = substr( $elementType, 4 );
		$term     = Hooks::withoutTermAdjustId(
			function () use ( $elementId, $taxonomy ) {
				return get_term_by( 'term_taxonomy_id', (int) $elementId, $taxonomy );
			}
		);
		if ( $term instanceof \WP_Term ) {
			$this->invalidateTranslatedTermIndexablesOfSourceTerm( $taxonomy, (int) $term->term_id );
		}
	}

	private function invalidateTranslatedTermIndexablesOfSourceTerm( string $taxonomy, int $termId ) {
		$term = Hooks::withoutTermAdjustId(
			function () use ( $termId, $taxonomy ) {
				return get_term( $termId, $taxonomy );
			}
		);

		if ( $term instanceof \WP_Term && $this->sitepress->is_original_content_filter( false, (int) $term->term_taxonomy_id, 'tax_' . $taxonomy ) ) {
			$this->indexableHooks->invalidateTranslatedTermIndexables( $taxonomy, $termId );
		}
	}

	private function translatableFieldsChanged( array $oldMeta, array $newMeta ) {
		foreach ( array_keys( Hooks::FIELDS ) as $field ) {
			$oldValue = isset( $oldMeta[ $field ] ) ? (string) $oldMeta[ $field ] : '';
			$newValue = isset( $newMeta[ $field ] ) ? (string) $newMeta[ $field ] : '';
			if ( $oldValue !== $newValue ) {
				return true;
			}
		}

		return false;
	}

	private function getTermEntry( $taxonomy, $termId ) {
		$all = $this->getOptionValue();
		if ( ! is_array( $all ) || ! isset( $all[ $taxonomy ][ $termId ] ) || ! is_array( $all[ $taxonomy ][ $termId ] ) ) {
			return null;
		}

		return $all[ $taxonomy ][ $termId ];
	}

	private function getOptionValue() {
		if ( ! $this->hasOptionValue ) {
			$optionName = $this->getWpSeoOptionName();
			if ( ! $optionName ) {
				return null;
			}

			$this->optionValue = Hooks::withStoredValues(
				function () use ( $optionName ) {
					return get_option( $optionName );
				}
			);
			$this->hasOptionValue = true;
		}

		return $this->optionValue;
	}

	public function forgetOptionValue( $option ) {
		if ( $option === $this->getWpSeoOptionName() ) {
			$this->hasOptionValue = false;
			$this->optionValue    = null;
		}
	}

	private function getWpSeoOptionName() {
		if ( null === $this->wpSeoOptionName && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			$this->wpSeoOptionName = WPSEO_Taxonomy_Meta::get_instance()->option_name;
		}

		return $this->wpSeoOptionName;
	}
}
