<?php

namespace WPML\ST\PackageTranslation;

use WPML\Upgrade\SchemaFacts;

class Ownership {
	private $rows = [];
	private $rides = [];
	private $types = [];
	private $collation;
	private $packageFactory;
	private static $logged = [];
	private static $changed = [];

	public function __construct( ?\WPML_ST_Package_Factory $packageFactory = null ) {
		$this->packageFactory = $packageFactory;
	}

	public function supported( $default = false ) {
		try {
			return SchemaFacts::hasColumn( 'icl_string_packages', 'owner_kind' ) && SchemaFacts::hasColumn( 'icl_string_packages', 'owner_element_id' ) ? true : $default;
		} catch ( \InvalidArgumentException $e ) {
			return $default;
		}
	}

	public function owner( $default, $package ) {
		if ( ! $this->supported() ) {
			return $default;
		}
		$element = ( $row = is_object( $package ) && isset( $package->owner_kind ) ? $package : $this->row( $this->packageId( $package ) ) ) ? $this->element( $row->owner_kind, $row->owner_element_id ) : null;
		if ( ! $element ) {
			return $default;
		}
		$element['language'] = ( apply_filters( 'wpml_element_language_details', null, $element ) )->language_code ?? null;
		return $element;
	}

	public function rides( $default, $package ) {
		if ( ! $this->supported() ) {
			return $default;
		}
		$id = $this->packageId( $package );
		if ( array_key_exists( $id, $this->rides ) ) {
			return $this->rides[ $id ];
		}
		$element = ( $row = $this->row( $id ) ) ? $this->element( $row->owner_kind, $row->owner_element_id ) : null;
		if ( ! $element ) {
			return $this->rides[ $id ] = false;
		}
		$type = $element['element_type'];
		$translated = apply_filters( 0 === strpos( $type, 'tax_' ) ? 'wpml_is_translated_taxonomy' : 'wpml_is_translated_post_type', false, substr( $type, 0 === strpos( $type, 'tax_' ) ? 4 : 5 ) );
		$trid = apply_filters( 'wpml_element_trid', null, $element['element_id'], $type );
		return $this->rides[ $id ] = (bool) ( $translated && $trid );
	}

