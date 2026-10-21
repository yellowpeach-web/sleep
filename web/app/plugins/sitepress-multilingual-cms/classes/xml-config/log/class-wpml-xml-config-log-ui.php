<?php

class WPML_XML_Config_Log_UI {
	private $template_service;
	private $log;

	function __construct( WPML_Config_Update_Log $log, IWPML_Template_Service $template_service ) {
		$this->log              = $log;
		$this->template_service = $template_service;
	}

	public function show() {
		$model = $this->get_model();

		return $this->template_service->show( $model, 'main.twig' );
	}

	private function get_model() {
		$entries = $this->log->get();
		krsort( $entries );

		$table_data = array();
		$columns    = array();
		foreach ( $entries as $entry ) {
			$columns += array_keys( $entry );
		}
		$columns = array_unique( $columns );

		foreach ( $entries as $timestamp => $entry ) {
			$table_row = array( 'timestamp' => $this->getDateTimeFromMicroseconds( $timestamp ) );

			foreach ( $columns as $column ) {
				$table_row[ $column ] = null;
				if ( array_key_exists( $column, $entry ) ) {
					$table_row[ $column ] = $entry[ $column ];
				}
			}
			$table_data[] = $table_row;
		}

		array_unshift( $columns, 'timestamp' );

		$model = array(
			'strings' => array(
				'title'     => __( 'Remote XML Config Log', 'sitepress' ),
				'message'   => __( "WPML needs to load configuration files, which tell it how to translate your theme and the plugins that you use. If there's a problem, use the Update button on Dashboard → Updates to load them again. If the problem continues, contact WPML support, show the error details and we'll help you resolve it.", 'sitepress' ),
				/* translators: Link that unfolds the further information about a row. */
				'details'   => __( 'Details', 'sitepress' ),
				'empty_log' => __( 'The remote XML Config Log is empty', 'sitepress' ),
			),
			'columns' => $columns,
			'entries' => $table_data,
		);

		return $model;
	}

	private function getDateTimeFromMicroseconds( $time ) {
		$dFormat = 'Y-m-d H:i:s';

		if ( strpos( $time, '.' ) === false ) {
			return $time;
		}

		list( $sec, $usec ) = explode( '.', $time );

		return date( $dFormat, (int) $sec ) . $usec;
	}
}
