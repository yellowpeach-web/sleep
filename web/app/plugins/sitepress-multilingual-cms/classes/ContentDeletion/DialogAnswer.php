<?php

namespace WPML\ContentDeletion;

class DialogAnswer {

	const NONCE_ACTION = 'wpml-delete-scope';

	const FIELD_SCOPE   = 'wpml_delete_scope';
	const FIELD_NONCE   = 'wpml_scope_nonce';
	const FIELD_PROMOTE = 'wpml_promote_to';

	const SCOPE_SET = 'set';

	const SCOPE_ONE = 'one';

	const SCOPE_SET_CANCEL_JOBS = 'set-cancel-jobs';

	const SCOPE_ESCALATE_SET = 'escalate-set';

	const SCOPES = array(
		self::SCOPE_SET,
		self::SCOPE_ONE,
		self::SCOPE_SET_CANCEL_JOBS,
		self::SCOPE_ESCALATE_SET,
	);

	private $request;

	private $parsed = false;

	private $scope = null;

	private $promoteTo = null;

	public function __construct( ?array $request = null ) {
		$this->request = $request;
	}

	public function scope() {
		$this->parse();

		return $this->scope;
	}

	public function promoteTo() {
		$this->parse();

		return $this->promoteTo;
	}

	public function cameFromTranslation() {
		return self::SCOPE_ESCALATE_SET === $this->scope();
	}

	public function cancelsJobs() {
		return self::SCOPE_SET_CANCEL_JOBS === $this->scope();
	}

	public function originalAction() {
		switch ( $this->scope() ) {
			case self::SCOPE_SET:
			case self::SCOPE_SET_CANCEL_JOBS:
			case self::SCOPE_ESCALATE_SET:
				return Settings::ALL;

			case self::SCOPE_ONE:
				return Settings::ONLY;
		}

		return null;
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

		if ( self::SCOPE_ONE !== $scope ) {
			return;
		}

		$promote = $this->field( $request, self::FIELD_PROMOTE );

		if ( null !== $promote && 1 === preg_match( '/^[A-Za-z0-9_-]{2,35}$/', $promote ) ) {
			$this->promoteTo = $promote;
		}
	}

	private function field( array $request, $key ) {
		if ( ! isset( $request[ $key ] ) || ! is_scalar( $request[ $key ] ) ) {
			return null;
		}

		$value = trim( sanitize_text_field( wp_unslash( (string) $request[ $key ] ) ) );

		return '' === $value ? null : $value;
	}
}
