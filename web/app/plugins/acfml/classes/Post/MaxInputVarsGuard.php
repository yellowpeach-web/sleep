<?php

namespace ACFML\Post;

class MaxInputVarsGuard implements \IWPML_Backend_Action, \IWPML_AJAX_Action {

	public function add_hooks() {
		add_action( 'wp_loaded', [ $this, 'abortIfPostTruncated' ], -100 );

		add_action( 'acf/save_post', [ $this, 'abortIfPostTruncated' ], -100 );
	}

	public function abortIfPostTruncated() {
		if ( ! $this->isAcfSubmission() || ! $this->postWasTruncated() ) {
			return;
		}

		$max      = $this->getMaxInputVars();
		$received = $this->countReceivedPostVars();
		$sent     = max( $this->countSentPostVars(), $received );

		$message = sprintf(
			/* translators: 1: number of fields the form sent, 2: the max_input_vars limit */
			__( 'WPML blocked this save to prevent data loss. The form sent about %1$s fields, but the PHP max_input_vars limit on this server (%2$s) let fewer through. Saving now would permanently delete the fields that did not arrive, including repeater rows. Nothing was saved. Ask your host to raise max_input_vars above %1$s and save again.', 'acfml' ),
			number_format_i18n( $sent ),
			number_format_i18n( $max )
		);

		if ( wp_doing_ajax() ) {
			wp_send_json_error( [ 'message' => $message ], 413 );
		}

		wp_die(
			esc_html( $message ),
			/* translators: Title of the error page shown when a field group has more fields than the server will accept in one save. */
			esc_html__( 'Save blocked to prevent data loss', 'acfml' ),
			[
				'response'  => 413,
				'back_link' => true,
			]
		);
	}

	private function isAcfSubmission() {
		if ( ! empty( $_POST['acf'] ) ) {
			return true;
		}

		if ( array_key_exists( '_acf_nonce', $_POST ) || array_key_exists( '_acf_screen', $_POST ) ) {
			return true;
		}

		if ( ! $this->isUrlEncodedRequest() ) {
			return false;
		}

		$raw = $this->getRawBody();
		if ( ! is_string( $raw ) || '' === $raw ) {
			return false;
		}

		foreach ( explode( '&', $raw ) as $pair ) {
			$key = rawurldecode( explode( '=', $pair, 2 )[0] );
			if ( 0 === strpos( $key, 'acf[' ) ) {
				return true;
			}
		}

		return false;
	}

	private function postWasTruncated() {
		$max = $this->getMaxInputVars();
		if ( $max <= 0 ) {
			return false;
		}

		$received = $this->countReceivedPostVars();
		$sent     = $this->countSentPostVars();

		if ( $sent > 0 ) {
			return $sent > $max;
		}

		return $received >= $max;
	}

	private function countReceivedPostVars() {
		$count = 0;
		array_walk_recursive( $_POST, function () use ( &$count ) {
			$count++;
		} );

		return $count;
	}

	private function countSentPostVars() {
		if ( ! $this->isUrlEncodedRequest() ) {
			return 0;
		}

		$raw = $this->getRawBody();
		if ( ! is_string( $raw ) || '' === $raw ) {
			return 0;
		}

		return count(
			array_filter(
				explode( '&', $raw ),
				static function ( $pair ) {
					return '' !== $pair;
				}
			)
		);
	}

	private function isUrlEncodedRequest() {
		$content_type = isset( $_SERVER['CONTENT_TYPE'] ) ? $_SERVER['CONTENT_TYPE'] : '';

		return false !== stripos( $content_type, 'application/x-www-form-urlencoded' );
	}

	protected function getMaxInputVars() {
		return (int) ini_get( 'max_input_vars' );
	}

	protected function getRawBody() {
		return file_get_contents( 'php://input' );
	}
}
