<?php

namespace ACFML\Repeater\Sync;

use ACFML\FieldGroup\Mode;
use ACFML\Helper\FieldGroup;
use ACFML\Helper\Fields;
use ACFML\Repeater\Shuffle\Strategy;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class PostHooks implements \IWPML_Backend_Action {

	private $shuffled;

	private $checkboxCondition;

	public function __construct(
		Strategy $shuffled,
		CheckboxCondition $checkboxCondition
	) {
		$this->shuffled          = $shuffled;
		$this->checkboxCondition = $checkboxCondition;
	}

	public function add_hooks() {
		Hooks::onAction( 'acf/add_meta_boxes', 10, 3 )
			->then( spreadArgs( [ $this, 'addMetaBox' ] ) );
		Hooks::onAction( 'acf/add_meta_boxes', 10, 3 )
			->then( spreadArgs( [ $this, 'resetFieldValues' ] ) );
	}

	public function addMetaBox( $postType, $post, $fieldGroups ) {
		if ( ! $this->checkboxCondition->isMet( $post->ID, $fieldGroups ) ) {
			return;
		}

		CheckboxUI::addMetaBox(
			$this->shuffled->getTrid( $post->ID ),
			$post->post_type
		);
	}

	public function resetFieldValues() {
		if ( FieldGroup::isTranslatable()) {
			acf_get_store( 'values' )->reset();
		}
	}
}
