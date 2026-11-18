<?php

namespace WPML\TM\Dashboard\Taxonomy;

class TaxonomySelectionPlan {

	private $data;

	public function __construct( ?TaxonomyDashboardData $data = null ) {
		$this->data = $data ?: new TaxonomyDashboardData();
	}

	public function build( array $input ): array {
		$taxonomy  = (string) ( $input['taxonomy'] ?? '' );
		$overwrite = (bool) ( $input['overwrite'] ?? false );
		$selection = $input['selection'] ?? [];
		$mode      = is_array( $selection ) ? ( $selection['mode'] ?? '' ) : '';
		if ( ! is_array( $selection ) || ! in_array( $mode, [ 'all', 'include', 'exclude', 'branch' ], true ) ) {
			throw new \InvalidArgumentException( 'Invalid taxonomy selection.' );
		}

		$languages = array_values( array_unique( array_map( 'strval', $input['targetLanguages'] ?? [] ) ) );
		sort( $languages, SORT_STRING );
		if ( ! $languages || $languages !== array_values( $this->data->filterTranslatableLanguages( $languages ) ) ) {
			throw new \InvalidArgumentException( 'Invalid or paused target language.' );
		}
		$ids        = $this->uniqueIds( $selection['ids'] ?? [] );
		$branches   = $this->uniqueIds( $selection['branches'] ?? [] );
		$exceptions = $this->uniqueIds( $selection['exceptions'] ?? [] );
		$facts      = $this->data->getSelectionFacts( $taxonomy, $languages, ! empty( $input['withWords'] ) );
		if ( isset( $input['lastElementId'] ) ) {
			$lastElementId  = (int) $input['lastElementId'];
			$facts['terms'] = array_filter(
				$facts['terms'],
				function ( int $id ) use ( $lastElementId ): bool {
					return $id <= $lastElementId;
				},
				ARRAY_FILTER_USE_KEY
			);
		}
		$terms = $facts['terms'];
		foreach ( array_merge( $ids, $branches, $exceptions ) as $id ) {
			if ( ! isset( $terms[ $id ] ) ) {
				throw new \InvalidArgumentException( 'Selection contains a term outside the original taxonomy.' );
			}
		}

		$inBranches = [];
		if ( 'all' !== $mode ) {
			foreach ( $branches as $branch ) {
				$inBranches += array_fill_keys( $this->branchIds( $terms, $branch ), true );
			}
		}
		foreach ( $exceptions as $id ) {
			if ( ! isset( $inBranches[ $id ] ) ) {
				throw new \InvalidArgumentException( 'A selection exception lies under no selected branch.' );
			}
		}
		$excepted = array_fill_keys( $exceptions, true );

		$addedParents = [];
		if ( 'all' === $mode ) {
			$selected = array_fill_keys( array_keys( $terms ), true );
		} elseif ( 'exclude' === $mode ) {
			$excluded = array_diff_key( array_fill_keys( $ids, true ) + $inBranches, $excepted );
			$selected = array_diff_key( array_fill_keys( array_keys( $terms ), true ), $excluded );
		} else {
			$selected = array_diff_key( array_fill_keys( $ids, true ) + $inBranches, $excepted );
			foreach ( array_keys( $selected ) as $id ) {
				$parent = (int) $terms[ $id ]['parent'];
				while ( $parent && isset( $terms[ $parent ] ) ) {
					if ( ! $this->isComplete( $facts, $parent, $languages ) ) {
						if ( ! isset( $selected[ $parent ] ) ) {
							$addedParents[ $parent ] = $parent;
						}
						$selected[ $parent ] = true;
					}
					$parent = (int) $terms[ $parent ]['parent'];
				}
			}
		}

		$counts = [ 'sent' => 0, 'alreadyTranslated' => 0, 'inFlight' => 0, 'inTranslationWithPosts' => 0, 'words' => 0 ];
		$perLanguage = array_fill_keys(
			$languages,
			$counts + [ 'notTranslated' => 0, 'needsUpdate' => 0, 'resent' => 0, 'queued' => 0, 'addedParentsNotTranslated' => 0 ]
		);
		$termIds = ! empty( $input['withTermIds'] )
			? array_fill_keys( $languages, [ 'alreadyTranslated' => [], 'inFlight' => [], 'inTranslationWithPosts' => [], 'queued' => [] ] )
			: null;
		$held    = [];
		foreach ( array_keys( $selected ) as $id ) {
			foreach ( $languages as $language ) {
				if ( $language === $terms[ $id ]['sourceLanguage'] ) {
					continue;
				}
				$status = $facts['statuses'][ $id ][ $language ] ?? 'missing';
				$bucket = null;
				$carries = ! empty( $facts['carried'][ $id ][ $language ] );
				if ( in_array( $status, [ 'in_progress', 'preparing' ], true ) ) {
					++$counts['inFlight'];
					++$perLanguage[ $language ]['inFlight'];
					$bucket = 'inFlight';
					if ( 'preparing' === $status ) {
						++$perLanguage[ $language ]['queued'];
						if ( null !== $termIds ) {
							$termIds[ $language ]['queued'][] = (int) $id;
						}
					}
				} elseif ( $carries ) {
					++$counts['inTranslationWithPosts'];
					++$perLanguage[ $language ]['inTranslationWithPosts'];
					$bucket = 'inTranslationWithPosts';
				} elseif ( ! $overwrite && 'complete' === $status ) {
					++$counts['alreadyTranslated'];
					++$perLanguage[ $language ]['alreadyTranslated'];
					$bucket = 'alreadyTranslated';
				} else {
					$words = (int) ( $facts['changedWords'][ $id ][ $language ] ?? $terms[ $id ]['words'] );
					++$counts['sent'];
					$counts['words'] += $words;
					++$perLanguage[ $language ]['sent'];
					$perLanguage[ $language ]['words'] += $words;
					if ( 'complete' === $status ) {
						++$perLanguage[ $language ]['resent'];
					} elseif ( 'needs_update' === $status ) {
						++$perLanguage[ $language ]['needsUpdate'];
					} else {
						++$perLanguage[ $language ]['notTranslated'];
						if ( isset( $addedParents[ $id ] ) ) {
							++$perLanguage[ $language ]['addedParentsNotTranslated'];
						}
					}
				}
				if ( null !== $bucket && null !== $termIds ) {
					$termIds[ $language ][ $bucket ][] = (int) $id;
				}
				if ( null !== $bucket && ! $carries ) {
					$held[ (int) $id ][] = $language;
				}
			}
		}
		ksort( $held, SORT_NUMERIC );
		$addedParents = array_values( $addedParents );
		sort( $addedParents, SORT_NUMERIC );
		$selectedIds = array_map( 'intval', array_keys( $selected ) );
		sort( $selectedIds, SORT_NUMERIC );
		$carried = array_intersect_key( $facts['carried'], $selected );
		foreach ( $carried as $id => $pairs ) {
			$carried[ $id ] = array_intersect_key( $pairs, array_flip( $languages ) );
		}

		$plan = [
			'selected'               => count( $selected ),
			'sent'                   => $counts['sent'],
			'alreadyTranslated'      => $counts['alreadyTranslated'],
			'inFlight'               => $counts['inFlight'],
			'inTranslationWithPosts' => $counts['inTranslationWithPosts'],
			'held'                   => $counts['inFlight'] + $counts['inTranslationWithPosts'],
			'words'                  => $counts['words'],
			'perLanguage'            => $perLanguage,
			'addedParents'           => $addedParents,
			'canonical'              => [
				'taxonomies'  => [ $taxonomy ],
				'languages'   => $languages,
				'overwrite'   => $overwrite,
				'selection'   => [ 'mode' => $mode, 'ids' => $ids, 'branches' => $branches, 'exceptions' => $exceptions ],
				'selectedIds' => $selectedIds,
				'carried'     => $carried,
				'held'        => $held,
			],
		];
		if ( null !== $termIds ) {
			foreach ( $termIds as $language => $buckets ) {
				foreach ( $buckets as $bucket => $bucketIds ) {
					sort( $bucketIds, SORT_NUMERIC );
					$termIds[ $language ][ $bucket ] = $bucketIds;
				}
			}
			$plan['termIds'] = $termIds;
		}

		return $plan;
	}

	private function uniqueIds( array $ids ): array {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		sort( $ids, SORT_NUMERIC );

		return $ids;
	}

	private function isComplete( array $facts, int $id, array $languages ): bool {
		foreach ( $languages as $language ) {
			if ( 'complete' !== ( $facts['statuses'][ $id ][ $language ] ?? 'missing' ) ) {
				return false;
			}
		}

		return true;
	}

	private function branchIds( array $terms, int $root ): array {
		$children = [];
		foreach ( $terms as $id => $term ) {
			$children[ (int) $term['parent'] ][] = $id;
		}
		$found = [ $root => true ];
		$queue = [ $root ];
		while ( $queue ) {
			$id = array_shift( $queue );
			foreach ( $children[ $id ] ?? [] as $child ) {
				if ( ! isset( $found[ $child ] ) ) {
					$found[ $child ] = true;
					$queue[]         = $child;
				}
			}
		}

		return array_keys( $found );
	}
}
