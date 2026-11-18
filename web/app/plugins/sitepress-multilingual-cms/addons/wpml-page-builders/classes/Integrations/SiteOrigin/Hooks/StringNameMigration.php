<?php

namespace WPML\PB\SiteOrigin\Hooks;

use WPML\FP\Obj;
use WPML\PB\SiteOrigin\BuilderFields;
use WPML\PB\SiteOrigin\DataSettings;
use WPML\PB\SiteOrigin\PositionStringNames;
use WPML\PB\SiteOrigin\TranslatableNodes;

class StringNameMigration implements \IWPML_Action {

	const PRIORITY_BEFORE_STRING_ROWS_READ = 9;

	private $wpdb;

	private $nodes;

	private $dataSettings;

	private $positionStringNames;

	private $registeredStringsCache;

	private $renamedNames = [];

	public function __construct(
		\wpdb $wpdb,
		TranslatableNodes $nodes,
		DataSettings $dataSettings,
		PositionStringNames $positionStringNames,
		\WPML_WP_Cache $registeredStringsCache
	) {
		$this->wpdb                   = $wpdb;
		$this->nodes                  = $nodes;
		$this->dataSettings           = $dataSettings;
		$this->positionStringNames    = $positionStringNames;
		$this->registeredStringsCache = $registeredStringsCache;
	}

	public function add_hooks() {
		add_action( 'wpml_pb_before_registering_strings', [ $this, 'renamePositionNamedStrings' ], self::PRIORITY_BEFORE_STRING_ROWS_READ, 2 );
	}

	public function renamePositionNamedStrings( $post, $package ) {
		if ( $this->dataSettings->get_pb_name() !== Obj::prop( 'kind', $package ) ) {
			return;
		}

		$layout = $this->getLayout( (int) $post->ID );
		if ( ! $layout ) {
			return;
		}

		$stringPackage = apply_filters( 'wpml_st_get_string_package', null, $package );
		if ( ! $stringPackage instanceof \WPML_Package ) {
			return;
		}

		$registeredStrings = (array) $stringPackage->get_package_strings();
		$registeredNames   = array_column( $registeredStrings, 'name' );

		if ( self::everyNameHoldsAWidgetId( $registeredNames, self::getWidgetIds( $layout ) ) ) {
			return;
		}

		$currentStrings = [];
		$this->collectStrings( $layout, $currentStrings );
		$currentStrings = $this->sanitizeNames( $currentStrings, $stringPackage, 'name' );

		$stringsToRename = array_filter(
			$currentStrings,
			function ( array $currentString ) use ( $registeredNames ) {
				return ! in_array( $currentString['name'], $registeredNames, true );
			}
		);

		if ( ! $stringsToRename ) {
			return;
		}

		$this->renamedNames = [];

		$currentNames    = array_column( $currentStrings, 'name' );
		$leftoverStrings = array_filter(
			$registeredStrings,
			function ( $registeredString ) use ( $currentNames ) {
				return ! in_array( $registeredString->name, $currentNames, true );
			}
		);

		$stringsToRename = $this->sanitizeNames( array_map( [ $this, 'addPositionName' ], $stringsToRename ), $stringPackage, 'positionName' );
		$stringsToRename = array_map( [ $this, 'addPositionNamePattern' ], $stringsToRename );
		$context         = $stringPackage->get_string_context_from_package();

		$isRenamed = $this->renameMatches( $stringsToRename, $leftoverStrings, $context, true );
		$isRenamed = $this->renameMatches( $stringsToRename, $leftoverStrings, $context, false ) || $isRenamed;

		if ( $isRenamed ) {
			$stringPackage->flush_cache();
			do_action( 'wpml_st_string_updated' );
		}
	}

	private function renameMatches( array &$stringsToRename, array &$leftoverStrings, string $context, bool $isSamePositionOnly ): bool {
		$isRenamed = false;

		foreach ( $stringsToRename as $stringKey => $stringToRename ) {
			if ( in_array( $stringToRename['name'], $this->renamedNames, true ) ) {
				continue;
			}

			foreach ( $leftoverStrings as $leftoverKey => $leftoverString ) {
				$isSameText     = $leftoverString->value === $stringToRename['value'];
				$isSamePosition = $leftoverString->name === $stringToRename['positionName'];
				$isAnyPosition  = $stringToRename['positionNamePattern'] && preg_match( $stringToRename['positionNamePattern'], $leftoverString->name );

				if ( $isSameText && ( $isSamePosition || ( ! $isSamePositionOnly && $isAnyPosition ) ) ) {
					$this->rename( $leftoverString, $stringToRename['name'], $context );
					$this->renamedNames[] = $stringToRename['name'];
					unset( $stringsToRename[ $stringKey ], $leftoverStrings[ $leftoverKey ] );
					$isRenamed = true;
					break;
				}
			}
		}

		return $isRenamed;
	}

