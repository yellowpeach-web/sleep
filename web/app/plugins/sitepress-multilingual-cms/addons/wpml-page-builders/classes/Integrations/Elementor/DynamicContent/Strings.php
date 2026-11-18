<?php

namespace WPML\PB\Elementor\DynamicContent;

use WPML\Collect\Support\Collection;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML_Elementor_Translatable_Nodes;
use WPML_PB_String;

class Strings {

	const KEY_SETTINGS = WPML_Elementor_Translatable_Nodes::SETTINGS_FIELD;
	const KEY_NODE_ID  = 'id';
	const KEY_ITEM_ID  = '_id';

	const KEY_DYNAMIC_V3 = '__dynamic__';
	const KEY_DYNAMIC_V4 = '$$type';

	const SETTINGS_REGEX        = '/settings="(.*?(?="]))/';
	const NAME_PREFIX           = 'dynamic';
	const DELIMITER             = '-';
	const TRANSLATABLE_SETTINGS = [
		'before',
		'after',
		'fallback',
		'video_url',
		'shortcode',
	];

	private static $tagInfoResolver;

	private static $registeredTags = [];

	public static function filter( array $strings, $nodeId, array $element ) {

		$dynamicFields = self::getDynamicFields( $element );

		$updateFromDynamicFields = function ( WPML_PB_String $pbString ) use ( &$dynamicFields ) {
			$matchingField = $dynamicFields->first(
				function ( Field $field ) use ( $pbString ) {
					return $field->isMatchingStaticString( $pbString );
				}
			);

			if ( $matchingField ) {
				return self::addBeforeAfterAndFallback( wpml_collect( [ $dynamicFields->pull( $dynamicFields->search( $matchingField ) ) ] ), $pbString->get_title() );
			}

			return $pbString;
		};

		$stringsForNonTranslatableFields = function ( $dynamicFields ) {
			return self::addBeforeAfterAndFallback( $dynamicFields );
		};

		return wpml_collect( $strings )
			->map( $updateFromDynamicFields )
			->merge( $stringsForNonTranslatableFields( $dynamicFields ) )
			->flatten()
			->toArray();
	}

