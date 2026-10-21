<?php

namespace ACFML\FieldGroup;

class SubfieldTermPreferenceReapplyHooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	private $subfieldRows;

	private $syncFactory;

	public function __construct( SubfieldRows $subfieldRows, SyncTermMetaFactory $syncFactory ) {
		$this->subfieldRows = $subfieldRows;
		$this->syncFactory  = $syncFactory;
	}

	public function add_hooks() {
		add_filter( 'wpml_preference_reapply_affected_terms', [ $this, 'addSubfieldTerms' ], BlockPreferenceReapplyHooks::PRIORITY, 2 );
		add_action( 'wpml_preference_reapply_terms_batch_completed', [ $this, 'applyToSubfieldRows' ], BlockPreferenceReapplyHooks::PRIORITY, 2 );
	}

	public function addSubfieldTerms( $termIds, $changes ) {
		$subfieldTermIds = $this->subfieldRows->termIdsForFieldNames( array_keys( (array) $changes ) );

		return array_merge( array_map( 'intval', (array) $termIds ), $subfieldTermIds );
	}

	public function applyToSubfieldRows( $termIds, $changes ) {
		if ( ! $termIds ) {
			return;
		}

		foreach ( SubfieldRows::namesByNewPreference( $changes ) as $preference => $fieldNames ) {
			$rowKeys = $this->subfieldRows->rowKeysWithin( $fieldNames, (array) $termIds, SubfieldRows::TYPE_TERM );

			foreach ( $rowKeys as $termId => $metaKeys ) {
				$sync = $this->syncFactory->createForTerm( $termId );

				if ( $sync ) {
					$sync->copy_keys_to_translations( SubfieldRows::withReferences( SubfieldRows::TYPE_TERM, $termId, $metaKeys ), WPML_COPY_ONCE_CUSTOM_FIELD === $preference );
				}
			}
		}
	}
}
