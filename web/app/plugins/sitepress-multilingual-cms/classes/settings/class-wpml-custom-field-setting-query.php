<?php

class WPML_Custom_Field_Setting_Query {

	private $wpdb;

	private $excluded_keys;

	private $table;

	public function __construct( wpdb $wpdb, array $excluded_keys, $table ) {
		if ( ! is_string( $table ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			throw new InvalidArgumentException( 'Invalid metadata table name.' );
		}

		$this->wpdb          = $wpdb;
		$this->excluded_keys = $excluded_keys;
		$this->table         = $table;
	}

	public function get( array $args ) {
		$args = array_merge(
			array(
				'search'             => null,
				'hide_system_fields' => false,
				'items_per_page'     => null,
				'page'               => null,
				'one_more'           => false,
			),
			$args
		);

		$values = array();
		$where = " WHERE 1=1 AND meta_key IS NOT NULL AND meta_key <> ''";

		if ( $this->excluded_keys ) {
			$where .= ' AND meta_key NOT IN('
				. implode( ', ', array_fill( 0, count( $this->excluded_keys ), '%s' ) )
				. ')';
			$values = array_merge( $values, array_values( $this->excluded_keys ) );
		}

		if ( $args['search'] ) {
			$where   .= ' AND meta_key LIKE %s';
			$values[] = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
		}

		if ( $args['hide_system_fields'] ) {
			$where   .= ' AND meta_key NOT LIKE %s';
			$values[] = $this->wpdb->esc_like( '_' ) . '%';
		}

		$query = sprintf(
			'SELECT DISTINCT meta_key FROM %s%s ORDER BY meta_key ASC %s',
			$this->table,
			$where,
			$this->get_limit_offset( $args )
		);

		if ( $values ) {
			$query = $this->wpdb->prepare( $query, $values );
		}

		return $this->wpdb->get_col( $query );
	}

	private function get_limit_offset( array $args ) {
		$limit_offset = '';

		if ( $args['items_per_page'] && 0 < (int) $args['page'] ) {
			$limit_offset = $this->wpdb->prepare(
				' LIMIT %d OFFSET %d',
				$args['items_per_page'] + ( $args['one_more'] ? 1 : 0 ),
				( $args['page'] - 1 ) * $args['items_per_page']
			);
		}

		return $limit_offset;
	}

}