	private static function getDynamicFields( array $element ) {
		if ( self::isModuleWithItems( $element ) ) {
			return self::getDynamicFieldsForModuleWithItems( $element );
		} elseif ( isset( $element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ] ) ) {
			return self::getFields(
				$element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ],
				$element[ self::KEY_NODE_ID ]
			);
		} elseif ( self::hasV4DynamicFields( $element ) ) {
			return self::getV4DynamicFields( $element );
		}

		return wpml_collect();
	}

	private static function getDynamicFieldsForModuleWithItems( array $element ) {
		$isDynamic = function ( $item ) {
			return isset( $item[ self::KEY_DYNAMIC_V3 ] );
		};

		$getFields = function ( array $item ) use ( $element ) {
			return self::getFields(
				$item[ self::KEY_DYNAMIC_V3 ],
				$element[ self::KEY_NODE_ID ],
				$item[ self::KEY_ITEM_ID ]
			);
		};

		$collection = wpml_collect( self::getFieldsFromModuleWithItems( $element[ self::KEY_SETTINGS ] ) )
			->filter( $isDynamic )
			->map( $getFields );

		if ( isset( $element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ] ) ) {
			$collection = $collection->merge( self::getFields( $element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ], $element[ self::KEY_NODE_ID ] ) );
		}

		return $collection->flatten();
	}

	public static function getFieldsFromModuleWithItems( $module ) {
		$hasFields = function ( $item ) {
			return is_array( $item );
		};

		return wpml_collect( $module )
			->first( $hasFields );
	}

	public static function getKeyFromModuleWithItems( $module ) {
		$hasFields = function ( $item ) {
			return is_array( $item );
		};

		return wpml_collect( $module )
			->filter( $hasFields )
			->keys()
			->first();
	}

	public static function setTagInfoResolver( $resolver = null ) {
		self::$tagInfoResolver = is_callable( $resolver ) ? $resolver : null;
		self::$registeredTags  = [];
	}

	private static function getFields( $data, $nodeId, $itemId = '' ) {
		$isDynamicTag = function ( $tagValue ) {
			return is_string( $tagValue )
				&& false !== strpos( $tagValue, '[elementor-tag' )
				&& self::isRegisteredTag( $tagValue );
		};

		$buildField = function ( $tagValue, $tagKey ) use ( $nodeId, $itemId ) {
			return new Field( $tagValue, $tagKey, $nodeId, $itemId );
		};

		return wpml_collect( is_array( $data ) ? $data : [] )
			->filter( $isDynamicTag )
			->map( $buildField );
	}

	private static function isRegisteredTag( $tagValue ) {
		$name = preg_match( '/name="(.*?(?="))"/', $tagValue, $matches ) ? $matches[1] : '';

		if ( array_key_exists( $name, self::$registeredTags ) ) {
			return self::$registeredTags[ $name ];
		}

		$resolver = self::$tagInfoResolver ?: self::getElementorTagInfoResolver();
		if ( ! $resolver ) {
			return true;
		}

		try {
			$isRegistered = ! empty( call_user_func( $resolver, $name ) );
		} catch ( \Throwable $e ) {
			return true;
		}

		self::$registeredTags[ $name ] = $isRegistered;

		return $isRegistered;
	}

	private static function getElementorTagInfoResolver() {
		if ( ! class_exists( '\Elementor\Plugin', false ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return null;
		}

		$dynamicTags = isset( \Elementor\Plugin::$instance->dynamic_tags ) ? \Elementor\Plugin::$instance->dynamic_tags : null;

		return is_object( $dynamicTags ) && method_exists( $dynamicTags, 'get_tag_info' )
			? [ $dynamicTags, 'get_tag_info' ]
			: null;
	}

	private static function isModuleWithItems( array $element ) {
		if ( isset( $element[ self::KEY_SETTINGS ] ) ) {
			$firstSettingElement = self::getFieldsFromModuleWithItems( $element[ self::KEY_SETTINGS ] );
			return is_array( $firstSettingElement ) && 0 === key( $firstSettingElement );
		}

		return false;
	}

	private static function addBeforeAfterAndFallback( Collection $dynamicFields, $stringTitle = null ) {
		$dynamicFieldToSettingStrings = function ( Field $field ) use ( $stringTitle ) {
			preg_match( self::SETTINGS_REGEX, $field->tagValue, $matches );

			$isTranslatableSetting = function ( $value, $settingField ) {
				return $value && is_string( $value ) && in_array( $settingField, self::TRANSLATABLE_SETTINGS, true );
			};

			$buildStringFromSetting = function ( $value, $settingField ) use ( $field, $stringTitle ) {
				$stringTitle = ( null === $stringTitle ) ? $field->tagKey : $stringTitle;

				return new WPML_PB_String(
					$value,
					self::getStringName( $field->nodeId, $field->itemId, $field->tagKey, $settingField ),
					$stringTitle . ' (' . $settingField . ')',
					'LINE'
				);
			};

			return wpml_collect( isset( $matches[1] ) ? self::decodeSettings( $matches[1] ) : [] )
				->filter( $isTranslatableSetting )
				->map( $buildStringFromSetting );
		};

		return $dynamicFields->map( $dynamicFieldToSettingStrings );
	}

	public static function updateNode( array $element, WPML_PB_String $pbString ) {
		$stringNameParts = explode( self::DELIMITER, $pbString->get_name() );

		if ( count( $stringNameParts ) !== 5 || self::NAME_PREFIX !== $stringNameParts[0] ) {
			return $element;
		}

		list( , , $itemId, $dynamicField, $settingField ) = $stringNameParts;

		if ( $itemId && self::isModuleWithItems( $element ) ) {
			$element = self::updateNodeWithItems( $element, $pbString, $stringNameParts );
		} elseif ( isset( $element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ] ) ) {
			$element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ] = self::replaceSettingString(
				$element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ],
				$pbString,
				$settingField
			);
		} elseif ( self::isV4DynamicField( $element, $dynamicField ) ) {
			$element = self::updateV4DynamicField( $element, $dynamicField, $settingField, $pbString );
		}

		return $element;
	}

	private static function replaceSettingString( $encodedSettings, WPML_PB_String $pbString, $settingField ) {
		$replace = function ( array $matches ) use ( $pbString, $settingField ) {
			$settings                  = self::decodeSettings( $matches[1] );
			$settings[ $settingField ] = $pbString->get_value();
			$replace                   = self::encodeSettings( $settings );

			return str_replace( $matches[1], $replace, $matches[0] );
		};

		return preg_replace_callback( self::SETTINGS_REGEX, $replace, $encodedSettings );
	}

	private static function updateNodeWithItems( array $element, WPML_PB_String $pbString, array $stringNameParts ) {
		list( , , $itemId, $dynamicField, $settingField ) = $stringNameParts;

		$items   = wpml_collect( self::getFieldsFromModuleWithItems( $element[ self::KEY_SETTINGS ] ) );
		$mainKey = self::getKeyFromModuleWithItems( $element[ self::KEY_SETTINGS ] );

		$replaceStringInItem = function ( array $item ) use ( $itemId, $pbString, $dynamicField, $settingField ) {
			if (
				isset( $item[ self::KEY_DYNAMIC_V3 ][ $dynamicField ], $item[ self::KEY_ITEM_ID ] )
				&& $item[ self::KEY_ITEM_ID ] === $itemId
			) {
				$item[ self::KEY_DYNAMIC_V3 ][ $dynamicField ] = self::replaceSettingString( $item[ self::KEY_DYNAMIC_V3 ][ $dynamicField ], $pbString, $settingField );
			}

			return $item;
		};

		$element[ self::KEY_SETTINGS ][ $mainKey ] = $items->map( $replaceStringInItem )->toArray();
		if ( isset( $element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ] ) ) {
			$element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ] = self::replaceSettingString(
				$element[ self::KEY_SETTINGS ][ self::KEY_DYNAMIC_V3 ][ $dynamicField ],
				$pbString,
				$settingField
			);
		}

		return $element;
	}

	private static function decodeSettings( $settingsString ) {
		return json_decode( urldecode( $settingsString ), true );
	}

	private static function encodeSettings( array $settings ) {
		return urlencode( json_encode( $settings ) );
	}

	public static function getStringName( $nodeId, $itemId, $tagKey, $settingField ) {
		return self::NAME_PREFIX . self::DELIMITER
			. $nodeId . self::DELIMITER
			. $itemId . self::DELIMITER
			. $tagKey . self::DELIMITER
			. $settingField;
	}

	private static function hasV4DynamicFields( array $element ) {
		if ( ! isset( $element[ self::KEY_SETTINGS ] ) ) {
			return false;
		}

		$isDynamicField = function ( $value ) {
			return is_array( $value ) && Relation::propEq( self::KEY_DYNAMIC_V4, 'dynamic', $value );
		};

		return wpml_collect( $element[ self::KEY_SETTINGS ] )->contains( $isDynamicField );
	}

	private static function getV4DynamicFields( array $element ) {
		$toField = function ( $value, $tagKey ) use ( $element ) {
			$tagValue = self::convertV4DynamicToV3Format( $value );

			return new Field( $tagValue, $tagKey, $element[ self::KEY_NODE_ID ] );
		};

		return wpml_collect( $element[ self::KEY_SETTINGS ] )
			->filter( Relation::propEq( self::KEY_DYNAMIC_V4, 'dynamic' ) )
			->map( $toField );
	}

	private static function convertV4DynamicToV3Format( array $v4Dynamic ) {
		$value    = Obj::propOr( [], 'value', $v4Dynamic );
		$name     = Obj::propOr( '', 'name', $value );
		$settings = Obj::propOr( [], 'settings', $value );

		$isTypedSetting = function ( $setting ) {
			return is_array( $setting ) && isset( $setting[ self::KEY_DYNAMIC_V4 ], $setting['value'] );
		};

		$convertedSettings = wpml_collect( $settings )
			->filter( $isTypedSetting )
			->map( Obj::prop( 'value' ) )
			->toArray();

		$encodedSettings = self::encodeSettings( $convertedSettings );

		return sprintf( '[elementor-tag id="%s" name="%s" settings="%s"]', uniqid(), $name, $encodedSettings );
	}

	private static function isV4DynamicField( array $element, $dynamicField ) {
		return isset( $element[ self::KEY_SETTINGS ][ $dynamicField ] )
			&& Relation::propEq( self::KEY_DYNAMIC_V4, 'dynamic', $element[ self::KEY_SETTINGS ][ $dynamicField ] );
	}

	private static function updateV4DynamicField( array $element, $dynamicField, $settingField, WPML_PB_String $pbString ) {
		$path = [ self::KEY_SETTINGS, $dynamicField, 'value', 'settings', $settingField ];

		if ( ! Obj::hasPath( $path, $element ) ) {
			return $element;
		}

		return Obj::assocPath(
			$path,
			[
				self::KEY_DYNAMIC_V4 => 'string',
				'value'              => $pbString->get_value(),
			],
			$element
		);
	}
}
