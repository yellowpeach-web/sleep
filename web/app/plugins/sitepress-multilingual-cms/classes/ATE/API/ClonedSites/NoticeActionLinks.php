<?php

namespace WPML\TM\ATE\ClonedSites;

trait NoticeActionLinks {

	private static function actionUrl( $action ) {
		return wp_nonce_url(
			add_query_arg( self::ACTION_PARAM, $action, self::currentAdminUrl() ),
			self::NONCE_ACTION
		);
	}

	private static function currentAdminUrl() {
		$requestUri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';

		return remove_query_arg(
			[ self::ACTION_PARAM, self::RESULT_PARAM, '_wpnonce' ],
			$requestUri
		);
	}

	private function redirectBack( $result ) {
		$url = self::currentAdminUrl();

		if ( $result ) {
			$url = add_query_arg( self::RESULT_PARAM, $result, $url );
		}

		$this->redirect( $url );
	}

	protected function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	private static function requestedAction() {
		return self::readQueryArg( self::ACTION_PARAM );
	}

	private static function requestedResult() {
		return self::readQueryArg( self::RESULT_PARAM );
	}

	private static function readQueryArg( $name ) {
		return isset( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( $_GET[ $name ] ) ) : '';
	}
}
