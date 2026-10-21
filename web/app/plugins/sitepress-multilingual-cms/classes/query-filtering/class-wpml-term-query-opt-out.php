<?php

class WPML_Term_Query_Opt_Out {

	const FILTER = 'wpml_bypass_term_query_id_adjustment';

	private static $queries_in_flight = array();

	private $sitepress;

	public function __construct( $sitepress = null ) {
		if ( ! $sitepress ) {
			global $sitepress;
		}

		$this->sitepress = $sitepress instanceof SitePress ? $sitepress : null;
	}

	public static function note_query_in_flight( $query ) {
		self::$queries_in_flight[] = $query;
	}

	public static function forget_query_in_flight() {
		array_pop( self::$queries_in_flight );
	}

	private static function innermost_query_in_flight() {
		return self::$queries_in_flight
			? self::$queries_in_flight[ count( self::$queries_in_flight ) - 1 ]
			: null;
	}

	public function is_stand_down_requested( $query = null ) {
		return $this->filtered( $this->query_suppresses_filters( $query ), $query );
	}

	public function is_id_adjustment_opted_out( $query = null ) {
		return $this->filtered(
			$this->query_suppresses_filters( $query ) || $this->ids_are_not_auto_adjusted(),
			$query
		);
	}

	private function filtered( $requested, $query ) {
		return (bool) apply_filters( self::FILTER, $requested, $query ? $query : self::innermost_query_in_flight() );
	}

	private function query_suppresses_filters( $query ) {
		$query = $query ? $query : self::innermost_query_in_flight();

		return is_object( $query ) && ! empty( $query->query_vars['suppress_filters'] );
	}

	private function ids_are_not_auto_adjusted() {
		return $this->sitepress && ! $this->sitepress->get_setting( 'auto_adjust_ids' );
	}
}
