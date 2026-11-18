<?php

namespace ACFML\TranslationPolicy;

use ACFML\FieldGroup\Cache;
use ACFML\Options;

class LockHooks implements \IWPML_Action {

	const GROUP_ACTIONS = [ 'acf/update_field_group', 'acf/trash_field_group', 'acf/untrash_field_group', 'acf/delete_field_group' ];

	const SAVE_POST_PRIORITY = 1000;

	private $rows;

	private $isCutOver;

	private $syncQueued = false;

	public function __construct( LockRows $rows, callable $isCutOver ) {
		$this->rows      = $rows;
		$this->isCutOver = $isCutOver;
	}

	public function add_hooks() {
		foreach ( self::GROUP_ACTIONS as $action ) {
			add_action( $action, [ $this, 'queueSync' ] );
		}
		add_action( 'save_post', [ $this, 'syncPost' ], self::SAVE_POST_PRIORITY, 2 );

		if ( ! Options::get( LockRows::OPTION_SYNCED ) && ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) ) {
			add_action( 'wp_loaded', [ $this, 'syncOnce' ] );
		}
	}

	public function syncOnce() {
		if ( call_user_func( $this->isCutOver ) && $this->rows->sync() ) {
			Options::set( LockRows::OPTION_SYNCED, true );
		}
	}

	public function queueSync() {
		if ( ! $this->syncQueued ) {
			$this->syncQueued = true;
			add_action( 'shutdown', [ $this, 'sync' ] );
		}
	}

	public function sync() {
		if ( call_user_func( $this->isCutOver ) ) {
			$this->rows->sync();
		}
	}

	public function syncPost( $postId, $post ) {
		if ( ! $post instanceof \WP_Post || wp_is_post_revision( $post ) || \ACFML\FieldReferenceAdjuster::FIELD_POST_TYPE === $post->post_type ) {
			return;
		}
		if ( \WPML_ACF_Translatable_Groups_Checker::POST_TYPE === $post->post_type ) {
			$this->queueSync();
			return;
		}
		if ( ! Cache::hasLocalizationGroup() || ! $this->rows->isTranslated( $post->post_type ) || ! call_user_func( $this->isCutOver ) ) {
			return;
		}

		$originalId = (int) apply_filters( 'wpml_original_element_id', null, (int) $postId, 'post_' . $post->post_type );
		if ( ! $originalId || (int) $postId === $originalId ) {
			$this->rows->syncPost( (int) $postId );
		}
	}
}
