<?php

namespace WPML\QueryFiltering;

class CommentLanguageCacheInvalidator implements \IWPML_Action, \IWPML_Frontend_Action_Loader, \IWPML_Backend_Action_Loader {

	private $check;

	public function __construct( ?CommentLanguageMismatchCheck $check = null ) {
		$this->check = $check;
	}

	public function create() {
		return $this;
	}

	public function add_hooks() {
		add_action( 'wpml_translation_update', array( $this, 'translationUpdated' ), 10, 1 );
	}

	public function translationUpdated( $args ) {
		if ( ! is_array( $args ) ) {
			return;
		}

		$element_type = isset( $args['element_type'] ) && is_string( $args['element_type'] )
			? $args['element_type'] : '';

		if ( 'comment' === $element_type ) {
			$this->commentRowChanged( $args );

			return;
		}

		if ( 0 === strpos( $element_type, 'post_' ) && ! empty( $args['trid'] ) ) {
			$this->check()->forgetGroupByTrid( (int) $args['trid'] );

			return;
		}

		if ( '' === $element_type && $this->isStructuralRewrite( $args ) ) {
			$this->dropEverythingDerived();
		}
	}

	private function isStructuralRewrite( array $args ) {
		$type = isset( $args['type'] ) && is_string( $args['type'] ) ? $args['type'] : '';

		return in_array( $type, array( 'reset', 'element_type_update', 'initialize_language_for_post_type' ), true );
	}

	private function dropEverythingDerived() {
		$this->check()->forgetSitewideFlag();

		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( CommentLanguageMismatchCheck::CACHE_GROUP );
		} else {
			wp_cache_flush();
		}
	}

	private function commentRowChanged( array $args ) {
		$check = $this->check();
		$type  = isset( $args['type'] ) ? (string) $args['type'] : '';

		if ( 'before_delete' === $type || 'after_delete' === $type ) {
			$check->forgetSitewideFlag();
		} else {
			$check->rememberSiteHasCommentLanguageRows();
		}

		if ( empty( $args['element_id'] ) ) {
			return;
		}

		$check->forgetGroupForComment( (int) $args['element_id'] );
	}

	private function check() {
		if ( ! $this->check ) {
			global $wpdb;

			$this->check = new CommentLanguageMismatchCheck( $wpdb );
		}

		return $this->check;
	}
}
