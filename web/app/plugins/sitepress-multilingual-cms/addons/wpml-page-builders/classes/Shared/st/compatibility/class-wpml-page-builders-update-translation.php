<?php

use WPML\PB\Helper\TranslationKses;
use WPML\TM\ATE\Receive\UnfilteredHtmlGrant;

abstract class WPML_Page_Builders_Update_Translation extends WPML_Page_Builders_Update {

	const TRANSLATION_COMPLETE = 10;

	protected $translatable_nodes;

	private $string_translations;
	private $lang;

	public function __construct(
		IWPML_Page_Builders_Translatable_Nodes $translatable_nodes,
		IWPML_Page_Builders_Data_Settings $data_settings
	) {
		$this->translatable_nodes = $translatable_nodes;
		parent::__construct( $data_settings );
	}

	public function update( $translated_post_id, $original_post, $string_translations, $lang ) {
		$this->string_translations = self::filter_ate_translations( $string_translations, $lang );
		$this->lang                = $lang;

		$converted_data = $this->get_converted_data( $original_post->ID );
		$this->update_strings_in_modules( $converted_data );
		$this->save( $translated_post_id, $original_post->ID, $converted_data );

	}

	protected function get_lang() {
		return $this->lang;
	}

	protected function get_string_translations() {
		return $this->string_translations;
	}

	private static function filter_ate_translations( array $string_translations, string $lang ): array {
		$is_ate_delivery   = apply_filters( UnfilteredHtmlGrant::FILTER_DELIVERY, false );
		$may_keep_raw_html = apply_filters( UnfilteredHtmlGrant::FILTER_ALLOW, false );

		if ( ! $is_ate_delivery || $may_keep_raw_html ) {
			return $string_translations;
		}

		foreach ( $string_translations as $name => $translations ) {
			$value = $translations[ $lang ]['value'] ?? null;

			if ( is_string( $value ) ) {
				$string_translations[ $name ][ $lang ]['value'] = TranslationKses::filter( $value );
			}
		}

		return $string_translations;
	}

	protected function get_translation( WPML_PB_String $string ) {
		if ( array_key_exists( $string->get_name(), $this->string_translations ) &&
		     array_key_exists( $this->lang, $this->string_translations[ $string->get_name() ] ) ) {
			$translation = $this->string_translations[ $string->get_name() ][ $this->lang ];
			$string->set_value( $translation['value'] );
		}

		return $string;
	}

	abstract protected function update_strings_in_modules( array &$data_array );
	abstract protected function update_strings_in_node( $node_id, $settings );
}