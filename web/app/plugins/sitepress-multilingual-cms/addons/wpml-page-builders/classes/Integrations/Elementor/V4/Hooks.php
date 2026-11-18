<?php

namespace WPML\PB\Elementor\V4;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks as WPHooks;
use WPML\PB\Elementor\Helper\ElementTree;
use WPML\PB\Elementor\V4\Component\InstanceIds;

use function WPML\FP\spreadArgs;

class Hooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const V4_WIDGET_PREFIX      = 'e-';
	const TYPE_KEY              = '$$type';
	const DESTINATION_TYPE_PATH = [ 'value', 'destination', self::TYPE_KEY ];
	const LINK_QUERY_ID_PATH    = [ 'value', 'destination', 'value', 'id', 'value' ];
	const COMPONENT_ID_PATH     = [ 'component_instance', 'value', 'component_id', 'value' ];
	const DYNAMIC_TAG_PATH      = [ 'value', 'destination', 'value' ];

	const DYNAMIC_TAG_POST_ID_SETTINGS = [
		'popup'        => [ 'popup' ],
		'internal-url' => [ 'post_id' ],
	];

	private $instanceIds;

	public function __construct( InstanceIds $instanceIds ) {
		$this->instanceIds = $instanceIds;
	}

	public function add_hooks() {
		WPHooks::onFilter( 'elementor/frontend/builder_content_data' )
			->then( spreadArgs( [ $this, 'translateContentIds' ] ) );
	}

	public function translateContentIds( array $data ) {
		return ElementTree::map( $data, [ $this, 'translateElementIds' ] );
	}

	public function translateElementIds( array $element ) {
		if ( $this->isV4Widget( $element ) || $this->isV4Container( $element ) ) {
			$element['settings'] = $this->translateSettingsIds( $element['settings'] );
		}

		return $element;
	}

	private function isV4Widget( array $element ) {
		$widgetType = $element['widgetType'] ?? null;

		return $widgetType
			&& strpos( $widgetType, self::V4_WIDGET_PREFIX ) === 0
			&& isset( $element['settings'] )
			&& is_array( $element['settings'] );
	}

	private function isV4Container( array $element ) {
		$elType = $element['elType'] ?? null;

		return is_string( $elType )
			&& 'widget' !== $elType
			&& strpos( $elType, self::V4_WIDGET_PREFIX ) === 0
			&& isset( $element['settings'] )
			&& is_array( $element['settings'] );
	}

	private function translateSettingsIds( array $settings ) {
		foreach ( $settings as $key => $value ) {
			if ( $this->isLinkWithQueryDestination( $value ) ) {
				$settings[ $key ] = $this->instanceIds->translateIdInPath( $value, self::LINK_QUERY_ID_PATH );
			} elseif ( $this->isLinkWithDynamicDestination( $value ) ) {
				$settings[ $key ] = $this->translateDynamicTagIds( $value );
			}
		}

		$settings = $this->instanceIds->translateIdInPath( $settings, self::COMPONENT_ID_PATH );

		return $settings;
	}

	private function isLinkWithQueryDestination( $value ) {
		return is_array( $value )
			&& 'link' === Obj::prop( self::TYPE_KEY, $value )
			&& 'query' === Obj::path( self::DESTINATION_TYPE_PATH, $value )
			&& null !== Obj::path( self::LINK_QUERY_ID_PATH, $value );
	}

	private function isLinkWithDynamicDestination( $value ) {
		return is_array( $value )
			&& 'link' === Obj::prop( self::TYPE_KEY, $value )
			&& 'dynamic' === Obj::path( self::DESTINATION_TYPE_PATH, $value )
			&& is_string( Obj::path( array_merge( self::DYNAMIC_TAG_PATH, [ 'name' ] ), $value ) );
	}

	private function translateDynamicTagIds( array $link ) {
		$tagName = Obj::path( array_merge( self::DYNAMIC_TAG_PATH, [ 'name' ] ), $link );

		foreach ( self::DYNAMIC_TAG_POST_ID_SETTINGS[ $tagName ] ?? [] as $setting ) {
			$path = array_merge( self::DYNAMIC_TAG_PATH, [ 'settings', $setting, 'value', 'id', 'value' ] );
			$link = $this->instanceIds->translateIdInPath( $link, $path );
		}

		return $link;
	}

}
