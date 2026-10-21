<?php

abstract class WPML_Display_As_Translated_Query {

	const COLUMN_REFERENCE_PATTERN = '/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/';

	protected $wpdb;

	protected $icl_translation_table_alias;

	public function __construct( wpdb $wpdb, $icl_translation_table_alias = 'wpml_translations' ) {
		$this->wpdb = $wpdb;
		$this->icl_translation_table_alias = in_array( $icl_translation_table_alias, [ 'wpml_translations', 'icl_t' ], true )
			? $icl_translation_table_alias
			: 'wpml_translations';
	}

	public function get_icl_translations_table_alias() {
		return is_string( $this->icl_translation_table_alias ) && ! empty( $this->icl_translation_table_alias )
			? $this->icl_translation_table_alias
			: 'wpml_translations';
	}

	public function get_language_snippet( $current_language, $fallback_language, $content_types, $skip_content_type_check = false ) {
		return $this->get_language_snippet_for_expression(
			(string) $this->wpdb->prepare( '%s', $current_language ),
			$fallback_language,
			$content_types,
			$skip_content_type_check
		);
	}

	public function get_language_snippet_for_column( $language_column, $fallback_language, $content_types, $skip_content_type_check = false ) {
		if ( ! preg_match( self::COLUMN_REFERENCE_PATTERN, $language_column ) ) {
			throw new InvalidArgumentException( 'Expected a plain column reference, got: ' . esc_html( $language_column ) );
		}

		return $this->get_language_snippet_for_expression( $language_column, $fallback_language, $content_types, $skip_content_type_check );
	}

	private function get_language_snippet_for_expression( $language_expression, $fallback_language, $content_types, $skip_content_type_check = false ) {
		if ( ! $fallback_language || ( ! $skip_content_type_check && ! $content_types ) ) {
			return '0';
		}

		$content_types_query = $skip_content_type_check ? '' : 'AND ' . $this->get_content_types_query( $content_types );

		$sub_query_no_translation            = $this->get_query_for_no_translation( $language_expression );
		$sub_query_translation_not_published = $this->get_query_for_translation_not_published( $language_expression );

		$language_condition = 'icl_t' === $this->icl_translation_table_alias
			? $this->wpdb->prepare( 'icl_t.language_code = %s', $fallback_language )
			: $this->wpdb->prepare( 'wpml_translations.language_code = %s', $fallback_language );

		return "(
					{$language_condition}
					{$content_types_query}
					AND ( ( {$sub_query_no_translation} ) OR ( {$sub_query_translation_not_published} ) )
				)";
	}

	private function get_query_for_no_translation( $language_expression ) {
		return "
			( SELECT COUNT(element_id)
			  FROM {$this->wpdb->prefix}icl_translations
			  WHERE trid = {$this->icl_translation_table_alias}.trid
			  AND language_code = {$language_expression}
			) = 0
			";
	}

	abstract protected function get_content_types_query( $content_types );

	abstract protected function get_query_for_translation_not_published( $language_expression );

}
