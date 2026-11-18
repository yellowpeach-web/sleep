<?php

namespace WPML\ST\API;

class StringById implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const FILTER = 'wpml_st_get_string_by_id';

	private $strings;

	public function __construct( \WPML_ST_DB_Mappers_Strings $strings ) {
		$this->strings = $strings;
	}

	public function add_hooks() {
		add_filter( self::FILTER, [ $this, 'getStringById' ], 10, 2 );
	}

	public function getStringById( $default, $stringId ) {
		if ( is_int( $stringId ) ) {
			$id = $stringId;
		} elseif ( is_string( $stringId ) && ctype_digit( $stringId ) ) {
			$id = (int) $stringId;
		} else {
			return $default;
		}

		if ( $id < 1 ) {
			return $default;
		}

		$row = $this->strings->getById( $id );

		if ( ! is_object( $row ) ) {
			return $default;
		}

		return [
			'id'                => (int) $row->id,
			'name'              => (string) $row->name,
			'context'           => (string) $row->context,
			'language'          => (string) $row->language,
			'string_package_id' => empty( $row->string_package_id ) ? null : (int) $row->string_package_id,
		];
	}
}
