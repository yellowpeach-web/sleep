<?php

class WPML_PB_String_Translation {

	protected $wpdb;

	private $package_ids = [];

	private $forgets_on_removal = false;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function get_package_strings( array $package_data ) {
		return $this->read_package_strings(
			$package_data,
			function ( $string ) {
				return $this->get_string_hash( $string->value );
			}
		);
	}

	public function get_package_strings_by_name( array $package_data ) {
		return $this->read_package_strings(
			$package_data,
			function ( $string ) {
				return $string->name;
			}
		);
	}

	private function read_package_strings( array $package_data, callable $get_key ) {
		$strings    = [];
		$package_id = $this->get_package_id( $package_data );
		if ( $package_id ) {
			$sql_to_get_strings_with_package_id = $this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->prefix}icl_strings s WHERE s.string_package_id=%d",
				$package_id
			);

			$package_strings = $this->wpdb->get_results( $sql_to_get_strings_with_package_id );

			if ( ! empty( $package_strings ) ) {
				foreach ( $package_strings as $string ) {
					$strings[ $get_key( $string ) ] = [
						'value'      => $string->value,
						'context'    => $string->context,
						'name'       => $string->name,
						'id'         => $string->id,
						'package_id' => $package_id,
						'location'   => $string->location,
					];
				}
			}
		}
		return $strings;
	}

	public function getStringsInContext( string $context, array $columns = [ '*' ], string $conditions = '' ) {
		$columns = array_intersect(
			$columns,
			[ '*', 'id', 'context', 'name', 'value', 'string_package_id', 'location', 'type', 'status' ]
		);

		if ( in_array( '*', $columns, true ) ) {
			$columns = [ '*' ];
		}

		if ( empty( $columns ) ) {
			return [];
		}

		$sql_columns = join( ', ', $columns );
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"
				SELECT {$sql_columns}
				FROM {$this->wpdb->prefix}icl_strings
				WHERE context = %s
				{$conditions}
				",
				$context
			)
		);
	}

	public function remove_string( array $string_data ) {
		icl_unregister_string( $string_data['context'], $string_data['name'] );

		$field_type = 'package-string-' . $string_data['package_id'] . '-' . $string_data['id'];

		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->wpdb->prefix}icl_translate
				WHERE field_type = %s
				AND job_id NOT IN (
					SELECT job_id FROM {$this->wpdb->prefix}icl_translate_job
				)",
				$field_type
			)
		);
	}

	private function get_package_id( array $package_data ) {
		$package_id = false;

		if ( ! \WPML\Upgrade\SchemaFacts::hasTable( 'icl_string_packages' ) ) {
			return $package_id;
		}

		$key = implode(
			"\0",
			[
				(string) $package_data['kind'],
				(string) $package_data['name'],
				(string) $package_data['post_id'],
			]
		);
		if ( isset( $this->package_ids[ $key ] ) ) {
			return $this->package_ids[ $key ];
		}

		$args = [ $package_data['kind'], $package_data['name'] ];

		if ( isset( $package_data['post_id'] ) ) {
			$post_id_condition = 's.post_id=%s';
			$args[]            = $package_data['post_id'];
		} else {
			$post_id_condition = 's.post_id IS NULL';
		}

		$sql_to_get_package_id = $this->wpdb->prepare(
			"
				SELECT s.ID
				FROM {$this->wpdb->prefix}icl_string_packages s
				WHERE s.kind=%s AND s.name=%s AND {$post_id_condition}
			",
			...$args
		);

		$result = $this->wpdb->get_row( $sql_to_get_package_id );

		if ( isset( $result->ID ) ) {
			$package_id = $result->ID;
			$this->remember_package_id( $key, $package_id );
		}

		return $package_id;
	}

	private function remember_package_id( $key, $package_id ) {
		if ( ! $this->forgets_on_removal ) {
			$this->forgets_on_removal = true;
			add_action( 'wpml_delete_package', [ $this, 'forget_package_ids' ] );
			add_action( 'deleted_post', [ $this, 'forget_package_ids' ] );
		}

		$this->package_ids[ $key ] = $package_id;
	}

	public function forget_package_ids() {
		$this->package_ids = [];
	}

	public function get_string_hash( $string_value ) {
		$string_value = is_null( $string_value ) ? '' : $string_value;

		return md5( $string_value );
	}
}
