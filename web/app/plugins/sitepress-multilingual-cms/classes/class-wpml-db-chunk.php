<?php

class WPML_DB_Chunk {
	private $wpdb;

	private $chunk_size;

	public function __construct( wpdb $wpdb, $chunk_size = 1000 ) {
		$this->wpdb       = $wpdb;
		$this->chunk_size = max( 1, (int) $chunk_size );
	}

	public function retrieve( $query, $args, $elements_num ) {
		$this->validate_query( $query );
		$result = array();

		$offset = 0;
		while ( $offset < $elements_num ) {
			$new_query = $query . sprintf( ' LIMIT %d OFFSET %s', $this->chunk_size, $offset );
			$new_query = $this->wpdb->prepare( $new_query, $args );
			$rowset    = $this->wpdb->get_results( $new_query, ARRAY_A );

			if ( is_array( $rowset ) && count( $rowset ) ) {
				$result = array_merge( $result, $rowset );
			}

			$offset += $this->chunk_size;
		}

		return $result;
	}

	private function validate_query( $query ) {
		if ( ! is_string( $query ) || false !== strpos( $query, '\\' ) ) {
			throw new InvalidArgumentException( 'Chunked database reads require exactly one valid SELECT statement.' );
		}

		$masked = preg_replace( '/\'(?:[^\']|\'\')*\'|"(?:[^"]|"")*"|`(?:[^`]|``)*`/', ' ', $query );

		if (
			! is_string( $masked )
			|| preg_match( '/[\'"`;#]|--|\/\*/', $masked )
			|| ! preg_match( '/^\s*SELECT\b/i', $masked )
		) {
			throw new InvalidArgumentException( 'Chunked database reads require exactly one valid SELECT statement.' );
		}

		if ( preg_match( '/\bFOR\s+(?:UPDATE|SHARE)\b|\bLOCK\s+IN\s+SHARE\s+MODE\b/i', $masked ) ) {
			throw new InvalidArgumentException( 'Chunked database reads require exactly one valid SELECT statement.' );
		}

		if ( preg_match( '/\b(?:LIMIT|OFFSET)\b/i', $masked ) ) {
			throw new InvalidArgumentException( "Query can't contain OFFSET or LIMIT keyword" );
		}

		if ( preg_match( '/\b(?:INTO|PROCEDURE)\b/i', $masked ) ) {
			throw new InvalidArgumentException( 'Chunked SELECT statements cannot write files or invoke procedures.' );
		}
	}
}
