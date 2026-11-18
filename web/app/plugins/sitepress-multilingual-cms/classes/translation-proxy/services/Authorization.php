<?php

namespace WPML\TM\TranslationProxy\Services;

use WPML\TM\TranslationProxy\Services\Project\Manager;

class Authorization {
	private $storage;

	private $projectManager;

	public function __construct( Storage $storage, Manager $projectManager ) {
		$this->storage        = $storage;
		$this->projectManager = $projectManager;
	}

	public function authorize( \stdClass $credentials ) {
		$service                     = $this->getCurrentService();
		$service->custom_fields_data = $this->declaredCredentials( $service, $credentials );

		$project = $this->projectManager->create( $service );
		$this->storage->setCurrentService( $service );

		do_action( 'wpml_tm_translation_service_authorized', $service, $project );
	}

	public function updateCredentials( \stdClass $credentials ) {
		$service     = $this->getCurrentService();
		$credentials = $this->declaredCredentials( $service, $credentials );

		$this->projectManager->updateCredentials( $service, $credentials );

		$service->custom_fields_data = $credentials;
		$this->storage->setCurrentService( $service );
	}

	public function deauthorize() {
		$service                     = clone $this->getCurrentService();
		$service->custom_fields_data = null;

		$this->storage->setCurrentService( $service );

		do_action( 'wpml_tp_service_de_authorized', $service );
	}

	private function declaredCredentials( \stdClass $service, \stdClass $credentials ) {
		$declared = $this->declaredFieldNames( $service );

		$clean = new \stdClass();
		foreach ( get_object_vars( $credentials ) as $name => $value ) {
			if ( is_scalar( $value ) && ( [] === $declared || in_array( $name, $declared, true ) ) ) {
				$clean->{$name} = (string) $value;
			}
		}

		return $clean;
	}

	private function declaredFieldNames( \stdClass $service ) {
		$fields = isset( $service->custom_fields ) ? $service->custom_fields : [];
		if ( is_object( $fields ) && isset( $fields->custom_fields ) ) {
			$fields = $fields->custom_fields;
		}
		$names = [];
		foreach ( (array) $fields as $field ) {
			$name = is_object( $field ) ? ( isset( $field->name ) ? $field->name : null ) : ( is_array( $field ) && isset( $field['name'] ) ? $field['name'] : null );
			if ( is_string( $name ) && '' !== $name ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	private function getCurrentService() {
		$service = $this->storage->getCurrentService();
		if ( (bool) $service === false ) {
			throw new \RuntimeException( 'Tried to authenticate a service, but no service is active!' );
		}

		return $service;
	}
}
