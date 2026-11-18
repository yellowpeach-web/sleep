<?php

class WPML_TM_Jobs_Sorting_Param {
	private $column;

	private $direction;

	public function __construct( $column, $direction = 'asc' ) {
		$this->column    = $column;
		$this->direction = is_string( $direction ) && 'desc' === strtolower( $direction ) ? 'desc' : 'asc';
	}

	public function get_column() {
		return $this->column;
	}

	public function get_direction() {
		return $this->direction;
	}
}
