<?php

namespace WPML\TM\Menu;

class AntdCompatBodyClass {

	const SCOPE_CLASS = 'wpml-antd';

	const SCOPED_ADDONS_FILTER = 'wpml_antd_scoped_addons';

	const ADDON_STYLE_HANDLES = [
		'acfml' => [ 'wpml-field-group-edit-ui', 'wpml-native-editor-translation-edit-ui' ],
		'wcml'  => [ 'wpml-paymentGatewaysAdmin-ui', 'wpml-paymentGatewaysAdminSPA-ui' ],
	];

	const ADDON_CONSTANTS = [
		'acfml' => 'ACFML_VERSION',
		'wcml'  => 'WCML_VERSION',
	];

	private $isAddonActive;

	public function __construct( $isAddonActive = null ) {
		$this->isAddonActive = $isAddonActive ?: function ( $slug ) {
			return array_key_exists( $slug, self::ADDON_CONSTANTS ) && defined( self::ADDON_CONSTANTS[ $slug ] );
		};
	}

	public function addBodyClass( $classes ) {
		$classes = (string) $classes;

		if ( in_array( self::SCOPE_CLASS, preg_split( '/\s+/', $classes, -1, PREG_SPLIT_NO_EMPTY ) ?: [], true ) ) {
			return $classes;
		}

		return $this->isUnscopedAddonScreen() ? $classes . ' ' . self::SCOPE_CLASS : $classes;
	}

	private function isUnscopedAddonScreen() {
		$scopedAddons = null;

		foreach ( self::ADDON_STYLE_HANDLES as $slug => $handles ) {
			if ( ! call_user_func( $this->isAddonActive, $slug ) || ! $this->isAnyEnqueued( $handles ) ) {
				continue;
			}

			if ( null === $scopedAddons ) {
				$scopedAddons = (array) apply_filters( self::SCOPED_ADDONS_FILTER, [] );
			}

			if ( ! in_array( $slug, $scopedAddons, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function isAnyEnqueued( array $handles ) {
		foreach ( $handles as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) ) {
				return true;
			}
		}

		return false;
	}
}
