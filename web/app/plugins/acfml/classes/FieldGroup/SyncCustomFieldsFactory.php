<?php

namespace ACFML\FieldGroup;

class SyncCustomFieldsFactory {

	private $elementFactory;

	public function __construct( \WPML_Translation_Element_Factory $elementFactory ) {
		$this->elementFactory = $elementFactory;
	}

	public function create( array $metaKeys ) {
		return new \WPML_Sync_Custom_Fields( $this->elementFactory, $metaKeys );
	}
}
