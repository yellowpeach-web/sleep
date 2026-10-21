<?php

namespace ACFML\Repeater\Sync;

use ACFML\Repeater\Shuffle\Post;
use ACFML\Repeater\Shuffle\Rows;
use WPML\FP\Obj;
use WPML\TM\API\Jobs;

class JobHooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	private $shuffled;

	public function __construct( Post $shuffled ) {
		$this->shuffled = $shuffled;
	}

	public function add_hooks() {
		add_filter( 'wpml_tm_can_reuse_translation_job', [ $this, 'canReuseJob' ], 10, 3 );
		add_filter( 'wpml_tm_populate_prev_translation', [ $this, 'seedRowsFromTheirOwnSource' ], 10, 3 );
		add_action( 'wpml_added_translation_jobs', [ $this, 'forgetStaleJobs' ], 10, 3 );
	}

	public function seedRowsFromTheirOwnSource( $prevTranslation, $package = [], $lang = '' ) {
		if ( ! is_array( $prevTranslation ) || ! $prevTranslation || ! is_array( $package ) ) {
			return $prevTranslation;
		}

		$originalId = (int) Obj::pathOr( 0, [ 'contents', 'original_id', 'data' ], $package );

		if ( ! $originalId || ! Condition::isActiveFor( $this->shuffled, $originalId ) ) {
			return $prevTranslation;
		}

		$translationBySource = $this->previousRowTranslations( $originalId, (string) $lang );

		if ( ! $translationBySource ) {
			return $prevTranslation;
		}

		foreach ( (array) Obj::propOr( [], 'contents', $package ) as $field => $content ) {
			if ( ! $this->belongsToARow( (string) $field, $originalId ) ) {
				continue;
			}

			$source      = (string) Obj::propOr( '', 'data', $content );
			$translation = (string) Obj::propOr( '', $source, $translationBySource );

			$prevTranslation[ $field ] = new \WPML_TM_Translated_Field( $source, $translation, '' !== $translation );
		}

		return $prevTranslation;
	}

	private function previousRowTranslations( $originalId, $lang ) {
		$trid = apply_filters( 'wpml_element_trid', null, $originalId, 'post_' . get_post_type( $originalId ) );

		if ( ! $trid || ! $lang ) {
			return [];
		}

		$job = Jobs::getTridJob( (int) $trid, $lang );

		if ( ! is_object( $job ) && ! is_array( $job ) ) {
			return [];
		}

		$byOwnSource = [];

		foreach ( (array) Obj::propOr( [], 'elements', $job ) as $element ) {
			$field = (string) Obj::propOr( '', 'field_type', $element );

			if ( ! $this->belongsToARow( $field, $originalId ) ) {
				continue;
			}

			$source      = (string) Obj::propOr( '', 'field_data', $element );
			$translation = (string) Obj::propOr( '', 'field_data_translated', $element );

			if ( '' !== $source && '' !== $translation ) {
				$byOwnSource[ $source ] = $translation;
			}
		}

		return $byOwnSource;
	}

	private function belongsToARow( $field, $originalId ) {
		$metaKey = preg_replace( '/^field-(.+)-\d+$/', '$1', $field );

		if ( $metaKey === $field ) {
			return false;
		}

		foreach ( array_keys( Rows::read( $originalId ) ) as $wrapper ) {
			if ( preg_match( '/^' . preg_quote( (string) $wrapper, '/' ) . '_\d+_/', $metaKey ) ) {
				return true;
			}
		}

		return false;
	}

	public function canReuseJob( $canReuse, $job = null, $context = [] ) {
		if ( ! $canReuse || ! is_array( $context ) ) {
			return $canReuse;
		}

		$elementId = (int) Obj::propOr( 0, 'element_id', $context );
		$language  = (string) Obj::propOr( '', 'language_code', $context );

		if ( ! $elementId || ! $language ) {
			return $canReuse;
		}

		return ! StaleJobs::isMarked( (string) Obj::propOr( '', 'element_type', $context ), $elementId, $language );
	}

	public function forgetStaleJobs( $addedJobs, $sendFrom = null, $batch = null ) {
		if ( ! $batch instanceof \WPML_TM_Translation_Batch ) {
			return;
		}

		foreach ( $batch->get_elements() as $element ) {
			foreach ( array_keys( (array) $element->get_target_langs() ) as $language ) {
				StaleJobs::clear( $element->get_element_type(), $element->get_element_id(), (string) $language );
			}
		}
	}
}
