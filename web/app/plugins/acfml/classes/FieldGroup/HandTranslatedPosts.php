<?php

namespace ACFML\FieldGroup;

class HandTranslatedPosts {

	const AFFECTED_POSTS_QUERY = '\WPML\TM\Settings\AffectedPostsQuery';

	const PAGE_SIZE = 500;

	public static function includes( int $postId ): bool {
		return (bool) self::filter( [ $postId ], self::localizationGroups() );
	}

	public static function countAffected( array $fieldNames, array $pendingModes = [] ): int {
		$groups = $fieldNames ? self::localizationGroups( $pendingModes ) : [];

		if ( ! $groups ) {
			return 0;
		}

		$count = count( self::filter( AttachedBlockPosts::idsForFieldNames( $fieldNames ), $groups ) );
		$query = self::affectedPostsQuery();

		if ( ! $query ) {
			return $count;
		}

		$lastPostId = 0;

		do {
			$postIds    = $query->page( $fieldNames, $lastPostId, self::PAGE_SIZE );
			$isFullPage = self::PAGE_SIZE === count( $postIds );

			if ( ! $postIds ) {
				break;
			}

			_prime_post_caches( $postIds, false, false );

			$count     += count( self::filter( $postIds, $groups ) );
			$lastPostId = (int) end( $postIds );
		} while ( $isFullPage );

		return $count;
	}

	private static function filter( array $postIds, array $groups ): array {
		if ( ! $postIds || ! $groups ) {
			return [];
		}

		return array_values(
			array_filter(
				$postIds,
				function ( $postId ) use ( $groups ) {
					return (bool) GroupsOnPost::filter( $groups, (int) $postId );
				}
			)
		);
	}

	private static function localizationGroups( array $pendingModes = [] ): array {
		return array_values(
			array_filter(
				(array) acf_get_field_groups(),
				function ( $fieldGroup ) use ( $pendingModes ) {
					$mode = $pendingModes[ (int) ( $fieldGroup['ID'] ?? 0 ) ] ?? Mode::getMode( $fieldGroup );

					return Mode::LOCALIZATION === $mode;
				}
			)
		);
	}

	private static function affectedPostsQuery(): ?object {
		return PreferenceReapplyService::make( self::AFFECTED_POSTS_QUERY );
	}
}
