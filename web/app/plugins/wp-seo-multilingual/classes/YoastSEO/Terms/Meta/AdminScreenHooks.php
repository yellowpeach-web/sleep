<?php

namespace WPML\WPSEO\YoastSEO\Terms\Meta;

use SitePress;
use WPML\Element\API\Translations;
use WPML\LIB\WP\Hooks as WPHooks;

use function WPML\FP\spreadArgs;

class AdminScreenHooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const PRIORITY_BEFORE_YOAST_SAVES_TERM_META = 50;

	const ASSET_HANDLE = 'wpseoml-term-meta-lock';

	private $sitepress;

	private $termMetaHooks;

	private $lockedFields;

	private $overriddenFields;

	public function __construct( SitePress $sitepress, Hooks $termMetaHooks ) {
		$this->sitepress     = $sitepress;
		$this->termMetaHooks = $termMetaHooks;
	}

	public function add_hooks() {
		WPHooks::onAction( 'current_screen' )
			->then( spreadArgs( [ $this, 'setupTermScreen' ] ) );

		WPHooks::onAction( 'edit_term', self::PRIORITY_BEFORE_YOAST_SAVES_TERM_META, 3 )
			->then( spreadArgs( [ $this, 'dropUnchangedServedValues' ] ) );
	}

	public function setupTermScreen( $screen ) {
		if ( 'term' !== $screen->base || ! $this->sitepress->is_translated_taxonomy( $screen->taxonomy ) ) {
			return;
		}

		$termId = (int) ( $_GET['tag_ID'] ?? 0 );
		$source = $termId ? $this->getSourceTerm( $termId, $screen->taxonomy ) : null;

		if ( ! $source ) {
			return;
		}

		$states                 = $this->getFieldStates( $screen->taxonomy, $termId, $source );
		$this->lockedFields     = $states['locked'];
		$this->overriddenFields = $states['overridden'];

		Hooks::enableAdminScreenTranslation();

		WPHooks::onAction( 'admin_enqueue_scripts' )
			->then( spreadArgs( [ $this, 'enqueueLockAssets' ] ) );
	}

	public function dropUnchangedServedValues( $termId, $ttId, $taxonomy ) {
		if ( ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
			return;
		}

		$source = $this->getSourceTerm( $termId, $taxonomy );
		if ( ! $source ) {
			return;
		}

		foreach ( array_keys( Hooks::FIELDS ) as $field ) {
			if ( ! isset( $_POST[ $field ] ) || ! is_string( $_POST[ $field ] ) ) {
				continue;
			}

			$servedValues = $this->termMetaHooks->getServedValues( $taxonomy, $source['termId'], $field, $source['targetLang'] );

			if ( in_array( wp_unslash( $_POST[ $field ] ), $servedValues, true ) ) {
				unset( $_POST[ $field ] );
			}
		}
	}

	public function enqueueLockAssets() {
		$baseUrl = plugins_url( '', WPSEOML_PLUGIN_PATH . '/plugin.php' );

		wp_enqueue_style(
			self::ASSET_HANDLE,
			$baseUrl . '/res/css/term-meta-lock.css',
			[],
			WPSEOML_VERSION
		);

		wp_enqueue_script(
			self::ASSET_HANDLE,
			$baseUrl . '/res/js/term-meta-lock.js',
			[],
			WPSEOML_VERSION,
			true
		);

		wp_localize_script(
			self::ASSET_HANDLE,
			'wpseomlTermMetaLock',
			[
				'lockedFields'     => $this->lockedFields,
				'overriddenFields' => $this->overriddenFields,
				'i18n'             => [
					'lockedLabel'  => /* translators: Label shown on a Yoast SEO field on the term editing screen when WPML supplies the translation and the field is read-only. */ __( 'Translated by WPML', 'wp-seo-multilingual' ),
					'lockedHint'   => /* translators: Explanation under that label. "Edit anyway" is the button below it, so translate the two the same way. "WPML String Translation" is a product name and stays in English. */ __( 'This field shows the translation that WPML String Translation serves. Click "Edit anyway" to override it.', 'wp-seo-multilingual' ),
					'editAnyway'   => /* translators: Button beside a read-only Yoast SEO field on the term editing screen; it unlocks the field for typing. Verb, imperative. The explanation above quotes it, so translate the two the same way. */ __( 'Edit anyway', 'wp-seo-multilingual' ),
					'overrideHint' => /* translators: Explanation shown on a Yoast SEO field on the term editing screen once the user has typed over WPML's translation; "it" is the field's text. */ __( 'Your text overrides the WPML translation. Leave the field empty to let WPML translate it.', 'wp-seo-multilingual' ),
				],
			]
		);
	}

	private function getSourceTerm( $termId, $taxonomy ) {
		$term = get_term( $termId, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		$original = Translations::getOriginal( $term->term_taxonomy_id, "tax_$taxonomy" );

		if ( ! $original || (int) $original->element_id === (int) $term->term_taxonomy_id ) {
			return null;
		}

		$sourceTerm = get_term_by( 'term_taxonomy_id', $original->element_id, $taxonomy );
		if ( ! $sourceTerm ) {
			return null;
		}

		$languageDetails = $this->sitepress->get_element_language_details( $term->term_taxonomy_id, "tax_$taxonomy" );

		return [
			'termId'     => (int) $sourceTerm->term_id,
			'targetLang' => $languageDetails ? (string) $languageDetails->language_code : '',
		];
	}

	private function getFieldStates( $taxonomy, $termId, array $source ) {
		$option = get_option( \WPSEO_Taxonomy_Meta::get_instance()->option_name );
		$states = [
			'locked'     => [],
			'overridden' => [],
		];

		foreach ( array_keys( Hooks::FIELDS ) as $field ) {
			$ownValue    = $option[ $taxonomy ][ $termId ][ $field ] ?? '';
			$sourceValue = $option[ $taxonomy ][ $source['termId'] ][ $field ] ?? '';
			$isServed    = $this->isServed( $taxonomy, $source, $field, $sourceValue );

			$states['locked'][ $field ]     = '' === $ownValue && $isServed;
			$states['overridden'][ $field ] = '' !== $ownValue && $isServed;
		}

		return $states;
	}

	private function isServed( $taxonomy, array $source, $field, $sourceValue ) {
		if ( ! Hooks::isServedOnlyWhenTranslated( $field ) ) {
			return '' !== $sourceValue;
		}

		return [] !== $this->termMetaHooks->getServedValues( $taxonomy, $source['termId'], $field, $source['targetLang'] );
	}
}
