<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\FP\Fns;
use WPML\FP\Str;

class Frontend implements \IWPML_Frontend_Action {

	const PERMALINKS_CATEGORY_PATTERN = '%category%';

	const FORM_WIDGET_NAME = 'form';

	const FORM_CLOSING_TAG = '</form>';

	public function add_hooks() {
		add_action( 'elementor_pro/search_form/after_input', [ $this, 'addLanguageFormField' ] );
		add_filter( 'elementor/widget/render_content', [ $this, 'addLanguageFieldToForm' ], 10, 2 );
		if ( Str::includes( self::PERMALINKS_CATEGORY_PATTERN, get_option( 'permalink_structure' ) ) ) {
			add_filter( 'post_link_category', Fns::memorize( [ $this, 'fixLanguageSwitcherPermalink' ] ), 10, 3 );
		}
		add_action( 'elementor/widget/before_render_content', [ $this, 'handleWidgetTextFilters' ] );
	}

	public function addLanguageFormField() {
		do_action( 'wpml_add_language_form_field' );
	}

	public function addLanguageFieldToForm( $content, $widget ) {
		if ( self::FORM_WIDGET_NAME !== $widget->get_name() ) {
			return $content;
		}

		return Str::replace( self::FORM_CLOSING_TAG, wpml_get_language_form_field() . self::FORM_CLOSING_TAG, $content );
	}

	public function fixLanguageSwitcherPermalink( $cat, $cats, $post ) {
		$postLang = apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $post->ID,
				'element_type' => $post->post_type,
			]
		);
		$catLang  = apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $cat->term_id,
				'element_type' => $cat->taxonomy,
			]
		);

		if ( $postLang !== $catLang ) {
			$convertedCatId = apply_filters( 'wpml_object_id', $cat->term_id, $cat->taxonomy, true, $postLang );
			return get_term( $convertedCatId, $cat->taxonomy );
		}

		return $cat;
	}

	public function handleWidgetTextFilters() {
		$isRemoved = remove_filter( 'widget_text', 'icl_sw_filters_widget_text', 0 );

		if ( ! $isRemoved ) {
			return;
		}

		add_filter( 'elementor/widget/render_content', [ $this, 'restoreWidgetTextFilter' ], PHP_INT_MAX );
	}

	public function restoreWidgetTextFilter( $content ) {
		add_filter( 'widget_text', 'icl_sw_filters_widget_text', 0 );
		remove_filter( 'elementor/widget/render_content', [ $this, 'restoreWidgetTextFilter' ], PHP_INT_MAX );

		return $content;
	}
}
