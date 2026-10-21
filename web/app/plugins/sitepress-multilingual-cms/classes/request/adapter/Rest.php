<?php

namespace WPML\Request\Adapter;

use WPML\Request\Policy\Policy;
use WPML\Request\Policy\Registry;

final class Rest {

	public static function permission( Policy $policy, $route ) {
		return new RestPermission( $policy, (string) $route );
	}

	public static function requirePolicy( $route, $permissionCallback ) {
		if ( $permissionCallback instanceof RestPermission ) {
			return $permissionCallback;
		}

		$description = is_string( $permissionCallback ) ? $permissionCallback : gettype( $permissionCallback );

		return new RestPermission(
			Policy::authorize(
				function () {
					return false;
				},
				\WPML\Request\Policy\Authenticity::none( 'undeclared route policy' ),
				'fail-closed: route registered without a Policy (' . $description . ')'
			),
			$route
		);
	}
}
