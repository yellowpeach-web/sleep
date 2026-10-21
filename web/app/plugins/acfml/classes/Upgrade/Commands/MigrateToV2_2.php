<?php

namespace ACFML\Upgrade\Commands;

use ACFML\Options;
use ACFML\FieldGroup\FieldNamePatterns;
use ACFML\Strings\Factory;
use ACFML\Strings\Package;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\Cache;

class MigrateToV2_2 implements Command {

	const KEY = 'migrate-to-v2_2';

	const STATUS_DONE = 'done';

	const INIT_PRIORITY = 2;

	public static function run() {
		Hooks::onAction( 'acf/init', self::INIT_PRIORITY )
			->then( function () {
				if ( self::isStActivated() && null === Options::get( self::KEY ) ) {
					$isLocalEnabled = acf_is_local_enabled();
					if ( $isLocalEnabled ) {
						acf_disable_local();
					}
					$allSucceeded = true;
					wpml_collect( acf_get_field_groups() )
						->filter( function ( $fieldGroup ) {
							return ! Relation::propEq( 'ID', 0, $fieldGroup );
						} )
						->map( function ( $fieldGroup ) use ( &$allSucceeded ) {
							$fieldGroupId  = Obj::prop( 'ID', $fieldGroup );
							$fieldGroupKey = Obj::prop( 'key', $fieldGroup );
							$package       = Factory::createWpmlPackage( [
								'kind'      => Package::FIELD_GROUP_PACKAGE_KIND,
								'kind_slug' => Package::FIELD_GROUP_PACKAGE_KIND_SLUG,
								'name'      => $fieldGroupId,
							] );

							$packageId = $package->get_package_id();
							if ( ! $packageId ) {
								return;
							}

							MigrateToV2_2::updatePackage( $package, $fieldGroupKey );
							if ( ! MigrateToV2_2::updatePackageStrings( $packageId, $fieldGroupKey, $fieldGroupId ) ) {
								$allSucceeded = false;
							}
							MigrateToV2_2::updateNamePatterns( $fieldGroup );
							$package->flush_cache();

							Cache::flushGroup( 'WPML_ST_CACHE' );

							do_action( 'wpml_st_refresh_domain', self::stringsDomain( $fieldGroupId ) );
							do_action( 'wpml_st_refresh_domain', self::stringsDomain( $fieldGroupKey ) );
						} );

					do_action( 'wpml_st_string_updated' );
					do_action( 'wpml_st_translation_files_process_queue' );
					if ( $isLocalEnabled ) {
						acf_enable_local();
					}
					if ( $allSucceeded ) {
						Options::set( self::KEY, self::STATUS_DONE );
					}
				}
			} );
	}

	public static function isStActivated() {
		return defined( 'WPML_ST_VERSION' );
	}

	public static function fieldIdsToKeys( $fields, $idsToKeys = [] ) {
		foreach ( $fields as $field ) {
			if ( isset( $field['ID'], $field['key'] ) ) {
				$idsToKeys[ $field['ID'] ] = $field['key'];
			}

			if ( isset( $field['sub_fields'] ) ) {
				$idsToKeys = self::fieldIdsToKeys( $field['sub_fields'], $idsToKeys );
			}

			if ( isset( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as $layout ) {
					if ( isset( $layout['sub_fields'] ) ) {
						$idsToKeys = self::fieldIdsToKeys( $layout['sub_fields'], $idsToKeys );
					}
				}
			}
		}

		return $idsToKeys;
	}

	private static function updatePackage( $package, $fieldGroupKey ) {
		$package->name  = $fieldGroupKey;
		$package->title = sprintf( Package::FIELD_GROUP_PACKAGE_TITLE, $fieldGroupKey );
		$package->update_package_record();
	}

	private static function updatePackageStrings( $packageId, $fieldGroupKey, $fieldGroupId ) {
		global $wpdb;
		$packageStrings = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT id, name
				FROM {$wpdb->prefix}icl_strings
				WHERE string_package_id = %d
				",
				$packageId
			)
		);
		if ( empty( $packageStrings ) ) {
			return true;
		}

		$newContext = self::stringsDomain( $fieldGroupKey );
		$contextUpdated = $wpdb->query(
			$wpdb->prepare(
				"
				UPDATE {$wpdb->prefix}icl_strings
				SET context = %s
				WHERE string_package_id = %d
				LIMIT %d
				",
				$newContext,
				$packageId,
				count( $packageStrings )
			)
		);
		if ( false === $contextUpdated ) {
			return false;
		}

		$fieldsInFieldGroup = acf_get_fields( $fieldGroupId );
		if ( empty( $fieldsInFieldGroup ) ) {
			return true;
		}

		$fieldIdsToKeys  = self::fieldIdsToKeys( $fieldsInFieldGroup );
		$pattern         = '/^(group-|field-)([0-9]+)(-.*?)$/';
		$entriesToUpdate = wpml_collect( $packageStrings )
			->map( function ( $string ) use ( $pattern, $fieldGroupKey, $fieldIdsToKeys ) {
				$hasMatch = preg_match( $pattern, $string->name, $matches );
				if ( ! $hasMatch ) {
					return null;
				}

				$itemKey = ( 'group-' === $matches[1] )
					? $fieldGroupKey
					: Obj::prop( $matches[2], $fieldIdsToKeys );


				return (bool) $itemKey
					? [
						'id'   => $string->id,
						'name' => $matches[1] . $itemKey . $matches[3],
					]
					: null;
			} )
			->filter()
			->toArray();

		if ( empty( $entriesToUpdate ) ) {
			return true;
		}

		$buildCase = function ( $entryToUpdate ) use ( $wpdb ) {
			return $wpdb->prepare( 'WHEN %d THEN %s', $entryToUpdate['id'], $entryToUpdate['name'] );
		};
		$buildId   = function ( $entryToUpdate ) use ( $wpdb ) {
			return $wpdb->prepare( '%d', $entryToUpdate['id'] );
		};
		$cases     = array_map( $buildCase, $entriesToUpdate );
		$ids       = array_map( $buildId, $entriesToUpdate );

		$updateStringNameQuery = "
			UPDATE {$wpdb->prefix}icl_strings
			SET name = CASE id " . implode( ' ', $cases ) . ' END
			WHERE id IN (' . implode( ', ', $ids ) . ')';

		$result = $wpdb->query( $updateStringNameQuery );

		return false !== $result;
	}

	private static function stringsDomain( $fieldGroupIdentifier ) {
		return Package::FIELD_GROUP_PACKAGE_KIND_SLUG . '-' . $fieldGroupIdentifier;
	}

	private static function updateNamePatterns( $fieldGroup ) {
		$fieldNamePatterns = new FieldNamePatterns();
		$fieldGroupId      = Obj::prop( 'ID', $fieldGroup );
		$fieldNamePatterns->updateGroup( $fieldGroupId, [] );
		$fieldNamePatterns->updateFieldNamePatterns( $fieldGroup );
	}

}
