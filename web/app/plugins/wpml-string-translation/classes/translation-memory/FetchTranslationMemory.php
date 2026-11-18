<?php


namespace WPML\ST\Main\Ajax;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Obj;

class FetchTranslationMemory implements IHandler {

	private const BATCH_MAX_STRINGS = 500;

	private const BATCH_MAX_BYTES = 1048576;

	private $records;

	public function __construct( \WPML_ST_Translation_Memory_Records $records ) {
		$this->records = $records;
	}

	public function run( Collection $data ) {
		$translations = Obj::prop( 'batch', $data )
			? $this->fetchBatchStrings( Obj::prop( 'strings', $data ) )
			: $this->fetchSingleString( $data );

		if ( $translations !== false ) {
			return Either::of( $translations );
		} else {
			return Either::left( 'invalid data' );
		}
	}

	private function fetchBatchStrings( $entries ) {
		if ( ! is_array( $entries ) ) {
			return false;
		}

		foreach ( $entries as $entry ) {
			if ( ! self::isValidEntry( $entry ) ) {
				return false;
			}
		}

		if ( ! self::isTranslationMemoryEnabled() ) {
			return array_map(
				function () {
					return [];
				},
				$entries
			);
		}

		$rows = [];
		foreach ( self::textsByPair( $entries ) as $pair => $group ) {
			foreach ( self::chunks( $group['texts'] ) as $chunk ) {
				foreach ( (array) $this->records->get( $chunk, $group['source'], $group['target'], null, null ) as $row ) {
					$rows[ $pair ][ $row->original ][] = $row;
				}
			}
		}

		return array_map(
			function ( $entry ) use ( $rows ) {
				$pair   = self::pairKey( $entry );
				$string = (string) Obj::prop( 'string', $entry );

				return isset( $rows[ $pair ][ $string ] ) ? $rows[ $pair ][ $string ] : [];
			},
			$entries
		);
	}

	private static function isValidEntry( $entry ) {
		return Obj::prop( 'string', $entry ) && Obj::prop( 'source', $entry ) && Obj::prop( 'target', $entry );
	}

	private static function pairKey( $entry ) {
		return Obj::prop( 'source', $entry ) . "\0" . Obj::prop( 'target', $entry );
	}

	private static function textsByPair( array $entries ) {
		$pairs = [];
		foreach ( $entries as $entry ) {
			$pair = self::pairKey( $entry );
			if ( ! isset( $pairs[ $pair ] ) ) {
				$pairs[ $pair ] = [
					'source' => Obj::prop( 'source', $entry ),
					'target' => Obj::prop( 'target', $entry ),
					'texts'  => [],
				];
			}
			$pairs[ $pair ]['texts'][] = (string) Obj::prop( 'string', $entry );
		}

		foreach ( $pairs as $pair => $group ) {
			$pairs[ $pair ]['texts'] = array_values( array_unique( $group['texts'] ) );
		}

		return $pairs;
	}

	private static function chunks( array $texts ) {
		$chunks = [];
		$chunk  = [];
		$bytes  = 0;
		foreach ( $texts as $text ) {
			$size = strlen( $text );
			if ( $chunk && ( count( $chunk ) >= self::BATCH_MAX_STRINGS || $bytes + $size > self::BATCH_MAX_BYTES ) ) {
				$chunks[] = $chunk;
				$chunk    = [];
				$bytes    = 0;
			}
			$chunk[] = $text;
			$bytes  += $size;
		}
		if ( $chunk ) {
			$chunks[] = $chunk;
		}

		return $chunks;
	}

	public function fetchSingleString( $data ) {
		$string          = Obj::prop( 'string', $data );
		$source          = Obj::prop( 'source', $data );
		$target          = Obj::prop( 'target', $data );
		$context         = Obj::prop( 'context', $data );
		$gettext_context = Obj::prop( 'gettext_context', $data );

		if ( $string && $source && $target ) {
			if ( ! self::isTranslationMemoryEnabled() ) {
				return [];
			}

			return $this->records->get( [ $string ], $source, $target, $context, $gettext_context );
		} else {
			return false;
		}
	}

	private static function isTranslationMemoryEnabled() {
		return defined( 'WPML_USE_ST_TRANSLATION_MEMORY' ) && WPML_USE_ST_TRANSLATION_MEMORY;
	}
}
