<?php

use WPML\PB\ShortCodesInGutenbergBlocks;

class WPML_PB_String_Translation_By_Strategy extends WPML_PB_String_Translation {

	private $factory;

	private $strategy;

	private $packages_to_update = array();

	private $packages_being_updated = array();

	public function __construct( wpdb $wpdb, WPML_PB_Factory $factory, IWPML_PB_Strategy $strategy ) {
		$this->factory  = $factory;
		$this->strategy = $strategy;
		parent::__construct( $wpdb );
	}

	public function new_translation( $translated_string_id ) {
		list( $package_id, $string_id, $language ) = $this->get_package_for_translated_string( $translated_string_id );
		if ( $package_id ) {
			$package = $this->factory->get_wpml_package( $package_id );
			if ( $package->post_id ) {
				$this->queue_package_for_update( $package, $language );
			}
		}
	}

	public function queue_package_for_update( WPML_Package $package, $language ) {
		$strategy_kind = $this->strategy->get_package_kind();
		if ( $strategy_kind === $package->kind ) {
			$this->add_package_to_update_list( $package, $language );
		}

		ShortCodesInGutenbergBlocks::recordPackage(
			$this,
			$strategy_kind,
			$package,
			$language
		);
	}

	public function start_saving_translations_to_post() {
		foreach ( $this->packages_to_update as $package_id => $package_data ) {
			if ( isset( $this->packages_being_updated[ $package_id ] ) ) {
				$package_data['languages'] = array_values(
					array_unique( array_merge( $this->packages_being_updated[ $package_id ]['languages'], $package_data['languages'] ) )
				);
			}
			$this->packages_being_updated[ $package_id ] = $package_data;
		}
		$this->packages_to_update = array();
	}

	public function save_translations_to_post() {
		$this->start_saving_translations_to_post();

		foreach ( array_keys( $this->packages_being_updated ) as $package_id ) {
			$package_data = ShortCodesInGutenbergBlocks::fixupPackage( $this->get_package_being_updated( $package_id ) );
			if ( $package_data['package']->kind == $this->strategy->get_package_kind() ) {
				$update_post = $this->strategy->get_update_post( $package_data );
				$update_post->update();
			}
		}
	}

	public function finish_saving_translations_to_post() {
		$this->packages_being_updated = array();
	}

	public function update_translations_in_content( $content, $lang ) {
		foreach ( array_keys( $this->packages_being_updated ) as $package_id ) {
			$content = $this->update_translations_of_package_in_content( $this->get_package_being_updated( $package_id ), $content, $lang );
		}
		foreach ( array_diff_key( $this->packages_to_update, $this->packages_being_updated ) as $package_data ) {
			$content = $this->update_translations_of_package_in_content( $package_data, $content, $lang );
		}
		return $content;
	}

	private function update_translations_of_package_in_content( array $package_data, $content, $lang ) {
		if ( $package_data['package']->kind === $this->strategy->get_package_kind() ) {
			$update_post = $this->strategy->get_update_post( $package_data );
			$content     = $update_post->update_content( $content, $lang );
		}
		return $content;
	}

	private function get_package_being_updated( $package_id ) {
		if ( ! array_key_exists( WPML_PB_Update_Post::STRING_TRANSLATIONS, $this->packages_being_updated[ $package_id ] ) ) {
			$this->packages_being_updated[ $package_id ][ WPML_PB_Update_Post::STRING_TRANSLATIONS ] =
				$this->packages_being_updated[ $package_id ]['package']->get_translated_strings( array() );
		}

		return $this->packages_being_updated[ $package_id ];
	}

	private function get_package_for_translated_string( $translated_string_id ) {
		$sql    = $this->wpdb->prepare(
			"SELECT s.string_package_id, s.id, t.language
			FROM {$this->wpdb->prefix}icl_strings s
			LEFT JOIN {$this->wpdb->prefix}icl_string_translations t
			ON s.id = t.string_id
			WHERE t.id = %d", $translated_string_id );
		$result = $this->wpdb->get_row( $sql );

		if ( $result ) {
			return array( $result->string_package_id, $result->id, $result->language );
		} else {
			return array( null, null, null );
		}
	}

	public function add_package_to_update_list( WPML_Package $package, $language ) {
		unset( $this->packages_being_updated[ $package->ID ][ WPML_PB_Update_Post::STRING_TRANSLATIONS ] );

		if ( ! isset( $this->packages_to_update[ $package->ID ] ) ) {
			$this->packages_to_update[ $package->ID ] = array( 'package'   => $package,
			                                                   'languages' => array( $language )
			);
		} else {
			if ( ! in_array( $language, $this->packages_to_update[ $package->ID ]['languages'] ) ) {
				$this->packages_to_update[ $package->ID ]['languages'][] = $language;
			}
		}

		$this->packages_to_update = ShortCodesInGutenbergBlocks::normalizePackages( $this->packages_to_update );
	}
}
