<?php

namespace WPML\ContentDeletion;

use WPML\OperationRecord\BulkDeleteRecorder;

class CoreNoticeParams implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const KEYS = array( 'trashed', 'deleted', 'message', 'updated', 'locked', 'skipped', 'untrashed' );

	const COMPANION_KEYS = array( 'updated', 'locked', 'skipped' );

	private static $params = array();

	private static $captured = false;

	private static $replaced = false;

	public static function forget() {
		self::$params   = array();
		self::$captured = false;
		self::$replaced = false;
	}

	public function add_hooks() {
		add_action( 'admin_init', array( $this, 'capture' ), 0 );

		add_filter( 'wp_admin_notice_markup', array( $this, 'suppressCoreNotice' ), 10, 3 );
	}

	public function capture() {
		self::$params = array();

		foreach ( self::KEYS as $key ) {
			$value = $this->read( $key );

			if ( null !== $value ) {
				self::$params[ $key ] = $value;
			}
		}

		self::$captured = true;
	}

	public function get( $key ) {
		if ( ! in_array( $key, self::KEYS, true ) ) {
			return null;
		}

		if ( ! self::$captured ) {
			return $this->read( $key );
		}

		return isset( self::$params[ $key ] ) ? self::$params[ $key ] : null;
	}

	public function replaced() {
		self::$replaced = true;
	}

	public function suppressCoreNotice( $markup, $message, $args ) {
		if ( ! self::$replaced ) {
			return $markup;
		}

		if ( ! is_array( $args ) || ! isset( $args['id'] ) || 'message' !== $args['id'] ) {
			return $markup;
		}

		if ( ! $this->isResultScreen() ) {
			return $markup;
		}

		if ( $this->isError( $args ) ) {
			return $markup;
		}

		if ( $this->carriesOtherCount() ) {
			return $markup;
		}

		return '';
	}

	private function carriesOtherCount() {
		global $pagenow;

		if ( ItemDeleteNotice::TERM_SCREEN === $pagenow ) {
			return false;
		}

		foreach ( self::COMPANION_KEYS as $key ) {
			if ( (int) $this->get( $key ) > 0 ) {
				return true;
			}
		}

		return false;
	}

	private function isError( array $args ) {
		if ( ! isset( $args['additional_classes'] ) || ! is_array( $args['additional_classes'] ) ) {
			return false;
		}

		return in_array( 'error', $args['additional_classes'], true )
			|| in_array( 'notice-error', $args['additional_classes'], true );
	}

	private function isResultScreen() {
		global $pagenow;

		return is_string( $pagenow ) && in_array( $pagenow, ItemDeleteNotice::RESULT_SCREENS, true );
	}

	private function read( $key ) {
		return ItemDeleteNotice::requestParam( $key );
	}
}
