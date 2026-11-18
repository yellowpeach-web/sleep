<?php

class WPML_ST_String_Positions_In_Page extends WPML_ST_String_Positions {

	const KIND = ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_PAGE;
	const KIND_FRONTEND = ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND;
	const KIND_FRONTEND_WHERE = ICL_STRING_TRANSLATION_STRING_TRACKING_TYPE_FRONTEND_WHERE;
	const TEMPLATE = 'positions-in-page.twig';

	private $string_factory;

	public function __construct(
		WPML_ST_String_Factory $string_factory,
		WPML_ST_DB_Mappers_String_Positions $string_position_mapper,
		IWPML_Template_Service $template_service
	) {
		$this->string_factory = $string_factory;
		parent::__construct( $string_position_mapper, $template_service );
	}

	protected function get_model( $string_id ) {
		return array(
			'pages' => $this->get_pages( $string_id ),
		);
	}

	protected function get_template_name() {
		return self::TEMPLATE;
	}

	private function get_pages( $string_id ) {
		$pages   = array();

		$string  = $this->string_factory->find_by_id( $string_id );
		$value   = $string->get_value();
		$context = $string->get_context();

		$urls = $this->get_mapper()->get_positions_by_string_and_kind( $string_id, self::KIND );
		$urls = array_merge(
			$urls,
			$this->get_mapper()->get_positions_by_string_and_kind( $string_id, self::KIND_FRONTEND ),
			$this->get_mapper()->get_positions_by_string_and_kind( $string_id, self::KIND_FRONTEND_WHERE )
		);
		$urls = array_values( array_unique( $urls ) );

		$urls = array_values(
			array_filter(
				$urls,
				function ( $address ) {
					return $this->is_safe_address( $address );
				}
			)
		);

		foreach ( $urls as $url ) {
			$pages[] = array(
				'iframe_url' => add_query_arg(
					array(
						'icl_string_track_value'   => rawurlencode( (string) $value ),
						'icl_string_track_context' => rawurlencode( (string) $context ),
					),
					$url
				),
				'url' => $url,
			);
		}

		return $pages;
	}

	private function is_safe_address( $address ) {
		if ( ! is_string( $address ) || '' === $address || preg_match( '/[\x00-\x1F\x7F]/', $address ) ) {
			return false;
		}

		if ( '/' === $address[0] ) {
			return 1 === strlen( $address ) || ( '/' !== $address[1] && '\\' !== $address[1] );
		}

		return 1 === preg_match( '#^https?://[^/\\\\?\#@\s]+(?:[/?\#]|$)#i', $address );
	}
}
