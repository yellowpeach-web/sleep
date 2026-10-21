<?php

namespace WPML\LanguageEditor;

class LanguagesOrder {

	public static function reconcile( $currentOrder, $activeCodes ) {
		$current = is_array( $currentOrder ) ? $currentOrder : [];
		$active  = is_array( $activeCodes ) ? $activeCodes : [];

		$activeCodes = [];
		foreach ( $active as $code ) {
			$code = (string) $code;
			if ( '' !== $code ) {
				$activeCodes[] = $code;
			}
		}

		$order = [];
		foreach ( $current as $code ) {
			$code = (string) $code;
			if ( in_array( $code, $activeCodes, true ) && ! in_array( $code, $order, true ) ) {
				$order[] = $code;
			}
		}

		foreach ( $activeCodes as $code ) {
			if ( ! in_array( $code, $order, true ) ) {
				$order[] = $code;
			}
		}

		return array_values( $order );
	}

	public static function sync( \SitePress $sitepress ) {
		$currentOrder = $sitepress->get_setting( 'languages_order', [] );
		if ( ! is_array( $currentOrder ) ) {
			$currentOrder = [];
		}

		$activeCodes = [];
		foreach ( (array) $sitepress->get_languages( false, false, true ) as $code => $row ) {
			$row = (array) $row;
			if ( isset( $row['active'] ) && '1' === (string) $row['active'] ) {
				$activeCodes[] = (string) $code;
			}
		}

		$order = self::reconcile( $currentOrder, $activeCodes );

		if ( $order === array_values( array_map( 'strval', $currentOrder ) ) ) {
			return false;
		}

		$sitepress->set_setting( 'languages_order', $order, true );

		return true;
	}
}