	public function ownedPackages( $default, $kind, $id ) {
		if ( ! $this->supported() ) {
			return $default;
		}
		$kind = 0 === strpos( $kind, 'post_' ) ? 'post' : ( 0 === strpos( $kind, 'tax_' ) ? 'term' : $kind );
		if ( ! in_array( $kind, [ 'post', 'term' ], true ) ) {
			return $default;
		}
		$packages = [];
		global $wpdb;
		if ( ! $this->packageFactory ) {
			$this->packageFactory = new \WPML_ST_Package_Factory();
		}
		if ( 'post' === $kind ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_string_packages WHERE (owner_kind = 'post' AND owner_element_id = %d) OR (owner_kind IS NULL AND post_id = %d)", $id, $id ) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_string_packages WHERE owner_kind = %s AND owner_element_id = %d", $kind, $id ) );
		}
		if ( ! empty( $wpdb->last_error ) ) {
			return $default;
		}
		foreach ( (array) $rows as $row ) {
			$packages[ $row->ID ] = $this->packageFactory->create_from_row( $row );
		}
		return $packages;
	}

	public function queueChangedString( $domain, $name, $oldValue, $newValue, $forceComplete, $string ) {
		if ( is_object( $string ) && ! empty( $string->id ) ) {
			$this->queueStringOwners( [ (int) $string->id ] );
		}
	}

	public function queueDeletedStrings( array $stringIds ) {
		$this->queueStringOwners( $stringIds );
	}

	private function queueStringOwners( array $stringIds ) {
		$stringIds = array_values( array_filter( array_map( 'intval', $stringIds ) ) );
		if ( ! $stringIds || ! $this->supported() ) {
			return;
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT owner_package.ID, owner_package.owner_kind, owner_package.owner_element_id
				FROM {$wpdb->prefix}icl_strings string_row
				INNER JOIN {$wpdb->prefix}icl_string_packages owner_package ON owner_package.ID = string_row.string_package_id
				WHERE string_row.id IN ( " . implode( ', ', array_fill( 0, count( $stringIds ), '%d' ) ) . " )
				AND owner_package.owner_kind IN ('post', 'term') AND owner_package.owner_element_id > 0",
				...$stringIds
			)
		);
		foreach ( (array) $rows as $row ) {
			self::recordChange( $row->owner_kind, (int) $row->owner_element_id, (int) $row->ID );
		}
	}

	public function registrationData( $postId, $owner = null ) {
		if ( ! $this->supported() ) {
			return [];
		}
		$type = is_array( $owner ) && isset( $owner['type'] ) ? $owner['type'] : ( $postId ? 'post' : null );
		$id = is_array( $owner ) && isset( $owner['id'] ) ? (int) $owner['id'] : (int) $postId;
		$valid = $id > 0 && in_array( $type, [ 'post', 'term' ], true );
		if ( $valid && 'term' === $type ) {
			$term = get_term_by( 'term_taxonomy_id', $id );
			$valid = $term && ! $term instanceof \WP_Error;
		} elseif ( $valid ) {
			$valid = (bool) get_post( $id );
		}
		$key = $type . ':' . $id;
		if ( ! $valid && $type && empty( self::$logged[ $key ] ) ) {
			self::$logged[ $key ] = true;
			error_log( 'WPML String Translation: invalid string package owner ' . $key );
		}
		return [ 'post_id' => 'post' === $type ? $id : null, 'owner_kind' => $valid ? $type : null, 'owner_element_id' => $valid ? $id : null ];
	}

	public function ridesSql( $default, $alias ) {
		if ( ! $this->supported() || ! preg_match( '/^[A-Za-z0-9_]+$/', $alias ) ) {
			return $default;
		}
		global $wpdb;
		$column = null === $this->collation ? $wpdb->get_row( "SHOW FULL COLUMNS FROM `{$wpdb->prefix}icl_translations` LIKE 'element_type'" ) : null;
		$pair = $this->collation ?? ( $this->collation = $column && $column->Collation ? [ substr( $column->Collation, 0, (int) strpos( $column->Collation, '_' ) ), $column->Collation ] : [] );
		if ( ! $pair ) {
			return $default;
		}
		$posts = $this->types( 'post' ) ? "'" . implode( "','", array_map( 'esc_sql', $this->types( 'post' ) ) ) . "'" : "''";
		$taxonomies = $this->types( 'term' ) ? "'" . implode( "','", array_map( 'esc_sql', $this->types( 'term' ) ) ) . "'" : "''";
		return "(({$alias}.owner_kind = 'post' AND EXISTS (SELECT 1 FROM {$wpdb->posts} owner_post JOIN {$wpdb->prefix}icl_translations owner_post_tr ON owner_post_tr.element_id = owner_post.ID AND owner_post_tr.element_type = CONVERT(CONCAT('post_', owner_post.post_type) USING {$pair[0]}) COLLATE {$pair[1]} WHERE owner_post.ID = {$alias}.owner_element_id AND owner_post.post_type IN ({$posts}))) OR ({$alias}.owner_kind = 'term' AND EXISTS (SELECT 1 FROM {$wpdb->term_taxonomy} owner_term JOIN {$wpdb->prefix}icl_translations owner_term_tr ON owner_term_tr.element_id = owner_term.term_taxonomy_id AND owner_term_tr.element_type = CONVERT(CONCAT('tax_', owner_term.taxonomy) USING {$pair[0]}) COLLATE {$pair[1]} WHERE owner_term.term_taxonomy_id = {$alias}.owner_element_id AND owner_term.taxonomy IN ({$taxonomies}))))";
	}

	public static function recordChange( $kind, $id, $packageId ) {
		self::$changed[ $kind . ':' . $id ] = [ $kind, $id, $packageId ];
		if ( 1 === count( self::$changed ) ) {
			add_action( 'shutdown', [ __CLASS__, 'flushChanges' ] );
		}
	}

	public static function flushChanges() {
		$changed = self::$changed;
		self::$changed = [];
		foreach ( $changed as $args ) {
			$element = ( new self() )->element( $args[0], $args[1] );
			if ( $element ) {
				do_action( 'wpml_string_package_owner_changed', $element['element_type'], $args[1], $args[2] );
			}
		}
	}

	private function element( $kind, $id ) {
		if ( 'post' === $kind && ( $post = get_post( $id ) ) ) {
			return [ 'element_type' => 'post_' . $post->post_type, 'element_id' => (int) $id ];
		}
		if ( 'term' === $kind && ( $term = get_term_by( 'term_taxonomy_id', $id ) ) && ! $term instanceof \WP_Error ) {
			$term = (array) $term;
			return [ 'element_type' => 'tax_' . $term['taxonomy'], 'element_id' => (int) $id ];
		}
		return null;
	}

	private function row( $id ) {
		if ( ! array_key_exists( $id, $this->rows ) ) {
			global $wpdb;
			$this->rows[ $id ] = $wpdb->get_row( $wpdb->prepare( "SELECT owner_kind, owner_element_id FROM {$wpdb->prefix}icl_string_packages WHERE ID = %d", $id ) );
		}
		return $this->rows[ $id ];
	}

	private function packageId( $package ) {
		$package = is_numeric( $package ) ? [ 'ID' => $package ] : (array) $package;
		return (int) ( $package['ID'] ?? $package['id'] ?? 0 );
	}

	private function types( $kind ) {
		return $this->types[ $kind ] ?? ( $this->types[ $kind ] = array_values( array_filter( 'post' === $kind ? get_post_types( [], 'names' ) : get_taxonomies( [], 'names' ), function( $type ) use ( $kind ) {
			return apply_filters( 'post' === $kind ? 'wpml_is_translated_post_type' : 'wpml_is_translated_taxonomy', false, $type );
		} ) ) );
	}
}
