<?php

namespace ACFML\FieldGroup;

use WPML\FP\Relation;

class Cache {

	const CACHE_GROUP = 'acfml_field_group';

	public static function getAll() {
		self::register();

		$groups = wp_cache_get( 'all', self::CACHE_GROUP );
		if ( false === $groups ) {
			$groups = acf_get_field_groups();
			wp_cache_set( 'all', $groups, self::CACHE_GROUP );
		}

		return $groups;
	}

	public static function getForPost( $postId ) {
		self::register();

		$key    = 'post:' . $postId;
		$groups = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false === $groups ) {
			$groups = GroupsOnPost::filter( self::getAll(), (int) $postId );
			wp_cache_set( $key, $groups, self::CACHE_GROUP );
		}

		return $groups;
	}

	public static function hasLocalizationGroup() {
		self::register();

		$cached = wp_cache_get( 'has_localization', self::CACHE_GROUP );
		if ( false === $cached ) {
			$cached = wpml_collect( self::getAll() )
				->first( Relation::propEq( Mode::KEY, Mode::LOCALIZATION ) ) ? 'yes' : 'no';
			wp_cache_set( 'has_localization', $cached, self::CACHE_GROUP );
		}

		return 'yes' === $cached;
	}

	private static function register() {
		wp_cache_add_non_persistent_groups( self::CACHE_GROUP );
	}
}
