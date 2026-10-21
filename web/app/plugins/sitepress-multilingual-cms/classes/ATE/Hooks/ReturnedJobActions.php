<?php

namespace WPML\TM\ATE\Hooks;

use WPML\TM\API\Jobs;
use WPML\TM\ATE\ReturnToken;

class ReturnedJobActions implements \IWPML_Action {

	public function add_hooks() {
		add_action( 'init', [ $this, 'callActions' ] );
	}

	public function callActions() {
		if ( ! isset( $_GET[ ReturnToken::PARAM ] ) || ! is_string( $_GET[ ReturnToken::PARAM ] ) ) {
			return;
		}
		if ( ! isset( $_GET['ate_original_id'] ) && ! isset( $_GET['ate_job_id'] ) ) {
			return;
		}
		if ( isset( $_GET['action'] ) && ReturnCommand::ACTION === $_GET['action'] ) {
			return;
		}

		$forwarded = [];
		foreach ( ReturnCommand::ATE_PARAMS as $param ) {
			if ( isset( $_GET[ $param ] ) && is_scalar( $_GET[ $param ] ) ) {
				$forwarded[ $param ] = rawurlencode( sanitize_text_field( wp_unslash( (string) $_GET[ $param ] ) ) );
			}
		}
		$token = sanitize_text_field( wp_unslash( $_GET[ ReturnToken::PARAM ] ) );

		$destination = remove_query_arg( array_merge( ReturnCommand::ATE_PARAMS, [ ReturnToken::PARAM ] ), Jobs::getCurrentUrl() );

		$this->redirect( add_query_arg( $forwarded, ReturnCommand::url( $destination, $token ) ) );
	}

	protected function redirect( $url ) {
		wp_safe_redirect( $url, 302, 'WPML' );
		exit;
	}
}
