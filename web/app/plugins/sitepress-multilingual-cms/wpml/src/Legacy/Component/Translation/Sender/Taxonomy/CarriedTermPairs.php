<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class CarriedTermPairs {

	private static $batch = null;

	private static $failed = false;

	public static function clear(): void {
		self::$batch  = null;
		self::$failed = false;
	}

	public static function failed(): bool {
		return self::$failed;
	}

	public static function sql( \wpdb $wpdb ): string {
		return self::joinSql( $wpdb );
	}

	private static function joinSql( \wpdb $wpdb ): string {
		return "FROM {$wpdb->prefix}icl_translation_status ts
			INNER JOIN {$wpdb->prefix}icl_translate_job job ON job.rid = ts.rid AND job.revision IS NULL
			AND ts.status IN (" . implode( ',', TranslationStatus::CARRIES_UNITS ) . ")" . self::holdsCurrentJobSql() . "
			INNER JOIN {$wpdb->prefix}icl_translate unit ON unit.job_id = job.job_id
			INNER JOIN {$wpdb->prefix}icl_translations target ON target.translation_id = ts.translation_id
			INNER JOIN {$wpdb->prefix}icl_translations original ON original.trid = target.trid
			AND original.source_language_code IS NULL AND original.element_type LIKE 'post!_%%' ESCAPE '!'
			INNER JOIN {$wpdb->term_relationships} relationships ON relationships.object_id = original.element_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = relationships.term_taxonomy_id
			AND unit.field_type = CONCAT('t_', tt.term_taxonomy_id)";
	}

	private static function holdsCurrentJobSql(): string {
		return " AND ( ts.status <> 2 OR COALESCE(ts.translation_service, '') <> 'local' OR COALESCE(job.editor, '') <> 'ate' OR COALESCE(job.editor_job_id, 0) <> 0 )";
	}

	public static function find( \wpdb $wpdb, array $pairs ): array {
		$pairs       = self::normalize( $pairs );
		self::$batch = [];
		if ( ! $pairs ) {
			self::$failed = false;

			return [];
		}

		$found = self::read( $wpdb, $pairs );
		if ( self::$failed ) {
			return [];
		}
		foreach ( $pairs as $key => $pair ) {
			self::$batch[ $key ] = $found[ $key ] ?? null;
		}

		return $found;
	}

	public static function read( \wpdb $wpdb, array $pairs ): array {
		$pairs = self::normalize( $pairs );
		if ( ! $pairs ) {
			self::$failed = false;

			return [];
		}
		$result = self::query( $wpdb, $pairs );

		self::$failed = $result['failed'];

		return $result['found'];
	}

	public static function one( \wpdb $wpdb, int $ttid, string $language ) {
		$key = self::key( $ttid, $language );
		if ( null !== self::$batch && array_key_exists( $key, self::$batch ) ) {
			return self::$batch[ $key ];
		}

		$found = self::read( $wpdb, [ [ $ttid, $language ] ] );

		return $found[ $key ] ?? null;
	}

	private static function query( \wpdb $wpdb, array $pairs ): array {
		$fields = array_values( array_unique( array_map( function ( array $pair ): string {
			return 't_' . $pair[0];
		}, $pairs ) ) );
		$languages = array_values( array_unique( array_column( $pairs, 1 ) ) );
		try {
			$queryHandler = \WPML\Container\make( QueryHandlerInterface::class, [ ':wpdb' => $wpdb ] );
			$queryPrepare = \WPML\Container\make( QueryPrepareInterface::class, [ ':wpdb' => $wpdb ] );
			$rows         = $queryHandler->query(
				$queryPrepare->prepare( "SELECT STRAIGHT_JOIN tt.term_taxonomy_id, target.language_code,
					MIN(original.element_id) AS post_id, MIN(job.job_id) AS job_id
					" . self::sql( $wpdb ) . "
					WHERE unit.field_type IN (" . implode( ',', array_fill( 0, count( $fields ), '%s' ) ) . ")
					AND FIND_IN_SET(target.language_code, %s)
					GROUP BY tt.term_taxonomy_id, target.language_code",
					...array_merge( $fields, [ implode( ',', $languages ) ] )
				)
			)->getResults();
		} catch ( \InvalidArgumentException | DatabaseErrorException $error ) {
			return [ 'found' => [], 'failed' => true ];
		}
		$found = [];
		foreach ( $rows as $row ) {
			if ( ! isset( $row['term_taxonomy_id'], $row['language_code'], $row['post_id'], $row['job_id'] ) ) {
				continue;
			}
			$ttid     = (int) $row['term_taxonomy_id'];
			$language = (string) $row['language_code'];
			$key      = self::key( $ttid, $language );
			if ( isset( $pairs[ $key ] ) ) {
				$found[ $key ] = [
					'ttid'     => $ttid,
					'language' => $language,
					'postId'   => (int) $row['post_id'],
					'jobId'    => (int) $row['job_id'],
				];
			}
		}

		return [ 'found' => $found, 'failed' => false ];
	}

	private static function normalize( array $pairs ): array {
		$normalized = [];
		foreach ( $pairs as $pair ) {
			$ttid     = $pair[0];
			$language = $pair[1];
			if ( $ttid > 0 && '' !== $language ) {
				$normalized[ self::key( $ttid, $language ) ] = [ $ttid, $language ];
			}
		}

		return $normalized;
	}

	private static function key( int $ttid, string $language ): string {
		return $ttid . '|' . $language;
	}
}
