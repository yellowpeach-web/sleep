<?php

namespace WPML\TranslationPolicy\Policy;

class PolicySql {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function rowsForPost( int $originalId, string $postType ): array {
		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT `scope`, `scope_id`, `strength`, `source`, `editor`, `method`
					FROM {$wpdb->prefix}icl_translation_policy
					WHERE ( `scope` = 'post' AND `scope_id` = %s )
						OR ( `scope` = 'post_type' AND `scope_id` = %s )
						OR ( `scope` = 'site' AND `scope_id` = '' )
					ORDER BY `id`",
					(string) $originalId,
					$postType
				),
				ARRAY_A
			)
		);
	}

	public function rowsForType( string $postType ): array {
		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT `scope`, `scope_id`, `strength`, `source`, `editor`, `method`
					FROM {$wpdb->prefix}icl_translation_policy
					WHERE ( `scope` = 'post_type' AND `scope_id` = %s )
						OR ( `scope` = 'site' AND `scope_id` = '' )
					ORDER BY `id`",
					$postType
				),
				ARRAY_A
			)
		);
	}

	public function rowsForSite(): array {
		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				"SELECT `scope`, `scope_id`, `strength`, `source`, `editor`, `method`
				FROM {$wpdb->prefix}icl_translation_policy
				WHERE `scope` = 'site' AND `scope_id` = ''
				ORDER BY `id`",
				ARRAY_A
			)
		);
	}

	public function settingsRows( string $postType ): array {
		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT `scope`, `scope_id`, `strength`, `source`, `editor`, `method`, `reason`
					FROM {$wpdb->prefix}icl_translation_policy
					WHERE ( `scope` = 'post_type' AND `scope_id` = %s AND %s <> '' )
						OR ( `scope` = 'site' AND `scope_id` = '' )
					ORDER BY `id`",
					$postType,
					$postType
				),
				ARRAY_A
			)
		);
	}

	public function panelRows( int $originalId, string $postType ): array {
		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT `scope`, `scope_id`, `strength`, `source`, `editor`, `method`, `reason`
					FROM {$wpdb->prefix}icl_translation_policy
					WHERE ( `scope` = 'post' AND `scope_id` = %s )
						OR ( `scope` = 'post_type' AND `scope_id` = %s )
						OR ( `scope` = 'site' AND `scope_id` = '' )
					ORDER BY `id`",
					(string) $originalId,
					$postType
				),
				ARRAY_A
			)
		);
	}

	public function choiceRow( string $scope, string $scopeId ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `editor`, `method`, `source` FROM {$wpdb->prefix}icl_translation_policy
				WHERE `scope` = %s AND `scope_id` = %s AND `strength` = 'choice'
				ORDER BY `id` LIMIT 1",
				$scope,
				$scopeId
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	public function clearPostColumnOfType( string $postType, string $column ): int {
		if ( ! in_array( $column, [ PolicyTable::COLUMN_EDITOR, PolicyTable::COLUMN_METHOD ], true ) ) {
			throw new \InvalidArgumentException( 'A policy column is editor or method.' );
		}
		$wpdb = $this->wpdb;

		$updated = PolicyTable::COLUMN_EDITOR === $column
			? $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translation_policy r
					JOIN {$wpdb->posts} p ON p.`ID` = CAST( r.`scope_id` AS UNSIGNED )
					SET r.`editor` = NULL
					WHERE r.`scope` = 'post' AND r.`source` = '' AND r.`strength` IN ( 'choice', 'default' )
						AND r.`editor` IN ( %s, %s )
						AND CAST( p.`post_type` AS BINARY ) = CAST( %s AS BINARY )",
					PolicyTable::EDITOR_CTE,
					PolicyTable::EDITOR_ATE,
					$postType
				)
			)
			: $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translation_policy r
					JOIN {$wpdb->posts} p ON p.`ID` = CAST( r.`scope_id` AS UNSIGNED )
					SET r.`method` = NULL
					WHERE r.`scope` = 'post' AND r.`source` = '' AND r.`strength` IN ( 'choice', 'default' )
						AND r.`method` IS NOT NULL
						AND CAST( p.`post_type` AS BINARY ) = CAST( %s AS BINARY )",
					$postType
				)
			);

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE r FROM {$wpdb->prefix}icl_translation_policy r
				JOIN {$wpdb->posts} p ON p.`ID` = CAST( r.`scope_id` AS UNSIGNED )
				WHERE r.`scope` = 'post' AND r.`source` = '' AND r.`strength` IN ( 'choice', 'default' )
					AND r.`editor` IS NULL AND r.`method` IS NULL
					AND CAST( p.`post_type` AS BINARY ) = CAST( %s AS BINARY )",
				$postType
			)
		);
		Policy::resetRequestCache();

		if ( false === $updated || false === $deleted ) {
			throw new \RuntimeException( 'The database refused the clear of the posts of the type.' );
		}

		return (int) $updated + (int) $deleted;
	}

	public function rowId( string $scope, string $scopeId, string $strength, string $source ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM {$wpdb->prefix}icl_translation_policy
				WHERE `scope` = %s AND `scope_id` = %s AND `strength` = %s AND `source` = %s",
				$scope,
				$scopeId,
				$strength,
				$source
			)
		);
	}

	public function choiceRowId( string $scope, string $scopeId ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM {$wpdb->prefix}icl_translation_policy
				WHERE `scope` = %s AND `scope_id` = %s AND `strength` = 'choice'
				ORDER BY `id` LIMIT 1",
				$scope,
				$scopeId
			)
		);
	}

	public function anyRowWantsAteAccount( bool $fallbackIsAte = false ): bool {
		$wpdb = $this->wpdb;

		if ( $fallbackIsAte ) {
			return (bool) $wpdb->get_var(
				"SELECT 1 FROM {$wpdb->prefix}icl_translation_policy
				WHERE `strength` IN ( 'lock', 'choice', 'default' )
					AND ( `editor` = 'ate' OR `editor` = 'wpml' OR `method` = 'ptc' )
				LIMIT 1"
			);
		}

		return (bool) $wpdb->get_var(
			"SELECT 1 FROM {$wpdb->prefix}icl_translation_policy
			WHERE `strength` IN ( 'lock', 'choice', 'default' )
				AND ( `editor` = 'ate' OR `method` = 'ptc' )
			LIMIT 1"
		);
	}

	public function answersForPosts( array $postIds, string $fallbackEditor ): array {
		self::assertFallbackEditor( $fallbackEditor );
		if ( ! $postIds ) {
			return [];
		}

		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.`ID` AS `id`,
						COALESCE( NULLIF( NULLIF( SUBSTRING_INDEX( LEAST(
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '1', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'post' AND r.`scope_id` = CAST( o.`ID` AS CHAR CHARACTER SET ascii )
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`editor` IN ( 'native', 'cte', 'ate', 'wpml' )
									AND NOT ( CAST( o.`post_type` AS BINARY ) IN ( 'wp_navigation' ) AND r.`editor` = 'native' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' ),
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '2', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'post_type' AND r.`scope_id` = CONVERT( o.`post_type` USING ascii )
									AND CAST( r.`scope_id` AS BINARY ) = CAST( o.`post_type` AS BINARY )
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`editor` IN ( 'native', 'cte', 'ate', 'wpml' )
									AND NOT ( CAST( o.`post_type` AS BINARY ) IN ( 'wp_navigation' ) AND r.`editor` = 'native' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' ),
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '3', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'site' AND r.`scope_id` = ''
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`editor` IN ( 'native', 'cte', 'ate', 'wpml' )
									AND NOT ( CAST( o.`post_type` AS BINARY ) IN ( 'wp_navigation' ) AND r.`editor` = 'native' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' )
						), '|', -1 ), '9' ), 'wpml' ),
						NULLIF( SUBSTRING_INDEX( LEAST(
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '1', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'post' AND r.`scope_id` = CAST( o.`ID` AS CHAR CHARACTER SET ascii )
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`editor` IN ( 'cte', 'ate' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' ),
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '2', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'post_type' AND r.`scope_id` = CONVERT( o.`post_type` USING ascii )
									AND CAST( r.`scope_id` AS BINARY ) = CAST( o.`post_type` AS BINARY )
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`editor` IN ( 'cte', 'ate' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' ),
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '3', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'site' AND r.`scope_id` = ''
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`editor` IN ( 'cte', 'ate' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' )
						), '|', -1 ), '9' ), %s ) AS `editor`,
						COALESCE( NULLIF( SUBSTRING_INDEX( LEAST(
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '1', LPAD( r.`id`, 20, '0' ), '|', r.`method` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'post' AND r.`scope_id` = CAST( o.`ID` AS CHAR CHARACTER SET ascii )
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`method` IN ( 'self', 'ptc' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' ),
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '2', LPAD( r.`id`, 20, '0' ), '|', r.`method` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'post_type' AND r.`scope_id` = CONVERT( o.`post_type` USING ascii )
									AND CAST( r.`scope_id` AS BINARY ) = CAST( o.`post_type` AS BINARY )
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`method` IN ( 'self', 'ptc' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' ),
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '3', LPAD( r.`id`, 20, '0' ), '|', r.`method` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'site' AND r.`scope_id` = ''
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`method` IN ( 'self', 'ptc' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' )
						), '|', -1 ), '9' ), 'self' ) AS `method`
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->prefix}icl_translations t
						ON t.`element_id` = p.`ID` AND t.`element_type` = CONCAT( 'post_', p.`post_type` )
					LEFT JOIN {$wpdb->prefix}icl_translations ot
						ON ot.`trid` = t.`trid` AND ot.`element_type` = t.`element_type` AND ot.`source_language_code` IS NULL
					JOIN {$wpdb->posts} o ON o.`ID` = COALESCE( ot.`element_id`, p.`ID` )
					WHERE p.`ID` IN ( " . implode( ', ', array_fill( 0, count( $postIds ), '%d' ) ) . ' )',
					array_merge( [ $fallbackEditor ], $postIds )
				),
				ARRAY_A
			)
		);
	}

	public function methodRowsForPosts( array $postIds ): array {
		if ( ! $postIds ) {
			return [];
		}

		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.`ID` AS `id`,
						COALESCE( NULLIF( SUBSTRING_INDEX( LEAST(
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '1', LPAD( r.`id`, 20, '0' ), '|', r.`strength`, ':', r.`method` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'post' AND r.`scope_id` = CAST( o.`ID` AS CHAR CHARACTER SET ascii )
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`method` IN ( 'self', 'ptc' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' ),
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '2', LPAD( r.`id`, 20, '0' ), '|', r.`strength`, ':', r.`method` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'post_type' AND r.`scope_id` = CONVERT( o.`post_type` USING ascii )
									AND CAST( r.`scope_id` AS BINARY ) = CAST( o.`post_type` AS BINARY )
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`method` IN ( 'self', 'ptc' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' ),
							COALESCE( (
								SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '3', LPAD( r.`id`, 20, '0' ), '|', r.`strength`, ':', r.`method` )
								FROM {$wpdb->prefix}icl_translation_policy r
								WHERE r.`scope` = 'site' AND r.`scope_id` = ''
									AND r.`strength` IN ( 'lock', 'choice', 'default' )
									AND r.`method` IN ( 'self', 'ptc' )
								ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
								LIMIT 1
							), '9' )
						), '|', -1 ), '9' ), '' ) AS `method_row`
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->prefix}icl_translations t
						ON t.`element_id` = p.`ID` AND t.`element_type` = CONCAT( 'post_', p.`post_type` )
					LEFT JOIN {$wpdb->prefix}icl_translations ot
						ON ot.`trid` = t.`trid` AND ot.`element_type` = t.`element_type` AND ot.`source_language_code` IS NULL
					JOIN {$wpdb->posts} o ON o.`ID` = COALESCE( ot.`element_id`, p.`ID` )
					WHERE p.`ID` IN ( " . implode( ', ', array_fill( 0, count( $postIds ), '%d' ) ) . ' )',
					$postIds
				),
				ARRAY_A
			)
		);
	}

	public function editorLocksForPosts( array $postIds ): array {
		if ( ! $postIds ) {
			return [];
		}

		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT l.`id`, l.`post_type`, l.`scope`, l.`scope_id`, l.`source`, l.`reason`, l.`editor`
					FROM (
					SELECT p.`ID` AS `id`, o.`post_type`, r.`scope`, r.`scope_id`, r.`source`, r.`reason`, r.`editor`, 1 AS `scope_rank`, r.`id` AS `row_id`
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->prefix}icl_translations t
						ON t.`element_id` = p.`ID` AND t.`element_type` = CONCAT( 'post_', p.`post_type` )
					LEFT JOIN {$wpdb->prefix}icl_translations ot
						ON ot.`trid` = t.`trid` AND ot.`element_type` = t.`element_type` AND ot.`source_language_code` IS NULL
					JOIN {$wpdb->posts} o ON o.`ID` = COALESCE( ot.`element_id`, p.`ID` )
					JOIN {$wpdb->prefix}icl_translation_policy r
						ON r.`scope` = 'post' AND r.`scope_id` = CAST( o.`ID` AS CHAR CHARACTER SET ascii )
					WHERE r.`strength` = 'lock'
						AND r.`editor` IN ( 'native', 'cte', 'ate', 'wpml' )
						AND p.`ID` IN ( " . implode( ', ', array_fill( 0, count( $postIds ), '%d' ) ) . " )
					UNION ALL
					SELECT p.`ID` AS `id`, o.`post_type`, r.`scope`, r.`scope_id`, r.`source`, r.`reason`, r.`editor`, 2 AS `scope_rank`, r.`id` AS `row_id`
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->prefix}icl_translations t
						ON t.`element_id` = p.`ID` AND t.`element_type` = CONCAT( 'post_', p.`post_type` )
					LEFT JOIN {$wpdb->prefix}icl_translations ot
						ON ot.`trid` = t.`trid` AND ot.`element_type` = t.`element_type` AND ot.`source_language_code` IS NULL
					JOIN {$wpdb->posts} o ON o.`ID` = COALESCE( ot.`element_id`, p.`ID` )
					JOIN {$wpdb->prefix}icl_translation_policy r
						ON r.`scope` = 'post_type' AND r.`scope_id` = CONVERT( o.`post_type` USING ascii )
							AND CAST( r.`scope_id` AS BINARY ) = CAST( o.`post_type` AS BINARY )
					WHERE r.`strength` = 'lock'
						AND r.`editor` IN ( 'native', 'cte', 'ate', 'wpml' )
						AND p.`ID` IN ( " . implode( ', ', array_fill( 0, count( $postIds ), '%d' ) ) . " )
					UNION ALL
					SELECT p.`ID` AS `id`, o.`post_type`, r.`scope`, r.`scope_id`, r.`source`, r.`reason`, r.`editor`, 3 AS `scope_rank`, r.`id` AS `row_id`
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->prefix}icl_translations t
						ON t.`element_id` = p.`ID` AND t.`element_type` = CONCAT( 'post_', p.`post_type` )
					LEFT JOIN {$wpdb->prefix}icl_translations ot
						ON ot.`trid` = t.`trid` AND ot.`element_type` = t.`element_type` AND ot.`source_language_code` IS NULL
					JOIN {$wpdb->posts} o ON o.`ID` = COALESCE( ot.`element_id`, p.`ID` )
					JOIN {$wpdb->prefix}icl_translation_policy r
						ON r.`scope` = 'site' AND r.`scope_id` = ''
					WHERE r.`strength` = 'lock'
						AND r.`editor` IN ( 'native', 'cte', 'ate', 'wpml' )
						AND p.`ID` IN ( " . implode( ', ', array_fill( 0, count( $postIds ), '%d' ) ) . " )
					) l
					ORDER BY l.`id`, l.`scope_rank`, l.`row_id`",
					array_merge( $postIds, $postIds, $postIds )
				),
				ARRAY_A
			)
		);
	}

	public function rowsOfSource( string $source, string $strength ): array {
		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT `scope`, `scope_id`, `editor`, `method`, `reason`
					FROM {$wpdb->prefix}icl_translation_policy
					WHERE `source` = %s AND `strength` = %s
					ORDER BY `id`",
					$source,
					$strength
				),
				ARRAY_A
			)
		);
	}

	public function editorOf( string $postIdColumn, string $postTypeColumn, string $fallbackEditor ): string {
		self::assertFallbackEditor( $fallbackEditor );

		$wpdb = $this->wpdb;

		return "COALESCE( NULLIF( NULLIF( SUBSTRING_INDEX( LEAST(
				COALESCE( (
					SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '1', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
					FROM {$wpdb->prefix}icl_translation_policy r
					WHERE r.`scope` = 'post' AND r.`scope_id` = CAST( {$postIdColumn} AS CHAR CHARACTER SET ascii )
						AND r.`strength` IN ( 'lock', 'choice', 'default' )
						AND r.`editor` IN ( 'native', 'cte', 'ate', 'wpml' )
						AND NOT ( CAST( {$postTypeColumn} AS BINARY ) IN ( 'wp_navigation' ) AND r.`editor` = 'native' )
					ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
					LIMIT 1
				), '9' ),
				COALESCE( (
					SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '2', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
					FROM {$wpdb->prefix}icl_translation_policy r
					WHERE r.`scope` = 'post_type' AND r.`scope_id` = CONVERT( {$postTypeColumn} USING ascii )
						AND CAST( r.`scope_id` AS BINARY ) = CAST( {$postTypeColumn} AS BINARY )
						AND r.`strength` IN ( 'lock', 'choice', 'default' )
						AND r.`editor` IN ( 'native', 'cte', 'ate', 'wpml' )
						AND NOT ( CAST( {$postTypeColumn} AS BINARY ) IN ( 'wp_navigation' ) AND r.`editor` = 'native' )
					ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
					LIMIT 1
				), '9' ),
				COALESCE( (
					SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '3', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
					FROM {$wpdb->prefix}icl_translation_policy r
					WHERE r.`scope` = 'site' AND r.`scope_id` = ''
						AND r.`strength` IN ( 'lock', 'choice', 'default' )
						AND r.`editor` IN ( 'native', 'cte', 'ate', 'wpml' )
						AND NOT ( CAST( {$postTypeColumn} AS BINARY ) IN ( 'wp_navigation' ) AND r.`editor` = 'native' )
					ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
					LIMIT 1
				), '9' )
			), '|', -1 ), '9' ), 'wpml' ),
			NULLIF( SUBSTRING_INDEX( LEAST(
				COALESCE( (
					SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '1', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
					FROM {$wpdb->prefix}icl_translation_policy r
					WHERE r.`scope` = 'post' AND r.`scope_id` = CAST( {$postIdColumn} AS CHAR CHARACTER SET ascii )
						AND r.`strength` IN ( 'lock', 'choice', 'default' )
						AND r.`editor` IN ( 'cte', 'ate' )
					ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
					LIMIT 1
				), '9' ),
				COALESCE( (
					SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '2', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
					FROM {$wpdb->prefix}icl_translation_policy r
					WHERE r.`scope` = 'post_type' AND r.`scope_id` = CONVERT( {$postTypeColumn} USING ascii )
						AND CAST( r.`scope_id` AS BINARY ) = CAST( {$postTypeColumn} AS BINARY )
						AND r.`strength` IN ( 'lock', 'choice', 'default' )
						AND r.`editor` IN ( 'cte', 'ate' )
					ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
					LIMIT 1
				), '9' ),
				COALESCE( (
					SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '3', LPAD( r.`id`, 20, '0' ), '|', r.`editor` )
					FROM {$wpdb->prefix}icl_translation_policy r
					WHERE r.`scope` = 'site' AND r.`scope_id` = ''
						AND r.`strength` IN ( 'lock', 'choice', 'default' )
						AND r.`editor` IN ( 'cte', 'ate' )
					ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
					LIMIT 1
				), '9' )
			), '|', -1 ), '9' ), '{$fallbackEditor}' )";
	}

	public function siteHasChoiceOrLock(): bool {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			"SELECT 1 FROM {$wpdb->prefix}icl_translation_policy
			WHERE `scope` = 'site' AND `scope_id` = '' AND `strength` IN ( 'lock', 'choice' )
				AND `editor` IN ( 'native', 'cte', 'ate', 'wpml' )
			LIMIT 1"
		);
	}

	public function stampedRows(): array {
		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				"SELECT `id`, `scope`, `scope_id`, `strength`, `source`, `editor`, `method`
				FROM {$wpdb->prefix}icl_translation_policy
				WHERE `reason` = 'migration' AND `source` = '' AND `scope` IN ( 'site', 'post_type' )
				ORDER BY `id`",
				ARRAY_A
			)
		);
	}

	public function stampedPostRows( int $afterId, int $lastId ): array {
		$wpdb = $this->wpdb;

		return self::rows(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT `id`, `scope`, `scope_id`, `strength`, `source`, `editor`, `method`
					FROM {$wpdb->prefix}icl_translation_policy
					WHERE `reason` = 'migration' AND `source` = '' AND `scope` = 'post'
						AND CAST( `scope_id` AS UNSIGNED ) > %d AND CAST( `scope_id` AS UNSIGNED ) <= %d
					ORDER BY `id`",
					$afterId,
					$lastId
				),
				ARRAY_A
			)
		);
	}

	public function countStamped(): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translation_policy WHERE `reason` = 'migration'" );
	}

	public function insertMissing( array $rows, string $now ): int {
		$wpdb     = $this->wpdb;
		$inserted = 0;

		foreach ( $rows as $row ) {
			$inserted += (int) $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}icl_translation_policy
						( `scope`, `scope_id`, `strength`, `source`, `editor`, `method`, `reason`, `updated_at` )
					VALUES ( %s, %s, %s, %s, NULLIF( %s, '' ), NULLIF( %s, '' ), 'migration', %s )
					ON DUPLICATE KEY UPDATE `id` = `id`",
					$row['scope'],
					(string) $row['scope_id'],
					$row['strength'],
					(string) $row['source'],
					$row['editor'] ?? '',
					$row['method'] ?? '',
					$now
				)
			);
		}

		return $inserted;
	}

	public function updateStamped( int $id, $editor, $method, string $now ): bool {
		return false !== $this->wpdb->update(
			$this->wpdb->prefix . PolicyTable::NAME,
			[
				'editor'     => $editor,
				'method'     => $method,
				'updated_at' => $now,
			],
			[
				'id'     => $id,
				'reason' => PolicyTable::REASON_MIGRATION,
			]
		);
	}

	public function deleteStamped( array $ids ): int {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}

		$wpdb    = $this->wpdb;
		$deleted = 0;
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$deleted += (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}icl_translation_policy
					WHERE `reason` = 'migration' AND `source` = '' AND `id` IN ( " . implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) . ' )',
					$chunk
				)
			);
		}

		return $deleted;
	}

	public function resetTypeAndPostEditors(): int {
		$wpdb = $this->wpdb;

		$changed = (int) $wpdb->query(
			"DELETE FROM {$wpdb->prefix}icl_translation_policy
			WHERE `scope` IN ( 'post_type', 'post' ) AND `source` = ''
				AND ( `strength` = 'choice' OR ( `strength` = 'default' AND `method` IS NULL ) )"
		);

		return $changed + (int) $wpdb->query(
			"UPDATE {$wpdb->prefix}icl_translation_policy SET `editor` = NULL
			WHERE `scope` IN ( 'post_type', 'post' ) AND `source` = '' AND `strength` = 'default'
				AND `method` IS NOT NULL AND `editor` IS NOT NULL"
		);
	}

	public function resetPostEditorsOfType( string $postType ): int {
		$wpdb = $this->wpdb;

		$changed = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE r FROM {$wpdb->prefix}icl_translation_policy r
				JOIN {$wpdb->posts} p ON p.`ID` = CAST( r.`scope_id` AS UNSIGNED )
				WHERE r.`scope` = 'post' AND r.`source` = ''
					AND ( r.`strength` = 'choice' OR ( r.`strength` = 'default' AND r.`method` IS NULL ) )
					AND CAST( p.`post_type` AS BINARY ) = CAST( %s AS BINARY )",
				$postType
			)
		);

		return $changed + (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_policy r
				JOIN {$wpdb->posts} p ON p.`ID` = CAST( r.`scope_id` AS UNSIGNED )
				SET r.`editor` = NULL
				WHERE r.`scope` = 'post' AND r.`source` = '' AND r.`strength` = 'default'
					AND r.`method` IS NOT NULL AND r.`editor` IS NOT NULL
					AND CAST( p.`post_type` AS BINARY ) = CAST( %s AS BINARY )",
				$postType
			)
		);
	}

	public function moveSiteEditor( string $editor, string $now ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_policy SET `editor` = %s, `updated_at` = %s
				WHERE `scope` = 'site' AND `scope_id` = '' AND `source` = '' AND `reason` <> 'migration'
					AND `editor` IN ( 'cte', 'ate' ) AND `editor` <> %s",
				$editor,
				$now,
				$editor
			)
		);
	}

	public function methodRowOf( string $postIdColumn, string $postTypeColumn ): string {
		$wpdb = $this->wpdb;

		return "COALESCE( NULLIF( SUBSTRING_INDEX( LEAST(
				COALESCE( (
					SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '1', LPAD( r.`id`, 20, '0' ), '|', r.`strength`, ':', r.`method` )
					FROM {$wpdb->prefix}icl_translation_policy r
					WHERE r.`scope` = 'post' AND r.`scope_id` = CAST( {$postIdColumn} AS CHAR CHARACTER SET ascii )
						AND r.`strength` IN ( 'lock', 'choice', 'default' )
						AND r.`method` IN ( 'self', 'ptc' )
					ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
					LIMIT 1
				), '9' ),
				COALESCE( (
					SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '2', LPAD( r.`id`, 20, '0' ), '|', r.`strength`, ':', r.`method` )
					FROM {$wpdb->prefix}icl_translation_policy r
					WHERE r.`scope` = 'post_type' AND r.`scope_id` = CONVERT( {$postTypeColumn} USING ascii )
						AND CAST( r.`scope_id` AS BINARY ) = CAST( {$postTypeColumn} AS BINARY )
						AND r.`strength` IN ( 'lock', 'choice', 'default' )
						AND r.`method` IN ( 'self', 'ptc' )
					ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
					LIMIT 1
				), '9' ),
				COALESCE( (
					SELECT CONCAT( FIELD( r.`strength`, 'lock', 'choice', 'default' ), '3', LPAD( r.`id`, 20, '0' ), '|', r.`strength`, ':', r.`method` )
					FROM {$wpdb->prefix}icl_translation_policy r
					WHERE r.`scope` = 'site' AND r.`scope_id` = ''
						AND r.`strength` IN ( 'lock', 'choice', 'default' )
						AND r.`method` IN ( 'self', 'ptc' )
					ORDER BY FIELD( r.`strength`, 'lock', 'choice', 'default' ), r.`id`
					LIMIT 1
				), '9' )
			), '|', -1 ), '9' ), '' )";
	}

	private static function assertFallbackEditor( string $fallbackEditor ) {
		if ( PolicyTable::EDITOR_WPML === $fallbackEditor || ! in_array( $fallbackEditor, PolicyTable::EDITORS, true ) ) {
			throw new \InvalidArgumentException( 'Invalid fallback editor.' );
		}
	}

	private static function rows( $rows ): array {
		return is_array( $rows ) ? $rows : [];
	}
}
