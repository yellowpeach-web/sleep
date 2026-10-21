<?php

namespace WPML\Media\Lookup;

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
		$table = $this->tableName();

		return 0 === strcasecmp( (string) $this->wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ), $table );
	}

	public function drop() {
		$table = $this->tableName();

		return false !== $this->wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	public function truncate() {
		$table = $this->tableName();

		return false !== $this->wpdb->query( "TRUNCATE TABLE `{$table}`" );
	}
}
