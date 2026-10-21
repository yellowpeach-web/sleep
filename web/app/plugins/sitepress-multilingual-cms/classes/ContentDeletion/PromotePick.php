<?php

namespace WPML\ContentDeletion;

class PromotePick {

	public static function pick( array $priorities, array $group, $exclude = 0, $preferred = null ) {
		return self::pickWithArm( $priorities, $group, $exclude, $preferred )['code'];
	}

	public static function pickWithArm( array $priorities, array $group, $exclude = 0, $preferred = null ): array {
		if ( is_string( $preferred ) && '' !== $preferred && self::eligible( $group, $preferred, $exclude ) ) {
			return array(
				'code'    => $preferred,
				'byOrder' => false,
			);
		}

		foreach ( $priorities as $language ) {
			$language = (string) $language;

			if ( self::eligible( $group, $language, $exclude ) ) {
				return array(
					'code'    => $language,
					'byOrder' => true,
				);
			}
		}

		foreach ( array_keys( $group ) as $language ) {
			$language = (string) $language;

			if ( self::eligible( $group, $language, $exclude ) ) {
				return array(
					'code'    => $language,
					'byOrder' => false,
				);
			}
		}

		return array(
			'code'    => null,
			'byOrder' => false,
		);
	}

	private static function eligible( array $group, $language, $exclude ) {
		return isset( $group[ $language ] ) && (int) $group[ $language ] !== (int) $exclude;
	}
}
