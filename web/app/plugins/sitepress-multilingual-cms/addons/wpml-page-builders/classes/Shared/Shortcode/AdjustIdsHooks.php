<?php

namespace WPML\PB\Shortcode;

use WPML\Convert\Ids;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use WPML_PB_Config_Import_Shortcode;
use function WPML\FP\spreadArgs;

class AdjustIdsHooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $config;

	public function __construct( WPML_PB_Config_Import_Shortcode $config ) {
		$this->config = $config;
	}

	public function add_hooks() {
		Hooks::onFilter( 'pre_do_shortcode_tag', - PHP_INT_MAX, 4 )
			->then( spreadArgs( Fns::withoutRecursion( Fns::identity(), [ $this, 'convertAttributeIds' ] ) ) );
	}

	public function convertAttributeIds( $bool, $tag, $attr, $m ) {
		$tagConfig = $this->getConfig( $tag );

		if ( $tagConfig ) {
			foreach ( $tagConfig as $attribute => $setting ) {
				$m = $this->convertAttribute( $attribute, $setting, $m );
			}

			return do_shortcode_tag( $m );
		}

		return $bool;
	}

	private function convertAttribute( string $attribute, $setting, array $m ): array {
		if ( is_array( $setting ) ) {
			$type    = $setting[ WPML_PB_Config_Import_Shortcode::WILDCARD_TYPE ];
			$exclude = $setting[ WPML_PB_Config_Import_Shortcode::WILDCARD_EXCLUDE ];

			$converted = preg_replace_callback(
				$setting[ WPML_PB_Config_Import_Shortcode::WILDCARD_REGEX ],
				function( $arr ) use ( $type, $exclude ) {
					return in_array( $arr[ WPML_PB_Config_Import_Shortcode::GROUP_NAME ], $exclude, true )
						? $arr[0]
						: $arr[ WPML_PB_Config_Import_Shortcode::GROUP_ATTRIBUTE ] . Ids::convert( $arr[ WPML_PB_Config_Import_Shortcode::GROUP_IDS ], $type, true );
				},
				$m
			);
		} else {
			$converted = preg_replace_callback(
				'/' . WPML_PB_Config_Import_Shortcode::NAME_BOUNDARY . '(' . preg_quote( $attribute, '/' ) . '=[\"\']{1})([^\"\']+)/',
				function( $arr ) use ( $setting ) {
					return $arr[1] . Ids::convert( $arr[2], $setting, true );
				},
				$m
			);
		}

		return is_array( $converted ) && array_keys( $converted ) === array_keys( $m ) ? $converted : $m;
	}

	private function getConfig( $tag ) {
		return Obj::prop( $tag, $this->config->get_id_settings() );
	}
}
