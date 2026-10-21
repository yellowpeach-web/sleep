<?php

use ACFML\FieldGroup\Mode;
use ACFML\Helper\Fields;
use ACFML\Repeater\Shuffle\Post;
use ACFML\Repeater\Shuffle\RowChange;
use ACFML\Repeater\Shuffle\Rows;
use ACFML\Repeater\Shuffle\Strategy;
use ACFML\Repeater\Sync\Condition;
use ACFML\Repeater\Sync\StaleJobs;
use ACFML\Repeater\Sync\TranslationStatus;
use WPML\FP\Obj;

class WPML_ACF_Repeater_Shuffle implements \IWPML_Backend_Action {

	const PRIORITY_BEFORE_ACF_WRITES = 5;
	const PRIORITY_AFTER_ACF_WROTE   = 15;

	private $shuffled;

	private $rowsBefore = [];

	private $levelLanguagesBefore = [];

	private $heldBack = [];

	private $unmoved = [];

	private $status;

	public function __construct( Strategy $shuffled, TranslationStatus $status ) {
		$this->shuffled = $shuffled;
		$this->status   = $status;
	}

	public function add_hooks() {
		if ( Mode::LOCALIZATION !== Mode::getForFieldableEntity( $this->shuffled->getEntityType() ) ) {
			add_action( 'acf/save_post', [ $this, 'store_state_before' ], self::PRIORITY_BEFORE_ACF_WRITES );
			add_action( 'acf/save_post', [ $this, 'update_translated_repeaters' ], self::PRIORITY_AFTER_ACF_WROTE );
			add_filter( 'wpml_custom_fields_to_sync_on_post_save', [ $this, 'holdBackRowsOfAnInsert' ], 10, 2 );
		}
	}

	public function store_state_before( $post_id = 0 ) {
		if ( $this->shouldSupportSync( $post_id ) ) {
			$this->rowsBefore           = Rows::read( $post_id );
			$this->levelLanguagesBefore = array_keys( $this->levelAmong( $this->shuffled->getTranslations( $post_id ) ) );
		}
	}

	public function shouldSupportSync( $entityId ) {
		return Condition::isActiveFor( $this->shuffled, $entityId );
	}

	public function update_translated_repeaters( $post_id = 0 ) {
		if ( ! $this->shouldSupportSync( $post_id ) ) {
			return;
		}

		$translations = $this->shuffled->getTranslations( $post_id );

		if ( ! $translations ) {
			return;
		}

		$level  = array_intersect_key( $translations, array_flip( $this->levelLanguagesBefore ) );
		$behind = array_diff_key( $translations, $level );

		$jobsOutdated = false;
		$needsUpdate  = false;
		$unmoved      = [];

		foreach ( Rows::read( $post_id ) as $field => $after ) {
			$change = RowChange::between(
				(array) Obj::pathOr( [], [ $field, 'rows' ], $this->rowsBefore ),
				$after['rows']
			);

			$jobsOutdated = $jobsOutdated || $change->outdatesJob();
			$needsUpdate  = $needsUpdate || $change->needsTranslating();

			if ( ! $change->movesRows() ) {
				if ( $change->needsTranslating() ) {
					if ( RowChange::INSERT === $change->getKind() && $this->shuffled->editedNatively( $post_id ) ) {
						$settled = array_filter( $translations, fn( $translation ) => $this->status->isSettled( $translation ) );

						foreach ( $settled as $translation ) {
							$this->followRows( $post_id, $translation->element_id, $field, $after['type'], $change->getMap() );
						}

						foreach ( array_diff_key( $translations, $settled ) as $translation ) {
							$unmoved[ (int) $translation->element_id ][] = $field;
						}
					} else {
						$this->heldBack[] = $field;
					}
				}

				continue;
			}

			foreach ( $level as $translation ) {
				$this->followRows( $post_id, $translation->element_id, $field, $after['type'], $change->getMap() );
			}

			foreach ( $behind as $translation ) {
				$unmoved[ (int) $translation->element_id ][] = $field;
			}

			if ( $behind ) {
				$this->heldBack[] = $field;
			}
		}

		$this->unmoved = $this->shuffled instanceof Post ? $unmoved : [];

		if ( $needsUpdate ) {
			foreach ( $translations as $translation ) {
				$this->status->markNeedsUpdate( $translation );
			}

			return;
		}

		if ( $jobsOutdated && $level ) {
			StaleJobs::mark( $this->shuffled->getEntityType(), $post_id, array_keys( $level ) );
		}
	}

