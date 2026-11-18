<?php

namespace WPML\WPSEO\Shared\Upgrade;

use SitePress;
use WPML\LIB\WP\User;
use WPML\Utilities\Lock;
use WPML\WPSEO\YoastSEO\Loaders;
use WPML\WPSEO\YoastSEO\Terms\Meta\Hooks as TermMetaHooks;

class TermMetaMigration implements \IWPML_Backend_Action {

	const OPTION = 'wpmlseo_term_meta_migration';
	const VERSION = 1;
	const CHUNK_SIZE = 200;
	const WAIT_CAP = 30 * DAY_IN_SECONDS;

	private $sitepress;

	public function __construct( ?SitePress $sitepress = null ) {
		$this->sitepress = $sitepress ?? $GLOBALS['sitepress'];
	}

	public function add_hooks() {
		add_action( 'init', [ $this, 'init' ] );
		add_filter( 'wpml_save_external_package_field_context', [ $this, 'mapLegacyPackageContext' ], 10, 3 );
	}

	public function init() {
		if ( Loaders::supportsPerTermPackages() && ! wp_doing_ajax() && current_user_can( User::CAP_MANAGE_OPTIONS ) ) {
			$ledger = $this->readLedger();
			if ( ! $ledger['complete'] ) {
				Lock::whileLocked( Hooks::LOCK_NAME, MINUTE_IN_SECONDS, [ $this, 'run' ] );
			} elseif ( ! $ledger['owners'] ) {
				Lock::whileLocked( Hooks::LOCK_NAME, MINUTE_IN_SECONDS, [ $this, 'addOwners' ] );
			}
		}
	}

	public function run() {
		if ( ! Loaders::supportsPerTermPackages() ) {
			return;
		}
		$ledger = $this->readLedger();
		if ( $ledger['complete'] ) {
			return;
		}

		$option = TermMetaHooks::withStoredValues( function() {
			return get_option( 'wpseo_taxonomy_meta', [] );
		} );
		$sources = $this->sourceEntries( is_array( $option ) ? $option : [] );
		$cursor  = (int) $ledger['cursor'];
		$moved   = 0;
		foreach ( array_slice( $sources, $cursor, self::CHUNK_SIZE ) as $source ) {
			if ( ! $this->moveTerm( $source ) ) {
				break;
			}
			$moved++;
		}

		$ledger['cursor'] = $cursor + $moved;
		if ( $ledger['cursor'] < count( $sources ) ) {
			if ( $moved ) {
				$this->writeLedger( $ledger );
			}
			return;
		}

		if ( ! $ledger['moved_at'] ) {
			$ledger['moved_at'] = time();
		}
		$this->cleanup( $ledger, $sources );
	}

	public function addOwners() {
		$ledger = $this->readLedger();
		if ( ! $ledger['complete'] || $ledger['owners'] ) {
			return;
		}
		Commands\AddTermMetaPackageOwners::run();
		$ledger['owners'] = true;
		$this->writeLedger( $ledger );
	}

	public function mapLegacyPackageContext( $context, $field, $job ) {
		if ( ! Loaders::supportsPerTermPackages() || TermMetaHooks::stringContext() !== $context ) {
			return $context;
		}
		$name = isset( $field->field_type ) ? (string) $field->field_type : '';
		if ( ! preg_match( '/^(.+)-(\d+)-(wpseo_[\w-]+)$/', $name, $matches ) || ! array_key_exists( $matches[3], TermMetaHooks::FIELDS ) ) {
			return $context;
		}
		$term = TermMetaHooks::withoutTermAdjustId( function() use ( $matches ) {
			return get_term( (int) $matches[2], $matches[1] );
		} );
		if ( ! $term instanceof \WP_Term ) {
			return $context;
		}
		$newContext = TermMetaHooks::stringContext( $term->term_taxonomy_id );
		$stringId   = (int) apply_filters( 'wpml_string_id', null, [ 'context' => $newContext, 'name' => $name ] );

		return $stringId ? $newContext : $context;
	}

	private function moveTerm( array $source ) {
		$term = $this->originalTerm( $source['termId'], $source['taxonomy'] );
		if ( ! $term ) {
			return true;
		}

		$package = TermMetaHooks::PACKAGE;
		$package['name'] = 'term-' . $term->term_taxonomy_id;
		$package['owner'] = [ 'type' => 'term', 'id' => (int) $term->term_taxonomy_id ];
		$context = TermMetaHooks::stringContext( $term->term_taxonomy_id );
		$moved   = [];
		foreach ( TermMetaHooks::FIELDS as $field => $fieldTitle ) {
			if ( empty( $source['meta'][ $field ] ) || ! is_string( $source['meta'][ $field ] ) ) {
				continue;
			}
			$name  = TermMetaHooks::buildStringName( $source['taxonomy'], $source['termId'], $field );
			$value = $source['meta'][ $field ];
			$oldId = $this->stringId( TermMetaHooks::stringContext(), $name );
			$newId = $this->stringId( $context, $name );
			if ( $oldId && $newId && $oldId !== $newId ) {
				return false;
			}
			if ( ! $oldId && ! $newId ) {
				do_action( 'wpml_register_string', $value, $name, $package, $fieldTitle . ': ' . $term->name, 'LINE' );
				$newId = $this->stringId( $context, $name );
				if ( ! $newId ) {
					error_log( 'WPML SEO: missing term-meta string during migration: ' . $context . '/' . $name );
					return false;
				}
			}

			$stringId = $oldId ?: $newId;
			if ( $oldId && ! $newId ) {
				do_action( 'wpml_st_move_string_to_domain', $oldId, $context );
			}
			if ( $stringId !== $this->stringId( $context, $name ) ) {
				return false;
			}
			$moved[ $name ] = [ 'id' => $stringId, 'value' => $value, 'title' => $fieldTitle . ': ' . $term->name ];
		}

		return $this->assignTermStrings( $package, $context, $moved );
	}

