<?php

namespace WPML\TM\ATE\TranslateEverything;

class Preflight {

	const FILTER = 'wpml_translate_everything_preflight';

	const MAX_ACTIONS = 3;

	public static function collect() {
		$providers = apply_filters( self::FILTER, array() );

		if ( ! is_array( $providers ) || ! $providers ) {
			return array();
		}

		$advisories = array();
		foreach ( $providers as $provider ) {
			$advisory = is_callable( $provider ) ? $provider() : $provider;
			$advisory = self::normalize( $advisory );
			if ( null !== $advisory ) {
				$advisories[] = $advisory;
			}
		}

		return $advisories;
	}

	const ADVISORIES_FILTER = 'wpml_translate_everything_preflight_advisories';

	public static function advisories( $advisories = array() ) {
		return is_array( $advisories ) && $advisories ? $advisories : self::collect();
	}

	public static function hasAny() {
		return (bool) self::collect();
	}

	private static function normalize( $advisory ) {
		if ( ! is_array( $advisory ) ) {
			return null;
		}

		$id      = isset( $advisory['id'] ) && is_scalar( $advisory['id'] ) ? trim( (string) $advisory['id'] ) : '';
		$message = isset( $advisory['message'] ) && is_scalar( $advisory['message'] ) ? trim( (string) $advisory['message'] ) : '';

		if ( '' === $id || '' === $message ) {
			return null;
		}

		return array(
			'id'      => $id,
			'message' => $message,
			'counts'  => self::normalizeCounts( isset( $advisory['counts'] ) ? $advisory['counts'] : array() ),
			'actions' => self::normalizeActions( isset( $advisory['actions'] ) ? $advisory['actions'] : array() ),
		);
	}

	private static function normalizeCounts( $counts ) {
		if ( ! is_array( $counts ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $counts as $label => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$label = trim( (string) $label );
			if ( '' === $label ) {
				continue;
			}
			$normalized[ $label ] = is_numeric( $value ) ? (int) $value : (string) $value;
		}

		return $normalized;
	}

	private static function normalizeActions( $actions ) {
		if ( ! is_array( $actions ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $actions as $action ) {
			if ( count( $normalized ) >= self::MAX_ACTIONS ) {
				break;
			}
			if ( ! is_array( $action ) || ! isset( $action['label'], $action['url'] ) ) {
				continue;
			}
			if ( ! is_scalar( $action['label'] ) || ! is_scalar( $action['url'] ) ) {
				continue;
			}

			$label = trim( (string) $action['label'] );
			$url   = esc_url_raw( (string) $action['url'] );
			if ( '' === $label || '' === $url ) {
				continue;
			}

			$normalized[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}

		return $normalized;
	}
}
