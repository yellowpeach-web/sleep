<?php

namespace WPML\Media\Lookup;

use WPML\Upgrade\SchemaFacts;

class MediaLookupSchema {

	const TABLE = 'icl_media_url_lookup';

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function tableName() {
		return $this->wpdb->prefix . self::TABLE;
	}

	public function exists() {
		if ( $this->usesSchemaFacts() ) {
			return SchemaFacts::hasTable( self::TABLE );
		}

		$table = $this->tableName();

		return 0 === strcasecmp( (string) $this->wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ), $table );
	}

	public function forgetIfQueryFailed() {
		if ( $this->usesSchemaFacts() ) {
			SchemaFacts::forgetIfQueryFailed( $this->wpdb );
		}
	}

	public function drop() {
		$table = $this->tableName();

		$dropped = false !== $this->wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

		if ( $this->usesSchemaFacts() ) {
			SchemaFacts::forgetKept();
		}

		return $dropped;
	}

	private function usesSchemaFacts() {
		return isset( $GLOBALS['wpdb'] ) && $this->wpdb === $GLOBALS['wpdb'] && class_exists( SchemaFacts::class );
	}

	public function truncate() {
		$table = $this->tableName();

		return false !== $this->wpdb->query( "TRUNCATE TABLE `{$table}`" );
	}
}
