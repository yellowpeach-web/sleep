<?php

namespace WPML\TaxonomyTermTranslation;

class WritingScreenCategoryControls implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const RENDER_PAGE = 'options-writing.php';

	const SAVE_PAGE = 'options.php';

	const OPTION_PAGE = 'writing';

	const SITE_WIDE_CONTROLS = array( 'default_email_category', 'default_link_category' );

	const DEFAULT_CATEGORY = 'default_category';

	private $sitepress;

	private $re_rendering = false;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public static function isWritingScreenRequest(): bool {
		$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';

		if ( self::RENDER_PAGE !== $pagenow && self::SAVE_PAGE !== $pagenow ) {
			return false;
		}

		if ( ! is_admin() ) {
			return false;
		}

		if ( self::RENDER_PAGE === $pagenow ) {
			return true;
		}

		return self::OPTION_PAGE === filter_input( INPUT_POST, 'option_page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	}

	private static function isWritingScreenRender(): bool {
		return self::isWritingScreenRequest()
			&& self::RENDER_PAGE === ( isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '' );
	}

	public function add_hooks() {
		if ( ! self::isWritingScreenRender() ) {
			return;
		}

		add_filter( 'wp_dropdown_cats', array( $this, 'siteWideDropdown' ), 10, 2 );
	}

	public function siteWideDropdown( $output, $args ) {
		if ( $this->re_rendering || ! is_array( $args ) ) {
			return $output;
		}

		$name = isset( $args['name'] ) ? (string) $args['name'] : '';

		if ( in_array( $name, self::SITE_WIDE_CONTROLS, true ) ) {
			return $this->groupedByLanguage( $this->inEveryLanguage( $args ), $args );
		}

		if ( self::DEFAULT_CATEGORY === $name ) {
			return $this->inDefaultLanguage( $args );
		}

		return $output;
	}

	private function inEveryLanguage( array $args ) {
		$standDown = array( $this, 'standDown' );
		add_filter( \WPML_Term_Query_Opt_Out::FILTER, $standDown, 10, 1 );

		try {
			return $this->reRender( $args );
		} finally {
			remove_filter( \WPML_Term_Query_Opt_Out::FILTER, $standDown, 10 );
		}
	}

	private function groupedByLanguage( $markup, array $args ) {
		$markup = (string) $markup;

		if ( ! preg_match_all( '#<option\b[^>]*>.*?</option>#s', $markup, $matches ) ) {
			return $markup;
		}

		$first = strpos( $markup, '<option' );
		$last  = strrpos( $markup, '</option>' );

		if ( false === $first || false === $last ) {
			return $markup;
		}

		$language_of = $this->termLanguages( $args );

		if ( array() === $language_of ) {
			return $markup;
		}

		$active = $this->sitepress->get_active_languages();
		$active = is_array( $active ) ? $active : array();

		$grouped   = array();
		$remainder = array();

		foreach ( $matches[0] as $option ) {
			$term_id = preg_match( '#\bvalue=["\']?([^"\'\s>]*)#', $option, $value ) ? (int) $value[1] : 0;
			$code    = isset( $language_of[ $term_id ] ) ? $language_of[ $term_id ] : '';

			if ( '' !== $code && isset( $active[ $code ] ) ) {
				$grouped[ $code ][] = $option;
			} else {
				$remainder[] = $option;
			}
		}

		if ( array() === $grouped ) {
			return $markup;
		}

		$body = '';

		foreach ( $active as $code => $language ) {
			if ( empty( $grouped[ $code ] ) ) {
				continue;
			}

			$label = is_array( $language ) && isset( $language['display_name'] ) && '' !== (string) $language['display_name']
				? (string) $language['display_name']
				: ( is_array( $language ) && isset( $language['english_name'] ) ? (string) $language['english_name'] : (string) $code );

			$body .= "\n" . '<optgroup label="' . esc_attr( $label ) . '">'
				. "\n\t" . implode( "\n\t", $grouped[ $code ] ) . "\n" . '</optgroup>';
		}

		if ( array() !== $remainder ) {
			$body .= "\n" . '<optgroup label="' . esc_attr( __( 'No language', 'sitepress' ) ) . '">'
				. "\n\t" . implode( "\n\t", $remainder ) . "\n" . '</optgroup>';
		}

		return substr( $markup, 0, $first ) . ltrim( $body, "\n" ) . substr( $markup, $last + strlen( '</option>' ) );
	}

	private function termLanguages( array $args ) {
		global $wpdb;

		$taxonomy = isset( $args['taxonomy'] ) && is_string( $args['taxonomy'] ) && '' !== $args['taxonomy']
			? $args['taxonomy']
			: 'category';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_id, t.language_code
				 FROM {$wpdb->prefix}icl_translations t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = t.element_id
				 WHERE t.element_type = %s",
				'tax_' . $taxonomy
			)
		);

		$language_of = array();

		foreach ( (array) $rows as $row ) {
			if ( isset( $row->term_id, $row->language_code ) ) {
				$language_of[ (int) $row->term_id ] = (string) $row->language_code;
			}
		}

		return $language_of;
	}

	public function standDown() {
		return true;
	}

	private function inDefaultLanguage( array $args ) {
		$ids = $this->defaultLanguageCategoryIds();
		if ( array() === $ids ) {
			return $this->reRender( $args );
		}
		$args['include'] = $ids;

		return $this->inEveryLanguage( $args );
	}

	private function defaultLanguageCategoryIds() {
		global $wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tt.term_id
				 FROM {$wpdb->prefix}icl_translations t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = t.element_id
				 WHERE t.element_type = 'tax_category' AND t.language_code = %s",
				$this->sitepress->get_default_language()
			)
		);

		return array_values( array_map( 'intval', is_array( $ids ) ? $ids : array() ) );
	}

	private function reRender( array $args ) {
		$args['echo'] = 0;

		$this->re_rendering = true;

		try {
			return (string) wp_dropdown_categories( $args );
		} finally {
			$this->re_rendering = false;
		}
	}
}