	private function getLayout( int $postId ): array {
		$data = get_post_meta( $postId, $this->dataSettings->get_meta_field(), false );

		return $data ? (array) $this->dataSettings->convert_data_to_array( $data ) : [];
	}

	private static function getWidgetIds( array $layout ): array {
		$widgetIds = [];

		array_walk_recursive(
			$layout,
			function ( $value, $key ) use ( &$widgetIds ) {
				if ( TranslatableNodes::WIDGET_ID_FIELD === $key && is_string( $value ) && '' !== $value ) {
					$widgetIds[] = $value;
				}
			}
		);

		return $widgetIds;
	}

	private static function everyNameHoldsAWidgetId( array $names, array $widgetIds ): bool {
		foreach ( $names as $name ) {
			$isHoldingAWidgetId = false;

			foreach ( $widgetIds as $widgetId ) {
				if ( false !== strpos( $name, '-' . $widgetId . '-' ) ) {
					$isHoldingAWidgetId = true;
					break;
				}
			}

			if ( ! $isHoldingAWidgetId ) {
				return false;
			}
		}

		return true;
	}

	private function collectStrings( array $dataArray, array &$strings ) {
		foreach ( $dataArray as $data ) {
			if ( isset( $data[ TranslatableNodes::SETTINGS_FIELD ] ) ) {
				if ( TranslatableNodes::isWrappingModule( $data ) ) {
					$this->collectStrings( $data[ TranslatableNodes::CHILDREN_FIELD ], $strings );
				} else {
					$this->collectWidgetStrings( $data, $strings );

					foreach ( BuilderFields::getWidgetLists( $data ) as $widgets ) {
						$this->collectStrings( $widgets, $strings );
					}
				}
			} elseif ( is_array( $data ) ) {
				$this->collectStrings( $data, $strings );
			}
		}
	}

	private function collectWidgetStrings( array $widget, array &$strings ) {
		$nodeId = $widget[ TranslatableNodes::SETTINGS_FIELD ]['class'];

		foreach ( $this->nodes->get( $nodeId, $widget ) as $index => $pbString ) {
			$strings[] = [
				'name'   => $pbString->get_name(),
				'value'  => $pbString->get_value(),
				'nodeId' => $nodeId,
				'widget' => $widget,
				'index'  => $index,
			];
		}
	}

	private function addPositionName( array $currentString ): array {
		$positionNames = $this->positionStringNames->get( $currentString['nodeId'], $currentString['widget'] );

		$currentString['positionName'] = $positionNames[ $currentString['index'] ] ?? '';

		return $currentString;
	}

	private function addPositionNamePattern( array $stringToRename ): array {
		$position         = Obj::path( [ TranslatableNodes::SETTINGS_FIELD, TranslatableNodes::POSITION_FIELD ], $stringToRename['widget'] );
		$positionedPrefix = $stringToRename['nodeId'] . '-' . $position . '-';

		$stringToRename['positionNamePattern'] = 0 === strpos( $stringToRename['positionName'], $positionedPrefix )
			? '/^' . preg_quote( $stringToRename['nodeId'] . '-', '/' ) . '\d+' . preg_quote( substr( $stringToRename['positionName'], strlen( $positionedPrefix ) - 1 ), '/' ) . '$/'
			: null;

		return $stringToRename;
	}

	private function rename( \stdClass $registeredString, string $name, string $context ) {
		$this->wpdb->update(
			$this->wpdb->prefix . 'icl_strings',
			[
				'name'                    => $name,
				'domain_name_context_md5' => md5( $context . $name ),
			],
			[ 'id' => (int) $registeredString->id ]
		);

		$this->registeredStringsCache->delete( md5( $context . $registeredString->name ) );
	}

	private function sanitizeNames( array $strings, \WPML_Package $stringPackage, string $key ): array {
		return array_map(
			function ( array $currentString ) use ( $stringPackage, $key ) {
				$currentString[ $key ] = $stringPackage->sanitize_string_name( $currentString[ $key ] );

				return $currentString;
			},
			$strings
		);
	}
}
