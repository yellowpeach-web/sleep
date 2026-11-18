<?php

namespace WPML\TM\Dashboard\Taxonomy;

class TaxonomyMetricsStore implements \IWPML_Action, \IWPML_Frontend_Action_Loader, \IWPML_Backend_Action_Loader {

	const TRANSIENT = 'wpml_tax_dashboard_metrics';

	const GENERATION_OPTION = 'wpml_tax_dashboard_metrics_gen';

	const EXPIRATION = 86400;

	const BUMPED_BY = [
		'clean_term_cache',
		'created_term',
		'edited_term',
		'delete_term',
		'added_term_meta',
		'updated_term_meta',
		'deleted_term_meta',
		'wpml_st_string_updated',
		'icl_st_unregister_string_multi',
	];

	const BUMPED_AFTER_ASSIGN = [
		'wpml_st_assign_strings_from_domain_to_existing_package',
		'wpml_st_assign_strings_from_domain_to_new_package',
	];

	private static $bumped = false;

	private static $unsettled = false;

	public function create() {
		return $this;
	}

	public function add_hooks() {
		foreach ( self::BUMPED_BY as $hook ) {
			add_action( $hook, [ self::class, 'bump' ], 10, 0 );
		}
		foreach ( self::BUMPED_AFTER_ASSIGN as $hook ) {
			add_action( $hook, [ self::class, 'bump' ], 11, 0 );
		}
		add_action( 'icl_st_unregister_string', [ self::class, 'stringUnregistered' ], 10, 1 );
		add_action( 'wpml_string_package_owner_changed', [ self::class, 'ownerChanged' ], 10, 1 );
		add_action( 'wpml_translation_update', [ self::class, 'translationUpdated' ], 10, 1 );
		add_action( 'shutdown', [ self::class, 'requestEnded' ], PHP_INT_MAX, 0 );
	}

	public static function generation(): string {
		if ( self::$unsettled ) {
			self::move();
		}
		self::$unsettled = false;
		self::$bumped    = false;
		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}

		return (string) get_option( self::GENERATION_OPTION, '' );
	}

	public static function bump() {
		self::$unsettled = true;
		if ( self::$bumped ) {
			return;
		}
		self::$bumped = true;
		self::move();
	}

	public static function requestEnded() {
		if ( ! self::$unsettled ) {
			return;
		}
		self::$unsettled = false;
		self::move();
	}

	private static function move() {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::GENERATION_OPTION, uniqid( '', true ), false );
		}
	}

	public static function translationUpdated( $args ) {
		if ( is_array( $args ) ) {
			$type    = isset( $args['element_type'] ) ? (string) $args['element_type'] : '';
			$context = isset( $args['context'] ) ? (string) $args['context'] : '';
			if ( ( '' !== $type || '' !== $context ) && 0 !== strpos( $type, 'tax_' ) && 'tax' !== $context ) {
				return;
			}
		}
		self::bump();
	}

	public static function stringUnregistered( $stringId ) {
		if ( $stringId ) {
			self::bump();
		}
	}

	public static function ownerChanged( $elementType ) {
		if ( 0 === strpos( (string) $elementType, 'tax_' ) ) {
			self::bump();
		}
	}

	public static function read( string $key ) {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}
		$stored = get_transient( self::TRANSIENT );
		if ( ! is_array( $stored ) || ! isset( $stored['key'], $stored['metrics'] ) || $key !== $stored['key'] || ! is_array( $stored['metrics'] ) ) {
			return null;
		}

		return $stored['metrics'];
	}

	public static function write( string $key, array $metrics ) {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		set_transient(
			self::TRANSIENT,
			[
				'key'     => $key,
				'metrics' => $metrics,
			],
			self::EXPIRATION
		);
	}

	public static function forgetRequestState() {
		self::$bumped    = false;
		self::$unsettled = false;
	}
}
