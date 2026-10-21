<?php

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;

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
		$parser = new Parser( $query );

		if (
			! empty( $parser->errors )
			|| 1 !== count( $parser->statements )
			|| ! $parser->statements[0] instanceof SelectStatement
		) {
			throw new InvalidArgumentException( 'Chunked database reads require exactly one valid SELECT statement.' );
		}

		$statement = $parser->statements[0];
		if ( ! empty( $statement->limit ) ) {
			throw new InvalidArgumentException( "Query can't contain OFFSET or LIMIT keyword" );
		}

		if ( ! empty( $statement->into ) || ! empty( $statement->procedure ) ) {
			throw new InvalidArgumentException( 'Chunked SELECT statements cannot write files or invoke procedures.' );
		}
	}
}