	private function levelAmong( array $translations ): array {
		return array_filter( $translations, fn( $translation ) => $this->status->isLevelWithOriginal( $translation ) );
	}

	public function holdBackRowsOfAnInsert( $fields, $postId = 0 ) {
		if ( ! is_array( $fields ) ) {
			if ( $this->unmoved ) {
				$this->holdBackCopiesFor( $this->unmoved );
			}

			return $fields;
		}

		$unmoved = [];
		foreach ( $this->unmoved as $elementId => $heldFields ) {
			$rest = array_values( array_diff( $heldFields, $this->heldBack ) );

			if ( $rest ) {
				$unmoved[ $elementId ] = $rest;
			}
		}

		$this->unmoved = [];

		if ( $unmoved ) {
			$this->holdBackCopiesFor( $unmoved );
		}

		if ( ! $this->heldBack ) {
			return $fields;
		}

		$kept = fn( $metaKey ) => ! self::belongsToAnyOf( $metaKey, $this->heldBack );

		return array_values( array_filter( $fields, $kept ) );
	}

	private function holdBackCopiesFor( array $unmoved ) {
		if ( $unmoved && $this->shuffled instanceof Post ) {
			$this->unmoved = $unmoved;

			add_filter( 'wpml_sync_custom_field_copied_value', [ $this, 'holdBackCopiedValue' ], 10, 4 );
			add_action( 'wpml_after_save_post', [ $this, 'releaseHeldBackCopies' ] );
		}
	}

	public function holdBackCopiedValue( $value, $originalId = 0, $translatedId = 0, $metaKey = '' ) {
		$unmoved = $this->unmoved[ (int) $translatedId ] ?? [];

		return self::belongsToAnyOf( $metaKey, $unmoved )
			? $this->shuffled->getOneMeta( $translatedId, (string) $metaKey, true )
			: $value;
	}

	public function releaseHeldBackCopies() {
		$this->unmoved = [];

		remove_filter( 'wpml_sync_custom_field_copied_value', [ $this, 'holdBackCopiedValue' ], 10 );
		remove_action( 'wpml_after_save_post', [ $this, 'releaseHeldBackCopies' ] );
	}

	private function followRows( $originalId, $translatedId, $field, $type, array $map ) {
		if ( ! ( $this->shuffled instanceof Post ) || ! $this->followPostRowsInBulk( (int) $translatedId, $field, $map ) ) {
			$rowMeta = $this->readRowMeta( $translatedId, $field );

			foreach ( $rowMeta as $keys ) {
				foreach ( array_keys( $keys ) as $key ) {
					$this->shuffled->deleteOneMeta( $translatedId, $key );
				}
			}

			foreach ( $map as $newIndex => $oldIndex ) {
				if ( null === $oldIndex || ! isset( $rowMeta[ $oldIndex ] ) ) {
					continue;
				}

				foreach ( $rowMeta[ $oldIndex ] as $key => $value ) {
					$this->shuffled->updateOneMeta( $translatedId, $this->reIndex( $key, $field, $newIndex ), $value );
				}
			}
		}

		$this->followWrapper( $originalId, $translatedId, $field, $type, count( $map ) );
	}

