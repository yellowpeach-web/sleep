<?php

namespace ACFML\TranslationPolicy;

use ACFML\FieldGroup\AttachedBlockPosts;
use ACFML\FieldGroup\GroupsOnPost;
use ACFML\FieldGroup\Mode;
use WPML\TranslationPolicy\Policy\Constraints;
use WPML\TranslationPolicy\Policy\PolicyTable;

class LockRows {

	const SOURCE = 'acfml';

	const REASON = 'different-fields';

	const BATCH = 500;

	const PAGE_PARAMS = [ 'page', 'page_type', 'page_parent', 'page_template' ];

	const OPTION_SYNCED = 'translation-policy-locks-synced';

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function sync(): bool {
		$groups  = $this->localizationGroups();
		$types   = $this->lockedTypes( $groups );
		$perPost = array_values( array_filter( $groups, [ $this, 'needsPerPostRows' ] ) );
		$wanted  = [
			PolicyTable::SCOPE_TYPE => array_fill_keys( $types, true ),
			PolicyTable::SCOPE_POST => [],
		];

		if ( $perPost ) {
			$postTypes                         = array_diff( $this->postTypesToScan( $perPost ), $types );
			$wanted[ PolicyTable::SCOPE_POST ] = array_fill_keys( $this->postsShowing( $perPost, $postTypes ), true );
		}

		$isAllWritten = true;
		foreach ( Constraints::registered( self::SOURCE, PolicyTable::STRENGTH_LOCK ) as $row ) {
			if ( isset( $wanted[ $row['scope'] ][ $row['scope_id'] ] ) ) {
				unset( $wanted[ $row['scope'] ][ $row['scope_id'] ] );
			} else {
				$isAllWritten = Constraints::unregister( self::SOURCE, PolicyTable::STRENGTH_LOCK, $row['scope'], $row['scope_id'] ) && $isAllWritten;
			}
		}

		foreach ( $wanted as $scope => $scopeIds ) {
			foreach ( array_keys( $scopeIds ) as $scopeId ) {
				$isAllWritten = $this->lock( $scope, (string) $scopeId ) && $isAllWritten;
			}
		}

		return $isAllWritten;
	}

	public function syncPost( int $postId ): bool {
		$postType = (string) get_post_type( $postId );
		$groups   = $this->localizationGroups();

		$needsRow = $postType
			&& ! in_array( $postType, $this->lockedTypes( $groups ), true )
			&& $this->isShownOn( array_values( array_filter( $groups, [ $this, 'needsPerPostRows' ] ) ), $postId );

		return $needsRow
			? $this->lock( PolicyTable::SCOPE_POST, (string) $postId )
			: Constraints::unregister( self::SOURCE, PolicyTable::STRENGTH_LOCK, PolicyTable::SCOPE_POST, (string) $postId );
	}

	public function lockedTypes( array $groups ): array {
		$types = [];
		foreach ( $groups as $group ) {
			foreach ( (array) ( $group['location'] ?? [] ) as $rules ) {
				$type = self::wholeType( (array) $rules );
				if ( $type && $this->isTranslated( $type ) ) {
					$types[ $type ] = true;
				}
			}
		}

		return array_keys( $types );
	}

	public function needsPerPostRows( array $group ): bool {
		if ( AttachedBlockPosts::blockNamesForGroup( $group ) ) {
			return true;
		}

		foreach ( (array) ( $group['location'] ?? [] ) as $rules ) {
			if ( ! self::wholeType( (array) $rules ) ) {
				return true;
			}
		}

		return false;
	}

	public function postTypesToScan( array $groups ): array {
		$all   = array_values( array_filter( get_post_types( [], 'names' ), [ $this, 'isTranslated' ] ) );
		$types = [];

		foreach ( $groups as $group ) {
			if ( AttachedBlockPosts::blockNamesForGroup( $group ) ) {
				return $all;
			}
			foreach ( (array) ( $group['location'] ?? [] ) as $rules ) {
				$named = self::typesNamedIn( (array) $rules );
				if ( ! $named ) {
					return $all;
				}
				$types += array_fill_keys( $named, true );
			}
		}

		return array_values( array_intersect( $all, array_keys( $types ) ) );
	}

	private function localizationGroups(): array {
		$filters = acf_enable_filters();
		$groups  = (array) acf_get_field_groups();
		acf_set_filters( $filters );

		return array_values(
			array_filter(
				$groups,
				fn( $group ) => is_array( $group ) && GroupsOnPost::isActive( $group ) && Mode::LOCALIZATION === Mode::getMode( $group )
			)
		);
	}

	private function postsShowing( array $groups, array $postTypes ): array {
		$wpdb = $this->wpdb;

		if ( ! $postTypes ) {
			return [];
		}

		$elementTypes = array_map( fn( $type ) => 'post_' . $type, $postTypes );
		$posts        = [];
		$lastId       = 0;

		do {
			$ids = array_map(
				'intval',
				(array) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} p
						JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = CONCAT( 'post_', p.post_type )
						WHERE t.source_language_code IS NULL
							AND p.post_status NOT IN ( 'auto-draft', 'inherit' )
							AND p.ID > %d
							AND t.element_type IN ( " . implode( ', ', array_fill( 0, count( $elementTypes ), '%s' ) ) . ' )
						ORDER BY p.ID LIMIT %d',
						array_merge( [ $lastId ], $elementTypes, [ self::BATCH ] )
					)
				)
			);

			foreach ( $ids as $postId ) {
				if ( $this->isShownOn( $groups, $postId ) ) {
					$posts[] = $postId;
				}
			}

			$read   = count( $ids );
			$lastId = $read ? (int) end( $ids ) : 0;
		} while ( self::BATCH === $read );

		return $posts;
	}

	private function isShownOn( array $groups, int $postId ): bool {
		return $groups && (bool) GroupsOnPost::filter( $groups, $postId );
	}

	private function lock( string $scope, string $scopeId ): bool {
		return Constraints::register(
			self::SOURCE,
			PolicyTable::STRENGTH_LOCK,
			$scope,
			$scopeId,
			PolicyTable::EDITOR_NATIVE,
			PolicyTable::METHOD_SELF,
			self::REASON
		);
	}

	public function isTranslated( $postType ): bool {
		return (bool) apply_filters( 'wpml_is_translated_post_type', false, $postType );
	}

	private static function wholeType( array $rules ): string {
		if ( 1 !== count( $rules ) ) {
			return '';
		}

		return self::postTypeOf( (array) reset( $rules ) );
	}

	private static function postTypeOf( array $rule ): string {
		return 'post_type' === ( $rule['param'] ?? '' ) && '==' === ( $rule['operator'] ?? '' ) && is_string( $rule['value'] ?? null )
			? $rule['value']
			: '';
	}

	private static function typesNamedIn( array $rules ): array {
		foreach ( $rules as $rule ) {
			$rule = (array) $rule;
			$type = self::postTypeOf( $rule );
			if ( $type ) {
				return [ $type ];
			}
			if ( in_array( $rule['param'] ?? '', self::PAGE_PARAMS, true ) ) {
				return [ 'page' ];
			}
		}

		return [];
	}
}
