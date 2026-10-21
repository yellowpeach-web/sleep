<?php

namespace WPML\LanguageEditor\Presets;

class CatalogueVersionStore {

	const OPTION = 'wpml_language_catalogue_sync';

	const SECTION_LANGUAGES             = 'languages';
	const SECTION_LANGUAGE_TRANSLATIONS = 'language_translations';
	const SECTION_COUNTRY_TRANSLATIONS  = 'country_translations';
	const SECTION_FLAGS                 = 'flags';

	private function read() {
		$stored = get_option( self::OPTION, array() );

		return array(
			'versions'  => isset( $stored['versions'] ) && is_array( $stored['versions'] ) ? $stored['versions'] : array(),
			'synced_at' => isset( $stored['synced_at'] ) && is_array( $stored['synced_at'] ) ? $stored['synced_at'] : array(),
		);
	}

	private function write( array $data ) {
		update_option( self::OPTION, $data, false );
	}

	public function getVersion( $section ) {
		$data = $this->read();

		return array_key_exists( $section, $data['versions'] ) ? (int) $data['versions'][ $section ] : null;
	}

	public function setVersion( $section, $version ) {
		$data                          = $this->read();
		$data['versions'][ $section ]  = (int) $version;
		$data['synced_at'][ $section ] = time();
		$this->write( $data );
	}

	public function clearVersion( $section ) {
		$data = $this->read();
		unset( $data['versions'][ $section ] );
		$this->write( $data );
	}

	public function getSyncedAt( $section ) {
		$data = $this->read();

		return isset( $data['synced_at'][ $section ] ) ? (int) $data['synced_at'][ $section ] : 0;
	}

	public function markSynced( $section ) {
		$data                          = $this->read();
		$data['synced_at'][ $section ] = time();
		$this->write( $data );
	}
}
