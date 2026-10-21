<?php

class WPML_TM_Jobs_List_Services {
	private $wpdb;

	private $service_names;

	private $cache;

	public function __construct( WPML_TM_Rest_Jobs_Translation_Service $service_names ) {
		global $wpdb;
		$this->wpdb          = $wpdb;
		$this->service_names = $service_names;
	}

	public function get() {
		if ( $this->cache === null ) {
			$wpdb        = $this->wpdb;
			$this->cache = array_map(
				array( $this, 'map' ),
				$wpdb->get_col(
					"SELECT *
					FROM (
						(
							SELECT translation_service
							FROM {$wpdb->prefix}icl_translation_status
						) UNION (
							SELECT translation_service
							FROM {$wpdb->prefix}icl_string_translations
						)
					) as services
					WHERE translation_service != 'local' AND translation_service != ''"
				)
			);
		}

		return $this->cache;
	}

	private function map( $translation_service_id ) {
		return array(
			'value' => $translation_service_id,
			'label' => $this->get_label( $translation_service_id ),
		);
	}

	private function get_label( $translation_service_id ) {
		try {
			return $this->service_names->get_name( $translation_service_id );
		} catch ( WPMLTranslationProxyApiException $e ) {
			return (string) $translation_service_id;
		}
	}
}
