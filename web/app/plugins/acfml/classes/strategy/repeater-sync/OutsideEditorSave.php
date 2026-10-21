<?php

namespace ACFML\Repeater\Sync;

use ACFML\FieldState;
use ACFML\Repeater\Shuffle\Post;
use ACFML\Repeater\Shuffle\Strategy;

class OutsideEditorSave {

	const POST_META_WRITE_ACTIONS = [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ];

	const WPML_POST_SAVE_PRIORITY = 100;

	private $shuffled;

	private $entityId;

	private $rowSync;

	private $copyOnceCleanup;

	private $writtenMetaKeys = [];

	private function __construct( Strategy $shuffled, $entityId, $rowSync, $copyOnceCleanup ) {
		$this->shuffled        = $shuffled;
		$this->entityId        = $entityId;
		$this->rowSync         = $rowSync;
		$this->copyOnceCleanup = $copyOnceCleanup;
	}

	public static function ofForm( Strategy $shuffled, $entityId, TranslationStatus $status ) {
		$fieldState = new FieldState( $shuffled );
		$fieldState->storeStateBefore( $entityId );

		return new self( $shuffled, $entityId, new \WPML_ACF_Repeater_Shuffle( $shuffled, $status ), new \WPML_ACF_Custom_Fields_Sync( $fieldState ) );
	}

	public static function ofValue( Strategy $shuffled, $entityId, $status ) {
		return new self( $shuffled, $entityId, $status ? new \WPML_ACF_Repeater_Shuffle( $shuffled, $status ) : null, null );
	}

	public function start() {
		if ( $this->rowSync ) {
			$this->rowSync->store_state_before( $this->entityId );
		}

		if ( $this->copyOnceCleanup ) {
			add_filter( 'acf/update_value', [ $this->copyOnceCleanup, 'clean_empty_values_for_copy_once_field' ], 10, 3 );
		}

		if ( $this->copiesToTranslations() ) {
			foreach ( self::POST_META_WRITE_ACTIONS as $action ) {
				add_action( $action, [ $this, 'rememberWrittenMetaKey' ], 10, 3 );
			}
		}
	}

	public function rememberWrittenMetaKey( $metaId, $objectId, $metaKey ) {
		if ( (int) $objectId === (int) $this->entityId ) {
			$this->writtenMetaKeys[ (string) $metaKey ] = true;
		}
	}

	public function finish() {
		if ( $this->copyOnceCleanup ) {
			remove_filter( 'acf/update_value', [ $this->copyOnceCleanup, 'clean_empty_values_for_copy_once_field' ], 10 );
		}

		foreach ( self::POST_META_WRITE_ACTIONS as $action ) {
			remove_action( $action, [ $this, 'rememberWrittenMetaKey' ], 10 );
		}

		if ( $this->rowSync ) {
			$this->rowSync->update_translated_repeaters( $this->entityId );
		}

		if ( ! $this->copiesToTranslations() ) {
			return;
		}

		if ( self::isWpmlPostSaveAhead() ) {
			$this->leaveTheCopyToTheRunningPostSave();
		} else {
			$this->copyWrittenFieldsToTranslations();
		}
	}

	public function holdBackRowsOfAnInsert( $metaKeys, $postId = 0 ) {
		if ( ! $this->rowSync || ! $this->isEntity( $postId ) ) {
			return $metaKeys;
		}

		return $this->rowSync->holdBackRowsOfAnInsert( $metaKeys, $postId );
	}

	public function finishAfterWpmlPostSave( $postId ) {
		remove_filter( 'wpml_custom_fields_to_sync_on_post_save', [ $this, 'holdBackRowsOfAnInsert' ], 10 );
		remove_action( 'wpml_after_save_post', [ $this, 'finishAfterWpmlPostSave' ] );

		if ( ! $this->isEntity( $postId ) ) {
			$this->copyWrittenFieldsToTranslations();
		}
	}

	private function copiesToTranslations() {
		return $this->shuffled instanceof Post && $this->shuffled->isOriginal( $this->entityId );
	}

	private function isEntity( $postId ) {
		return (int) $postId === (int) $this->entityId;
	}

	private static function isWpmlPostSaveAhead() {
		global $wp_filter;

		return doing_action( 'save_post' )
			&& isset( $wp_filter['save_post'] )
			&& $wp_filter['save_post']->current_priority() < self::WPML_POST_SAVE_PRIORITY;
	}

	private function leaveTheCopyToTheRunningPostSave() {
		add_filter( 'wpml_custom_fields_to_sync_on_post_save', [ $this, 'holdBackRowsOfAnInsert' ], 10, 2 );
		add_action( 'wpml_after_save_post', [ $this, 'finishAfterWpmlPostSave' ] );
	}

	private function copyWrittenFieldsToTranslations() {
		$metaKeys = array_keys( $this->writtenMetaKeys );

		$metaKeys = $this->holdBackRowsOfAnInsert( $metaKeys, $this->entityId );

		foreach ( $metaKeys as $metaKey ) {
			do_action( 'wpml_sync_custom_field', (int) $this->entityId, $metaKey );
		}
	}
}
