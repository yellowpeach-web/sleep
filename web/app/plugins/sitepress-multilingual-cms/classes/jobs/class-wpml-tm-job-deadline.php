<?php

class WPML_TM_Job_Deadline {

	const MYSQL_ZERO_DATE = '0000-00-00 00:00:00';

	public static function is_set( $value ) {
		return ! empty( $value ) && self::MYSQL_ZERO_DATE !== $value;
	}

	public static function normalize( $value ) {
		return self::is_set( $value ) ? $value : null;
	}

	public static function to_timestamp( $value ) {
		return self::is_set( $value ) ? (int) strtotime( $value ) : 0;
	}
}
