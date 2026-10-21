<?php

namespace ACFML\FieldGroup;

class SubfieldPreferenceReapplyHooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	private $subfieldRows;

	private $translationStatus;

	private $syncFactory;

	private $elementFactory;

	public function __construct(
		SubfieldRows $subfieldRows,
		TranslationStatusService $translationStatus,
		SyncCustomFieldsFactory $syncFactory,
		\WPML_Translation_Element_Factory $elementFactory
	) {
		$this->subfieldRows      = $subfieldRows;
		$this->translationStatus = $translationStatus;
		$this->syncFactory       = $syncFactory;
		$this->elementFactory    = $elementFactory;
	}

	public function add_hooks() {
		add_filter( 'wpml_preference_reapply_affected_posts', [ $this, 'addSubfieldPosts' ], BlockPreferenceReapplyHooks::PRIORITY, 2 );
		add_action( 'wpml_preference_reapply_batch_completed', [ $this, 'applyToSubfieldRows' ], BlockPreferenceReapplyHooks::PRIORITY, 2 );
	}

	public function addSubfieldPosts( $postIds, $changes ) {
		$subfieldPostIds = $this->subfieldRows->postIdsForFieldNames( array_keys( (array) $changes ) );

		return array_merge( array_map( 'intval', (array) $postIds ), $subfieldPostIds );
	}

	public function applyToSubfieldRows( $postIds, $changes ) {
		if ( ! $postIds ) {
			return;
		}

		$postIds = (array) $postIds;

		foreach ( SubfieldRows::namesByNewPreference( $changes ) as $preference => $fieldNames ) {
			$rowKeys = $this->subfieldRows->rowKeysWithin( $fieldNames, $postIds );

			if ( ! $rowKeys ) {
				continue;
			}

			if ( WPML_TRANSLATE_CUSTOM_FIELD === $preference ) {
				$this->translationStatus->markNeedsUpdate( array_keys( $rowKeys ) );
			} elseif ( WPML_COPY_CUSTOM_FIELD === $preference ) {
				$this->copy( $rowKeys );
			} else {
				$this->copyOnce( $rowKeys );
			}
		}
	}

	private function copy( array $rowKeys ) {
		foreach ( $rowKeys as $postId => $metaKeys ) {
			$this->syncFactory->create( SubfieldRows::withReferences( SubfieldRows::TYPE_POST, $postId, $metaKeys ) )->sync_all_custom_fields( $postId );
		}
	}

	private function copyOnce( array $rowKeys ) {
		foreach ( $rowKeys as $postId => $metaKeys ) {
			$metaKeys = SubfieldRows::withReferences( SubfieldRows::TYPE_POST, $postId, $metaKeys );
			$sync     = $this->syncFactory->create( $metaKeys );

			foreach ( $this->translationIds( $postId ) as $translationId ) {
				foreach ( $metaKeys as $metaKey ) {
					if ( self::isEmptyInTranslation( $translationId, $metaKey ) ) {
						$sync->sync_custom_field( $postId, $translationId, $metaKey );
					}
				}
			}
		}
	}

	private static function isEmptyInTranslation( int $translationId, string $metaKey ) {
		$current = get_post_meta( $translationId, $metaKey, false );

		$values = apply_filters(
			'wpml_custom_field_values',
			$current ? [ $current ] : [],
			[
				'post_id'                   => $translationId,
				'meta_key'                  => $metaKey,
				'custom_fields_translation' => WPML_COPY_ONCE_CUSTOM_FIELD,
			]
		);

		return empty( $values );
	}

	private function translationIds( int $postId ) {
		$translationIds = [];

		foreach ( (array) $this->elementFactory->create( $postId, 'post' )->get_translations() as $translation ) {
			$translationId = (int) $translation->get_element_id();

			if ( $translationId && $translationId !== (int) $postId ) {
				$translationIds[] = $translationId;
			}
		}

		return $translationIds;
	}
}
