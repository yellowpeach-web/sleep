<?php

namespace WPML\PB\Cornerstone\Config;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class DeclaredEntries implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const PRIORITY = \WPML\PB\Config\Hooks::PRIORITY_EXTRACT_CONFIG - 5;

	const POST_TYPES = [
		'cs_header',
		'cs_footer',
		'cs_layout_archive',
		'cs_layout_single',
		'cs_layout_archive_wc',
		'cs_layout_single_wc',
		'cs_global_block',
	];

	const BUILT_WITH_PATTERN = '/\[cs_content[\s\]]/';

	const WIDGET_FIELDS = [
		'headline'          => [
			[ 'text_content', 'Headline: Headline', 'VISUAL' ],
			[ 'text_subheadline_content', 'Headline: Subheadline', 'VISUAL' ],
			[ 'text_typing_prefix', 'Headline: Typing Prefix', 'LINE' ],
			[ 'text_typing_content', 'Headline: Typing Content', 'LINE' ],
			[ 'text_typing_suffix', 'Headline: Typing Suffix', 'LINE' ],
			[ 'text_href', 'Headline: Link URL', 'LINK' ],
		],
		'testimonial'       => [
			[ 'testimonial_content', 'Testimonial: Content', 'VISUAL' ],
			[ 'testimonial_cite_content', 'Testimonial: Author', 'VISUAL' ],
			[ 'testimonial_graphic_image_alt', 'Testimonial: Graphic Image Alternative Text', 'LINE' ],
			[ 'testimonial_rating_text_content', 'Testimonial: Rating Text', 'LINE' ],
			[ 'testimonial_rating_schema_author_content', 'Testimonial: Rating Schema Author', 'LINE' ],
			[ 'testimonial_rating_schema_review_body_content', 'Testimonial: Rating Schema Review Body', 'AREA' ],
		],
		'image'             => [
			[ 'image_alt', 'Image: Alternative Text', 'LINE' ],
			[ 'image_href', 'Image: Link URL', 'LINK' ],
		],
		'raw-content'       => [
			[ 'raw_content', 'Raw content', 'VISUAL' ],
		],
		'layout-table-cell' => [
			[ 'text_content', 'Table Cell: Text Content', 'VISUAL' ],
		],
	];

	const ADMIN_TEXTS = [
		'x_logo_text',
		'x_integrity_shop_title',
		'x_integrity_shop_subtitle',
		'x_renew_shop_title',
		'x_icon_shop_title',
		'x_ethos_shop_title',
	];

	const THEMES = [ 'x', 'pro' ];

	const ADMIN_TEXT_TYPE    = 'theme';
	const ADMIN_TEXT_CONTEXT = 'wpml-cornerstone';

	public function add_hooks() {
		Hooks::onFilter( 'wpml_config_array', self::PRIORITY )
			->then( spreadArgs( [ self::class, 'addEntries' ] ) );

		Hooks::onFilter( 'wpml_config_sources' )
			->then( spreadArgs( [ self::class, 'addAdminTextSource' ] ) );
	}

	public static function addEntries( $config ) {
		if ( ! isset( $config['wpml-config'] ) || ! is_array( $config['wpml-config'] ) ) {
			return $config;
		}

		$config['wpml-config'] = self::addPostTypes( $config['wpml-config'] );
		$config['wpml-config'] = self::addBuiltWithPattern( $config['wpml-config'] );
		$config['wpml-config'] = self::addWidgetFields( $config['wpml-config'] );

		return $config;
	}

	public static function addAdminTextSource( $sources ) {
		if ( ! is_array( $sources ) || ! in_array( get_template(), self::THEMES, true ) ) {
			return $sources;
		}

		$keys = array_values( array_diff( self::ADMIN_TEXTS, self::getDeclaredAdminTexts( $sources ) ) );

		if ( $keys ) {
			$sources[] = (object) [
				'type'               => self::ADMIN_TEXT_TYPE,
				'admin_text_context' => self::ADMIN_TEXT_CONTEXT,
				'config'             => [
					'wpml-config' => [
						'admin-texts' => [
							'key' => array_map(
								function ( $name ) {
									return [ 'attr' => [ 'name' => $name ] ];
								},
								$keys
							),
						],
					],
				],
			];
		}

		return $sources;
	}

	private static function addPostTypes( array $config ) {
		$types    = self::getList( $config, 'custom-types', 'custom-type' );
		$declared = array_map(
			function ( $type ) {
				return $type['value'] ?? null;
			},
			$types
		);

		foreach ( array_diff( self::POST_TYPES, $declared ) as $postType ) {
			$types[] = [
				'value' => $postType,
				'attr'  => [
					'translate'             => '1',
					'display-as-translated' => '1',
				],
			];
		}

		$config['custom-types']['custom-type'] = $types;

		return $config;
	}

	private static function addBuiltWithPattern( array $config ) {
		$patterns = (array) ( $config[ \WPML_Config_Built_With_Page_Builders::CONFIG_KEY ] ?? [] );

		if ( ! in_array( self::BUILT_WITH_PATTERN, $patterns, true ) ) {
			$patterns[] = self::BUILT_WITH_PATTERN;
		}

		$config[ \WPML_Config_Built_With_Page_Builders::CONFIG_KEY ] = $patterns;

		return $config;
	}

	private static function addWidgetFields( array $config ) {
		$widgets = self::getList( $config, 'cornerstone-widgets', 'widget' );

		foreach ( self::WIDGET_FIELDS as $name => $fields ) {
			$index = self::findLastWidget( $widgets, $name );

			if ( null === $index ) {
				$widgets[] = [
					'attr'   => [ 'name' => $name ],
					'fields' => [ 'field' => [] ],
				];
				$index     = count( $widgets ) - 1;
			}

			$widgets[ $index ] = self::addFieldsToWidget( $widgets[ $index ], $fields );
		}

		$config['cornerstone-widgets']['widget'] = $widgets;

		return $config;
	}

	private static function addFieldsToWidget( array $widget, array $fields ) {
		$declared = self::normalize( $widget['fields']['field'] ?? [] );

		foreach ( $fields as list( $field, $type, $editorType ) ) {
			$index = self::findField( $declared, $field );

			if ( null === $index ) {
				$declared[] = [
					'value' => $field,
					'attr'  => [
						'type'        => $type,
						'editor_type' => $editorType,
					],
				];
			} elseif ( empty( $declared[ $index ]['attr']['editor_type'] ) ) {
				$declared[ $index ]['attr']['editor_type'] = $editorType;
			}
		}

		$widget['fields']['field'] = $declared;

		return $widget;
	}

	private static function findLastWidget( array $widgets, string $name ) {
		$found = null;

		foreach ( $widgets as $index => $widget ) {
			if ( $name === ( $widget['attr']['name'] ?? null ) ) {
				$found = $index;
			}
		}

		return $found;
	}

	private static function findField( array $fields, string $name ) {
		foreach ( $fields as $index => $field ) {
			if ( $name === ( $field['value'] ?? null ) && empty( $field['attr']['key_of'] ) ) {
				return $index;
			}
		}

		return null;
	}

	private static function getList( array $config, string $plural, string $singular ) {
		return self::normalize( $config[ $plural ][ $singular ] ?? [] );
	}

	private static function normalize( $items ) {
		if ( ! is_array( $items ) || ! $items ) {
			return [];
		}

		return isset( $items['value'] ) || isset( $items['attr'] ) ? [ $items ] : array_values( $items );
	}

	private static function getDeclaredAdminTexts( array $sources ) {
		$names = [];

		foreach ( $sources as $source ) {
			if ( is_object( $source ) ) {
				$config = $source->config ?? [];
			} elseif ( self::isThemeConfigFile( $source ) ) {
				$config = icl_xml2array( (string) file_get_contents( $source ) );
			} else {
				continue;
			}

			foreach ( self::normalize( $config['wpml-config']['admin-texts']['key'] ?? [] ) as $key ) {
				$names[] = $key['attr']['name'] ?? null;
			}
		}

		return array_filter( $names );
	}

	private static function isThemeConfigFile( $source ): bool {
		return is_string( $source )
			&& in_array( dirname( $source ), [ get_template_directory(), get_stylesheet_directory() ], true )
			&& is_readable( $source );
	}
}
