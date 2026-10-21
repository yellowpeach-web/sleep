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

	public function __construct( SitePress $sitepress, StringLanguage $stringLanguage, IndexableHooks $indexableHooks ) {
		$this->sitepress      = $sitepress;
		$this->stringLanguage = $stringLanguage;
		$this->indexableHooks = $indexableHooks;
	}

	public function add_hooks() {
		WPHooks::onFilter( 'wpml_translatable_term_meta_values', 10, 3 )
			->then( spreadArgs( [ $this, 'addTermJobValues' ] ) );

		WPHooks::onFilter( 'wpml_apply_translated_term_meta', 10, 5 )
			->then( spreadArgs( [ $this, 'applyTranslatedValue' ] ) );

		WPHooks::onAction( 'update_option', 10, 3 )
			->then( spreadArgs( [ $this, 'onOptionUpdated' ] ) );
	}

	public function addTermJobValues( $values, $termId, $taxonomy ) {
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

		if ( ! function_exists( 'icl_get_string_id' ) || ! function_exists( 'icl_add_string_translation' ) ) {
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
		if ( $term instanceof \WP_Term ) {
			do_action(
				'wpml_register_string',
				$sourceValue,
				$stringName,
				Hooks::PACKAGE,
				Hooks::FIELDS[ $key ] . ': ' . $term->name,
				'LINE'
			);

			$this->setSourceStringLanguage( $stringName, $term );
		}

		$stringId = icl_get_string_id( $sourceValue, Hooks::stringContext(), $stringName );
		if ( $stringId ) {
			icl_add_string_translation( $stringId, $targetLang, (string) $value, ICL_TM_COMPLETE );
			$this->invalidateTermIndexable( (int) $targetTermId );
		}

		return true;
	}

	private function setSourceStringLanguage( $stringName, \WP_Term $sourceTerm ) {
		$language = $this->stringLanguage->getTermLanguage( $sourceTerm->term_taxonomy_id, $sourceTerm->taxonomy );
		if ( $language ) {
			$this->stringLanguage->apply( [ $stringName => $language ] );
		}
	}

	private function invalidateTermIndexable( $termId ) {
		if ( ! $termId || ! function_exists( 'YoastSEO' ) || ! class_exists( '\Yoast\WP\SEO\Repositories\Indexable_Repository' ) ) {
			return;
		}

		try {
			$yoastSEO = YoastSEO();
			$classes    = $yoastSEO->classes;
			$repository = $classes->get( \Yoast\WP\SEO\Repositories\Indexable_Repository::class );
			$indexable  = $repository->find_by_id_and_type( $termId, 'term', false );
			if ( $indexable ) {
				$indexable->version = 0;
				$indexable->save();

				$rebuild = function () use ( $repository, $termId ) {
					Hooks::withoutTermAdjustId(
						function () use ( $repository, $termId ) {
							$repository->find_by_id_and_type( $termId, 'term' );
						}
					);
				};

				Hooks::withForcedTranslation( $rebuild );
			}
		} catch ( \Throwable $e ) {
			return;
		}
	}

	public function onOptionUpdated( $option, $oldValue, $value ) {
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
		$optionName = $this->getWpSeoOptionName();
		if ( ! $optionName ) {
			return null;
		}

		$all = get_option( $optionName );
		if ( ! is_array( $all ) || ! isset( $all[ $taxonomy ][ $termId ] ) || ! is_array( $all[ $taxonomy ][ $termId ] ) ) {
			return null;
		}

		return $all[ $taxonomy ][ $termId ];
	}

	private function getWpSeoOptionName() {
		if ( null === $this->wpSeoOptionName && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			$this->wpSeoOptionName = WPSEO_Taxonomy_Meta::get_instance()->option_name;
		}

		return $this->wpSeoOptionName;
	}
}
