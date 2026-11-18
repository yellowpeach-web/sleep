<?php

namespace WPML\ST\MO;

use WP_Locale;

class WPML_Locale extends WP_Locale {
	const LAZY_PROPERTIES = [
		'weekday'             => [],
		'weekday_initial'     => [],
		'weekday_abbrev'      => [],
		'month'               => [],
		'month_genitive'      => [],
		'month_abbrev'        => [],
		'meridiem'            => [],
		'text_direction'      => 'ltr',
		'number_format'       => [],
		'list_item_separator' => null,
		'word_count_type'     => null,
	];

	private $initialized = false;

	public function __construct() {

		foreach ( array_keys( self::LAZY_PROPERTIES ) as $property ) {
			unset( $this->$property );
		}
	}

	private static function is_lazy_property( string $name ): bool {
		return array_key_exists( $name, self::LAZY_PROPERTIES ) && property_exists( WP_Locale::class, $name );
	}

	public function &__get( $name ) {
		if ( self::is_lazy_property( $name ) ) {
			$this->lazy_init();

			return $this->$name;
		}

		trigger_error( sprintf( 'Undefined property: %s::$%s', __CLASS__, esc_html( $name ) ), E_USER_WARNING );
		$value = null;

		return $value;
	}

	public function __isset( $name ) {
		if ( self::is_lazy_property( $name ) ) {
			$this->lazy_init();

			return isset( $this->$name );
		}

		return false;
	}

	private function lazy_init() {
		if ( ! $this->initialized ) {
			$this->initialized = true;

			$set_properties = get_object_vars( $this );
			foreach ( self::LAZY_PROPERTIES as $property => $default ) {
				if ( property_exists( WP_Locale::class, $property ) && ! array_key_exists( $property, $set_properties ) ) {
					$this->$property = $default;
				}
			}

			unset( $GLOBALS['text_direction'] );

			parent::__construct();
		}
	}

	public function get_weekday( $weekday_number ) {
		$this->lazy_init();

		return parent::get_weekday( $weekday_number );
	}

	public function get_weekday_initial( $weekday_name ) {
		$this->lazy_init();

		return parent::get_weekday_initial( $weekday_name );
	}

	public function get_weekday_abbrev( $weekday_name ) {
		$this->lazy_init();

		return parent::get_weekday_abbrev( $weekday_name );
	}

	public function get_month( $month_number ) {
		$this->lazy_init();

		return parent::get_month( $month_number );
	}

	public function get_month_abbrev( $month_name ) {
		$this->lazy_init();

		return parent::get_month_abbrev( $month_name );
	}

	public function get_month_genitive( $month_number ) {
		$this->lazy_init();

		return parent::get_month_genitive( $month_number );
	}

	public function get_meridiem( $meridiem ) {
		$this->lazy_init();

		return parent::get_meridiem( $meridiem );
	}

	public function is_rtl() {
		$this->lazy_init();

		return parent::is_rtl();
	}

	public function get_list_item_separator() {
		$this->lazy_init();

		return parent::get_list_item_separator();
	}

	public function get_word_count_type() {
		$this->lazy_init();

		return parent::get_word_count_type();
	}
}