	private function assignTermStrings( array $package, $context, array $moved ) {
		if ( ! $moved ) {
			return true;
		}
		$first = reset( $moved );
		$name  = key( $moved );
		do_action( 'wpml_register_string', $first['value'], $name, $package, $first['title'], 'LINE' );
		$packageId = $this->packageId( $package );
		if ( ! $packageId ) {
			return false;
		}
		do_action( 'wpml_st_assign_strings_from_domain_to_existing_package', $context, $packageId );
		foreach ( $moved as $name => $string ) {
			$assignedId = (int) apply_filters( 'wpml_string_id_from_package', null, $package, $name, $string['value'] );
			if ( $assignedId !== $string['id'] ) {
				return false;
			}
		}
		return true;
	}

	private function cleanup( array $ledger, array $sources ) {
		$oldPackageId = $this->packageId( TermMetaHooks::PACKAGE );
		$jobs         = array_values( array_map( 'intval', (array) apply_filters( 'wpml_tm_package_jobs_in_flight', [], $oldPackageId ) ) );
		$waiting      = (int) ( $ledger['waiting'] ?: $ledger['moved_at'] );
		if ( $jobs && $waiting && time() - $waiting >= self::WAIT_CAP ) {
			apply_filters( 'wpml_tm_cancel_jobs', null, $jobs );
			$jobs = array_values( array_map( 'intval', (array) apply_filters( 'wpml_tm_package_jobs_in_flight', [], $oldPackageId ) ) );
			if ( ! $jobs ) {
				$this->flagAffectedTerms( $sources );
			}
		}
		if ( $jobs ) {
			if ( ! $ledger['waiting'] ) {
				$ledger['waiting'] = time();
			}
			$this->writeLedger( $ledger );
			return;
		}

		do_action( 'wpml_delete_package', TermMetaHooks::PACKAGE['name'], TermMetaHooks::PACKAGE['kind'] );
		$ledger['complete'] = true;
		$this->writeLedger( $ledger );
	}

	private function flagAffectedTerms( array $sources ) {
		foreach ( $sources as $source ) {
			$term = $this->originalTerm( $source['termId'], $source['taxonomy'] );
			if ( ! $term ) {
				continue;
			}
			$package = TermMetaHooks::PACKAGE;
			$package['name'] = 'term-' . $term->term_taxonomy_id;
			do_action( 'wpml_string_package_owner_changed', 'tax_' . $source['taxonomy'], (int) $term->term_taxonomy_id, $this->packageId( $package ) );
		}
	}

	private function originalTerm( $termId, $taxonomy ) {
		$term = TermMetaHooks::withoutTermAdjustId( function() use ( $termId, $taxonomy ) {
			return get_term( (int) $termId, $taxonomy );
		} );
		if ( ! $term instanceof \WP_Term || ! $this->sitepress->is_original_content_filter( false, (int) $term->term_taxonomy_id, 'tax_' . $taxonomy ) ) {
			return null;
		}
		return $term;
	}

	private function sourceEntries( array $option ) {
		$sources = [];
		foreach ( $option as $taxonomy => $terms ) {
			if ( ! is_array( $terms ) || ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
				continue;
			}
			foreach ( $terms as $termId => $meta ) {
				$sources[] = [ 'taxonomy' => (string) $taxonomy, 'termId' => (int) $termId, 'meta' => is_array( $meta ) ? $meta : [] ];
			}
		}
		return $sources;
	}

	private function stringId( $context, $name ) {
		return (int) apply_filters( 'wpml_string_id', null, [ 'context' => $context, 'name' => $name ] );
	}

	private function packageId( array $package ) {
		$package = apply_filters( 'wpml_st_get_string_package', null, $package );
		return is_object( $package ) && isset( $package->ID ) ? (int) $package->ID : 0;
	}

	private function readLedger() {
		$ledger = get_option( self::OPTION, [] );
		return array_merge( [ 'version' => self::VERSION, 'cursor' => 0, 'moved_at' => 0, 'waiting' => 0, 'complete' => false, 'owners' => false ], is_array( $ledger ) ? $ledger : [] );
	}

	private function writeLedger( array $ledger ) {
		$ledger['version'] = self::VERSION;
		update_option( self::OPTION, $ledger, false );
	}
}
