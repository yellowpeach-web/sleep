<?php

namespace ACFML\Repeater\Sync\Rest;

use ACFML\Repeater\Sync\CheckboxCondition;
use ACFML\Repeater\Sync\CheckboxOption;
use ACFML\Repeater\Sync\CheckboxUI;
use WPML\LIB\WP\Hooks;

class DefaultChoiceHooks implements \IWPML_Action {

	private $checkboxCondition;

	private $isEditorSave;

	private $frames = [];

	public function __construct( CheckboxCondition $checkboxCondition, ?callable $isEditorSave = null ) {
		$this->checkboxCondition = $checkboxCondition;
		$this->isEditorSave      = $isEditorSave ?: function ( $handler ) {
			return class_exists( \WPML_REST_Posts_Hooks::class )
				&& true === \WPML_REST_Posts_Hooks::is_rest_saving_post_from_editor( is_array( $handler ) ? $handler : null );
		};
	}

	public function add_hooks() {
		Hooks::onFilter( 'rest_request_before_callbacks', 10, 3 )
			->then( \WPML\FP\spreadArgs( [ $this, 'inspectDispatch' ] ) );
		Hooks::onFilter( 'rest_request_after_callbacks', 10, 3 )
			->then( \WPML\FP\spreadArgs( [ $this, 'concludeDispatch' ] ) );
		Hooks::onAction( 'wp_after_insert_post', 10, 3 )
			->then( \WPML\FP\spreadArgs( [ $this, 'storeDefaultChoice' ] ) );
	}

	public function inspectDispatch( $response, $handler = null, $request = null ) {
		$isEditorSave = ! ( $response instanceof \WP_Error )
			&& true === call_user_func( $this->isEditorSave, $handler );

		$this->frames[] = [ $isEditorSave, $request ];

		return $response;
	}

	public function concludeDispatch( $response, $handler = null, $request = null ) {
		if ( null === $request ) {
			array_pop( $this->frames );

			return $response;
		}

		foreach ( array_reverse( array_keys( $this->frames ) ) as $depth ) {
			if ( $this->frames[ $depth ][1] === $request ) {
				array_splice( $this->frames, $depth );
				break;
			}
		}

		return $response;
	}

	public function storeDefaultChoice( $postId, $post, $update ) {
		if ( ! $this->isEditorSave() || ! $update || ! $post instanceof \WP_Post || wp_is_post_revision( $post ) ) {
			return;
		}

		$fieldGroups = acf_get_field_groups( [ 'post_id' => $post->ID ] );
		if ( ! $this->checkboxCondition->isMet( $post->ID, $fieldGroups ) ) {
			return;
		}

		$trid = $this->tridOf( $post );
		if ( ! $trid ) {
			return;
		}

		$option = CheckboxOption::getUncached();
		if ( ! is_array( $option ) ) {
			$option = [];
		}
		if ( array_key_exists( $trid, $option ) ) {
			return;
		}

		$option[ $trid ] = CheckboxUI::isChecked( $trid );
		CheckboxOption::update( $option );
	}

	private function isEditorSave() {
		$top = end( $this->frames );

		return false !== $top && $top[0];
	}

	private function tridOf( \WP_Post $post ) {
		$elementType = apply_filters( 'wpml_element_type', $post->post_type );

		return (int) apply_filters( 'wpml_element_trid', false, $post->ID, $elementType );
	}
}