	private function followPostRowsInBulk( $postId, $field, array $map ) {
		global $wpdb;

		if ( $postId <= 0 || ! $wpdb || empty( $wpdb->postmeta ) ) {
			return false;
		}

		$like     = $wpdb->esc_like( (string) $field ) . '\_%';
		$metaRows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND ( meta_key LIKE %s OR meta_key LIKE %s ) ORDER BY meta_id",
				$postId,
				$like,
				'\_' . $like
			),
			ARRAY_A
		);

		if ( ! is_array( $metaRows ) ) {
			return false;
		}

		$pattern = self::rowKeyPattern( $field );
		$rows    = [];
		foreach ( $metaRows as $metaRow ) {
			if ( preg_match( $pattern, (string) $metaRow['meta_key'], $matched ) ) {
				$rows[ (int) $matched[2] ][] = $metaRow;
			}
		}

		$kept = [];
		foreach ( $map as $newIndex => $oldIndex ) {
			if ( null !== $oldIndex && (int) $oldIndex === (int) $newIndex ) {
				$kept[ (int) $newIndex ] = true;
			}
		}

		$deleteIds = [];
		foreach ( $rows as $oldIndex => $entries ) {
			if ( ! isset( $kept[ $oldIndex ] ) ) {
				foreach ( $entries as $entry ) {
					$deleteIds[] = (int) $entry['meta_id'];
				}
			}
		}

		$inserts = [];
		foreach ( $map as $newIndex => $oldIndex ) {
			if ( null === $oldIndex || isset( $kept[ (int) $oldIndex ] ) || ! isset( $rows[ (int) $oldIndex ] ) ) {
				continue;
			}

			foreach ( $rows[ (int) $oldIndex ] as $entry ) {
				$inserts[] = [ $this->reIndex( $entry['meta_key'], $field, $newIndex ), (string) $entry['meta_value'] ];
			}
		}

		if ( ! $deleteIds && ! $inserts ) {
			return true;
		}

		$ok = true;
		$wpdb->query( 'START TRANSACTION' );

		foreach ( array_chunk( $deleteIds, 500 ) as $chunk ) {
			$ok = $ok && false !== $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE meta_id IN (" . implode( ', ', array_fill( 0, count( $chunk ), '%d' ) ) . ')',
					$chunk
				)
			);
		}

		$flush = function ( array $placeholders, array $args ) use ( $wpdb ) {
			return ! $placeholders || false !== $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode( ', ', $placeholders ),
					$args
				)
			);
		};

		$placeholders = [];
		$args         = [];
		$bytes        = 0;
		foreach ( $inserts as $insert ) {
			$placeholders[] = '(%d, %s, %s)';
			array_push( $args, $postId, $insert[0], $insert[1] );
			$bytes += strlen( $insert[0] ) + strlen( $insert[1] );

			if ( count( $placeholders ) >= 500 || $bytes >= 1000000 ) {
				$ok           = $ok && $flush( $placeholders, $args );
				$placeholders = [];
				$args         = [];
				$bytes        = 0;
			}
		}
		$ok = $ok && $flush( $placeholders, $args );

		$wpdb->query( $ok ? 'COMMIT' : 'ROLLBACK' );
		wp_cache_delete( $postId, 'post_meta' );

		if ( $ok && function_exists( 'wp_cache_set_posts_last_changed' ) ) {
			wp_cache_set_posts_last_changed();
		}

		return $ok;
	}

	private function readRowMeta( $translatedId, $field ) {
		$pattern = self::rowKeyPattern( $field );
		$rows    = [];

		foreach ( (array) $this->shuffled->getAllMeta( $translatedId ) as $key => $value ) {
			if ( preg_match( $pattern, (string) $key, $matched ) ) {
				$rows[ (int) $matched[2] ][ $key ] = $this->shuffled->readOneValue( $value );
			}
		}

		return $rows;
	}

	private function followWrapper( $originalId, $translatedId, $field, $type, $rowCount ) {
		$value = 'flexible_content' === $type
			? $this->shuffled->getOneMeta( $originalId, $field, true )
			: $rowCount;

		$this->shuffled->updateOneMeta( $translatedId, $field, $value );

		if ( 'flexible_content' === $type ) {
			$this->followLayoutMeta( $originalId, $translatedId, $field );
		}
	}

	private function followLayoutMeta( $originalId, $translatedId, string $field ) {
		$layoutMetaKey = self::layoutMetaKey( $field );
		$layoutMeta    = $this->shuffled->getOneMeta( $originalId, $layoutMetaKey, true );

		if ( $layoutMeta ) {
			$this->shuffled->updateOneMeta( $translatedId, $layoutMetaKey, $layoutMeta );
		} else {
			$this->shuffled->deleteOneMeta( $translatedId, $layoutMetaKey );
		}
	}

	private static function layoutMetaKey( string $field ): string {
		return '_' . $field . Fields::LAYOUT_META_SUFFIX;
	}

	private function reIndex( $key, $field, $newIndex ) {
		$rewrite = fn( array $matched ) => $matched[1] . $field . '_' . $newIndex . '_';

		return preg_replace_callback( self::rowKeyPattern( $field ), $rewrite, (string) $key, 1 );
	}

	private static function belongsToAnyOf( $metaKey, array $fields ) {
		foreach ( $fields as $field ) {
			if ( $metaKey === $field || self::layoutMetaKey( $field ) === $metaKey || preg_match( self::rowKeyPattern( $field ), (string) $metaKey ) ) {
				return true;
			}
		}

		return false;
	}

	private static function rowKeyPattern( $field ) {
		return '/^(_?)' . preg_quote( $field, '/' ) . '_(\d+)_/';
	}
}
