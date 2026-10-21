<?php

namespace WPML\ContentDeletion;

class RestoreAnswer {

	const NONCE_ACTION = 'wpml-restore-scope';

	const FIELD_SCOPE = 'wpml_restore_scope';
	const FIELD_NONCE = 'wpml_restore_nonce';

	const SCOPE_SET = 'set';

	const SCOPE_ONE = 'one';

	const SCOPES = array(
		self::SCOPE_SET,
		self::SCOPE_ONE,
	);

	private $request;

	private $parsed = false;

	private $scope = null;

	public function __construct( ?array $request = null ) {
		$this->request = $request;
	}

	public function scope() {
		$this->parse();

		return $this->scope;
	}

	private function parse() {
		if ( $this->parsed ) {
			return;
		}

		$this->parsed = true;

		$request = null === $this->request ? $_REQUEST : $this->request;

		$scope = $this->field( $request, self::FIELD_SCOPE );
		$nonce = $this->field( $request, self::FIELD_NONCE );

		if ( null === $scope || null === $nonce ) {
			return;
		}

		if ( ! in_array( $scope, self::SCOPES, true ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		$this->scope = $scope;
	}

	private function field( array $request, $key ) {
		if ( ! isset( $request[ $key ] ) || ! is_scalar( $request[ $key ] ) ) {
			return null;
		}

		$value = trim( sanitize_text_field( wp_unslash( (string) $request[ $key ] ) ) );

		return '' === $value ? null : $value;
	}
}
