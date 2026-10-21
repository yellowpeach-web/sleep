<?php

namespace WPML\Settings\PostType;

use WPML\FP\Obj;
use WPML\Setup\Option;
use WPML\WP\OptionManager;

class Automatic {

	const GROUP = 'post-type';
	const FROM_CONFIG = 'automatic-config';
	const OVERRIDE = 'automatic-override';

	public static function isAutomatic( $postType ) {
		$fromConfig = Obj::propOr( true, $postType, OptionManager::getOr( [], self::GROUP, self::FROM_CONFIG ) );
		$current    = OptionManager::getOr( [], self::GROUP, self::OVERRIDE );

		return Obj::propOr( $fromConfig, $postType, $current );
	}

	private static function getAllAutoTranslatePerPostTypeValues(): array {
		return array_merge(
			OptionManager::getOr( [], self::GROUP, self::FROM_CONFIG ),
			OptionManager::getOr( [], self::GROUP, self::OVERRIDE )
		);
	}

	public static function getAllPostTypesDisabledForAutoTranslate(): array {
		return array_keys(
			array_filter( self::getAllAutoTranslatePerPostTypeValues(), function ( $autoTranslate ) {
				return $autoTranslate === false;
			} )
		);
	}

	public static function isAnyPostTypeDisabledForAutoTranslate(): bool {
		return count( self::getAllPostTypesDisabledForAutoTranslate() ) > 0;
	}

	public static function set( $postType, $state ) {
		$current              = OptionManager::getOr( [], self::GROUP, self::OVERRIDE );
		$current[ $postType ] = $state;
		OptionManager::update( self::GROUP, self::OVERRIDE, $current );
	}

	public static function shouldTranslate( $postType ) {
		return Option::shouldTranslateEverything() && self::isAutomatic( $postType );
	}
}
