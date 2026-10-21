<?php

namespace ACFML\FieldGroup;

use ACFML\FieldPreferences\SubfieldRules;
use ACFML\Helper\Fields;
use WPML\FP\Obj;

class SetupInventory {

	const TRANSIENT_KEY = 'acfml_field_group_setup_rows';

	const CACHE_TTL = 60;

	const SETUP_CODE_PREFERENCES = 'code-preferences';

	const SETUP_WPML_SETTINGS = 'wpml-settings';

	const SETUP_MODE = 'mode';


	const SETUP_UNCONFIGURED = 'unconfigured';

	const SOURCE_JSON = 'json';
	const SOURCE_PHP  = 'php';

	const SOURCE_JSON_NEWER = 'json-newer';

	public static function get() {
		$cached = get_transient( self::TRANSIENT_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rows = self::compute();
		set_transient( self::TRANSIENT_KEY, $rows, self::CACHE_TTL );

		return $rows;
	}

	public static function flush() {
		delete_transient( self::TRANSIENT_KEY );
	}

	public static function countUnconfigured( array $rows ) {
		$unconfigured = array_filter(
			$rows,
			function ( $row ) {
				return self::SETUP_UNCONFIGURED === $row['setupKind'];
			}
		);

		return count( $unconfigured );
	}

	public static function countBulkTargets( array $rows ) {
		$targets = array_filter(
			$rows,
			function ( $row ) {
				return self::SETUP_UNCONFIGURED === $row['setupKind'] && ! self::isReadOnlyRow( $row );
			}
		);

		return count( $targets );
	}

	public static function countUnconfiguredLocal( array $rows ) {
		$local = array_filter(
			$rows,
			function ( $row ) {
				return self::SETUP_UNCONFIGURED === $row['setupKind'] && ! empty( $row['isLocal'] );
			}
		);

		return count( $local );
	}

	public static function countUnconfiguredPendingSync( array $rows ) {
		$pending = array_filter(
			$rows,
			function ( $row ) {
				return self::SETUP_UNCONFIGURED === $row['setupKind']
					&& empty( $row['isLocal'] )
					&& ! empty( $row['isJsonFileNewer'] );
			}
		);

		return count( $pending );
	}

	public static function isReadOnlyRow( array $row ) {
		return ! empty( $row['isLocal'] ) || ! empty( $row['isJsonFileNewer'] );
	}

	private static function compute() {
		$rows                  = [];
		$translationManagement = (array) apply_filters( 'wpml_setting', [], 'translation-management' );

		foreach ( (array) acf_get_field_groups() as $group ) {
			$rows[] = self::describe( (array) $group, $translationManagement );
		}

		return $rows;
	}

	private static function describe( array $group, array $translationManagement ) {
		$id        = (int) Obj::propOr( 0, 'ID', $group );
		$isLocal   = 0 === $id;
		$jsonNewer = ! $isLocal && self::isJsonFileNewer( $group );

		$isConfigured = Mode::isConfigured( $group );
		$mode         = $isConfigured ? Mode::getMode( $group ) : null;

		$isPureOptionsPage = DetectNonTranslatableLocations::OPTIONS_PAGE_PURE
			=== DetectNonTranslatableLocations::classifyOptionsPageLocations( Obj::propOr( [], 'location', $group ) );

		$stats = self::walkFields(
			self::readFields( $group ),
			$mode,
			$isLocal ? self::getPreferencesSetOnWpmlSettings( $group, $translationManagement ) : []
		);

		return [
			'id'                   => $id,
			'key'                  => (string) Obj::propOr( '', 'key', $group ),
			'title'                => (string) Obj::propOr( '', 'title', $group ),
			'mode'                 => $mode,
			'setupKind'            => self::getSetupKind( $isConfigured, $isLocal, $stats ),
			'isOptionsPage'        => $isPureOptionsPage,
			'fieldCount'           => $stats['fieldCount'],
			'manualCount'          => $stats['manualCount'],
			'isLocal'              => $isLocal,
			'isJsonFileNewer'      => $jsonNewer,
			'source'               => self::getSource( $group, $isLocal, $jsonNewer ),
			'needsCodePreferences' => self::needsCodePreferences( $isLocal, $group, $stats ),
		];
	}

	public static function isJsonFileNewer( array $group ) {
		if ( self::SOURCE_JSON !== Obj::prop( 'local', $group ) || Obj::prop( 'private', $group ) ) {
			return false;
		}

		$modified = (int) Obj::propOr( 0, 'modified', $group );
		$id       = (int) Obj::propOr( 0, 'ID', $group );

		if ( ! $modified || ! $id ) {
			return false;
		}

		return $modified > (int) get_post_modified_time( 'U', true, $id );
	}

	private static function getSetupKind( $isConfigured, $isLocal, array $stats ) {
		if ( $isConfigured ) {
			return self::SETUP_MODE;
		}

		if ( $isLocal && $stats['declaredCount'] > 0 ) {
			return self::SETUP_CODE_PREFERENCES;
		}

		if ( $isLocal && $stats['wpmlSettingsCount'] > 0 ) {
			return self::SETUP_WPML_SETTINGS;
		}

		return self::SETUP_UNCONFIGURED;
	}

	private static function getPreferencesSetOnWpmlSettings( array $group, array $translationManagement ) {
		$preferences = [];

		foreach ( SubfieldRules::elementTypesForGroup( $group ) as $elementType ) {
			$settingIndex = 'term' === $elementType
				? SetFieldPreferencesAsDefault::INDEX_TERM
				: SetFieldPreferencesAsDefault::INDEX_POST;

			$preferences += (array) Obj::propOr( [], $settingIndex, $translationManagement );
		}

		return $preferences;
	}

	private static function readFields( array $group ) {
		$fields = acf_get_fields( $group );

		return $fields ? $fields : [];
	}

	private static function walkFields( array $fields, $mode, array $preferencesSetOnWpmlSettings ) {
		$fieldCount        = 0;
		$manualCount       = 0;
		$declaredCount     = 0;
		$wpmlSettingsCount = 0;

		if ( ! $fields ) {
			return [
				'fieldCount'        => 0,
				'manualCount'       => 0,
				'declaredCount'     => 0,
				'wpmlSettingsCount' => 0,
			];
		}

		$getPreset = self::getPresetReader( $mode );

		Fields::iterate(
			$fields,
			function ( $field, $fieldPattern ) use ( &$fieldCount, &$manualCount, &$declaredCount, &$wpmlSettingsCount, $getPreset, $preferencesSetOnWpmlSettings ) {
				if ( ModeValidity::storesNoValue( $field ) ) {
					return $field;
				}

				++$fieldCount;

				$stored = Obj::prop( 'wpml_cf_preferences', $field );

				if ( null === $stored || '' === $stored ) {
					$wpmlSettingsCount += (int) self::isSetOnWpmlSettings( $field, $fieldPattern, $preferencesSetOnWpmlSettings );

					return $field;
				}

				++$declaredCount;

				if ( $getPreset && (int) $stored !== (int) $getPreset( $field ) ) {
					++$manualCount;
				}

				return $field;
			},
			function ( $layout ) {
				return $layout;
			}
		);

		return [
			'fieldCount'        => $fieldCount,
			'manualCount'       => $manualCount,
			'declaredCount'     => $declaredCount,
			'wpmlSettingsCount' => $wpmlSettingsCount,
		];
	}

	private static function isSetOnWpmlSettings( array $field, $fieldPattern, array $preferencesSetOnWpmlSettings ) {
		$fieldName = (string) Obj::propOr( '', 'name', $field );

		if ( preg_quote( $fieldName ) === $fieldPattern ) {
			return array_key_exists( $fieldName, $preferencesSetOnWpmlSettings );
		}

		foreach ( array_keys( $preferencesSetOnWpmlSettings ) as $metaKey ) {
			if ( preg_match( '/^' . $fieldPattern . '$/', (string) $metaKey ) ) {
				return true;
			}
		}

		return false;
	}

	private static function getPresetReader( $mode ) {
		if ( Mode::TRANSLATION !== $mode && Mode::LOCALIZATION !== $mode ) {
			return null;
		}

		return ModeDefaults::get( $mode );
	}

	private static function getSource( array $group, $isLocal, $jsonNewer ) {
		if ( $isLocal ) {
			return self::SOURCE_JSON === Obj::prop( 'local', $group )
				? self::SOURCE_JSON
				: self::SOURCE_PHP;
		}

		return $jsonNewer ? self::SOURCE_JSON_NEWER : null;
	}

	private static function needsCodePreferences( $isLocal, array $group, array $stats ) {
		return $isLocal
			&& $stats['fieldCount'] > 0
			&& 0 === $stats['declaredCount']
			&& 0 === $stats['wpmlSettingsCount']
			&& Mode::isAdvanced( $group );
	}
}
