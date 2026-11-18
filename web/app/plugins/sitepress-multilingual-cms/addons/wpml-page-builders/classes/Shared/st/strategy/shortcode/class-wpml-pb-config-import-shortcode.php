<?php

use WPML\Convert\Ids;
use WPML\FP\Obj;
use WPML\PB\ConvertIds\Helper as ConvertIdsHelper;

class WPML_PB_Config_Import_Shortcode {

	const PB_SHORTCODE_SETTING       = 'pb_shortcode';
	const PB_MEDIA_SHORTCODE_SETTING = 'wpml_pb_media_shortcode';
	const PB_IDS_SHORTCODE_SETTING   = 'wpml_pb_ids_shortcode';
	const PB_LINKS_SHORTCODE_SETTING = 'wpml_pb_links_shortcode';

	const PB_STICKY_LINKS_SHORTCODE_SETTING = 'wpml_pb_sticky_links_shortcode';

	const HAS_SHORTCODE_SETTINGS_FLAG = 'wpml_pb_has_shortcode_settings';

	const TYPE_POST_IDS     = 'post-ids';
	const TYPE_TAXONOMY_IDS = 'taxonomy-ids';
	const TYPE_LINK         = 'link';

	const FLAG_CONVERT_TO_STICKY = 'convert_to_sticky';

	const WILDCARD_TYPE    = 'type';
	const WILDCARD_REGEX   = 'regex';
	const WILDCARD_EXCLUDE = 'exclude';

	const NAME_BOUNDARY = '(?<![\w-])';

	const GROUP_ATTRIBUTE = 'attribute';
	const GROUP_NAME      = 'name';
	const GROUP_IDS       = 'ids';

	private $st_settings;

	public function __construct( WPML_ST_Settings $st_settings ) {
		$this->st_settings = $st_settings;
	}

	public function add_hooks() {
		add_filter( 'wpml_config_array', array( $this, 'wpml_config_filter' ) );
	}

	public function wpml_config_filter( $config_data ) {
		$this->update_shortcodes_config( $config_data );
		$this->update_ids_shortcodes_config( $config_data );
		$this->update_links_shortcodes_config( $config_data );
		$this->update_sticky_links_shortcodes_config( $config_data );
		$this->update_media_shortcodes_config( $config_data );

		return $config_data;
	}

	private function update_shortcodes_config( $config_data ) {
		$old_shortcode_data = $this->get_settings();

		$shortcode_data = array();
		if ( isset ( $config_data['wpml-config']['shortcodes']['shortcode'] ) ) {
			foreach ( $config_data['wpml-config']['shortcodes']['shortcode'] as $data ) {
				$ignore_content = isset( $data['tag']['attr']['ignore-content'] )
				                  && $data['tag']['attr']['ignore-content'];

				$attributes = array();
				if ( isset( $data['attributes']['attribute'] ) ) {

					$data['attributes']['attribute'] = $this->convert_single_attribute_to_multiple_format( $data['attributes']['attribute'] );

					foreach ( $data['attributes']['attribute'] as $attribute ) {

						if ( ! $this->is_string_attribute( $attribute ) ) {
							continue;
						}

						if ( ! empty( $attribute['value'] ) ) {
							$attribute_encoding = isset( $attribute['attr']['encoding'] ) ? $attribute['attr']['encoding'] : '';
							$attribute_type     = isset( $attribute['attr']['type'] ) ? $attribute['attr']['type'] : '';
							$attribute_label    = isset( $attribute['attr']['label'] ) ? $attribute['attr']['label'] : '';
							$attributes[]       = [
								'value'    => $attribute['value'],
								'encoding' => $attribute_encoding,
								'type'     => $attribute_type,
								'label'    => $attribute_label,
							];
						}
					}
				}

				if ( ! ( $ignore_content && empty( $attributes ) ) ) {
					$shortcode_data[] = [
						'tag'        => [
							'value'              => $data['tag']['value'],
							'encoding'           => isset( $data['tag']['attr']['encoding'] ) ? $data['tag']['attr']['encoding'] : '',
							'encoding-condition' => isset( $data['tag']['attr']['encoding-condition'] ) ? $data['tag']['attr']['encoding-condition'] : '',
							'type'               => isset( $data['tag']['attr']['type'] ) ? $data['tag']['attr']['type'] : '',
							'raw-html'           => isset( $data['tag']['attr']['raw-html'] ) ? $data['tag']['attr']['raw-html'] : '',
							'ignore-content'     => $ignore_content,
							'label'              => isset( $data['tag']['attr']['label'] ) ? $data['tag']['attr']['label'] : '',
						],
						'attributes' => $attributes,
					];
				}
			}
		}

		if ( $shortcode_data != $old_shortcode_data ) {
			$this->st_settings->update_setting( self::PB_SHORTCODE_SETTING, $shortcode_data, true );
			update_option( self::HAS_SHORTCODE_SETTINGS_FLAG, empty( $shortcode_data ) ? 0 : 1, true );
		}
	}

	private function update_media_shortcodes_config( $config_data ) {
		$old_shortcodes_data = $this->get_media_settings();
		$shortcodes_data     = array();

		if ( isset ( $config_data['wpml-config']['shortcodes']['shortcode'] ) ) {

			foreach ( $config_data['wpml-config']['shortcodes']['shortcode'] as $data ) {
				$shortcode_data = array();

				if ( isset( $data['attributes']['attribute'] ) ) {
					$attributes = array();

					$data['attributes']['attribute'] = $this->convert_single_attribute_to_multiple_format( $data['attributes']['attribute'] );

					foreach ( $data['attributes']['attribute'] as $attribute ) {

						if ( ! $this->is_media_attribute( $attribute ) ) {
							continue;
						}

						if ( ! empty( $attribute['value'] ) ) {
							$attribute_type = isset( $attribute['attr']['type'] ) ? $attribute['attr']['type'] : '';
							$attributes[ $attribute['value'] ] = array( 'type' => $attribute_type );
						}
					}

					if ( $attributes ) {
						$shortcode_data['attributes'] = $attributes;
					}
				}

				if ( isset( $data['tag']['attr']['type'] )
				     && $data['tag']['attr']['type'] === WPML_Page_Builders_Media_Shortcodes::TYPE_URL
				) {
					$shortcode_data['content'] = array( 'type' => WPML_Page_Builders_Media_Shortcodes::TYPE_URL );
				}

				if ( $shortcode_data ) {
					$shortcode_data['tag'] = array( 'name' => $data['tag']['value'] );
					$shortcodes_data[]     = $shortcode_data;
				}
			}
		}

		if ( $shortcodes_data != $old_shortcodes_data ) {
			update_option( self::PB_MEDIA_SHORTCODE_SETTING, $shortcodes_data, true );
		}
	}

	private function update_ids_shortcodes_config( $config_data ) {
		$shortcodes_data = $this->get_attribute_settings(
			$config_data,
			function ( array $attribute ) {
				if ( ! $this->is_id_attribute( $attribute ) ) {
					return null;
				}

				return ConvertIdsHelper::selectElementType(
					Obj::path( [ 'attr', 'sub-type' ], $attribute ),
					Obj::path( [ 'attr', 'type' ], $attribute )
				);
			}
		);

		if ( $shortcodes_data != $this->get_id_settings() ) {
			update_option( self::PB_IDS_SHORTCODE_SETTING, $shortcodes_data, true );
		}
	}

	private function update_links_shortcodes_config( $config_data ) {
		$old_shortcodes_data = get_option( self::PB_LINKS_SHORTCODE_SETTING, null );
		$shortcodes_data     = $this->get_attribute_settings(
			$config_data,
			function ( array $attribute ) {
				return $this->is_link_attribute( $attribute ) ? self::TYPE_LINK : null;
			}
		);

		if ( null === $old_shortcodes_data || $shortcodes_data != $old_shortcodes_data ) {
			update_option( self::PB_LINKS_SHORTCODE_SETTING, $shortcodes_data, true );
		}
	}

	private function update_sticky_links_shortcodes_config( $config_data ) {
		$shortcodes_data = $this->get_attribute_settings(
			$config_data,
			function ( array $attribute ) {
				return $this->is_link_attribute( $attribute ) && $this->has_flag( $attribute, self::FLAG_CONVERT_TO_STICKY )
					? self::TYPE_LINK
					: null;
			}
		);

		if ( $shortcodes_data !== self::get_sticky_link_settings() ) {
			update_option( self::PB_STICKY_LINKS_SHORTCODE_SETTING, $shortcodes_data, true );
		}
	}

	private function get_attribute_settings( $config_data, callable $get_type ) {
		$shortcodes_data = [];

		if ( isset ( $config_data['wpml-config']['shortcodes']['shortcode'] ) ) {

			foreach ( $config_data['wpml-config']['shortcodes']['shortcode'] as $data ) {
				$attributes = [];

				if ( isset( $data['attributes']['attribute'] ) ) {
					$data['attributes']['attribute'] = $this->convert_single_attribute_to_multiple_format( $data['attributes']['attribute'] );

					foreach ( $data['attributes']['attribute'] as $attribute ) {
						$type = empty( $attribute['value'] ) ? null : $get_type( $attribute );

						if ( null !== $type ) {
							$attributes[ $attribute['value'] ] = $this->is_wildcard_name( $attribute['value'] )
								? $this->get_wildcard_setting( $attribute, $type )
								: $type;
						}
					}
				}

				if ( $attributes ) {
					$tag_name                     = $data['tag']['value'];
					$earlier                      = isset( $shortcodes_data[ $tag_name ] ) ? $shortcodes_data[ $tag_name ] : [];
					$shortcodes_data[ $tag_name ] = array_merge( $earlier, $attributes );
				}
			}
		}

		return $shortcodes_data;
	}

	private function is_string_attribute( array $attribute ) {
		return ! $this->is_id_attribute( $attribute )
		       && ! $this->is_media_attribute( $attribute );
	}

	private function is_id_attribute( array $attribute ) {
		return ConvertIdsHelper::isValidType( Obj::path( [ 'attr', 'type' ], $attribute ) );
	}

	private function is_link_attribute( array $attribute ) {
		if ( Obj::path( [ 'attr', 'encoding' ], $attribute ) ) {
			return false;
		}

		if ( self::TYPE_LINK === Obj::path( [ 'attr', 'type' ], $attribute ) ) {
			return true;
		}

		return $this->has_flag( $attribute, self::FLAG_CONVERT_TO_STICKY )
			&& ! $this->is_id_attribute( $attribute )
			&& ! $this->is_media_attribute( $attribute );
	}

	private function has_flag( array $attribute, $flag ) {
		return (bool) Obj::path( [ 'attr', $flag ], $attribute );
	}

	private function is_media_attribute( array $attribute ) {
		$media_attribute_types = array(
			WPML_Page_Builders_Media_Shortcodes::TYPE_URL,
			WPML_Page_Builders_Media_Shortcodes::TYPE_IDS,
		);

		return isset( $attribute['attr']['type'] )
		       && in_array( $attribute['attr']['type'], $media_attribute_types, true );
	}

	private function is_wildcard_name( string $name ): bool {
		return false !== strpos( $name, '*' );
	}

	private function get_wildcard_setting( array $attribute, string $type ): array {
		$name    = str_replace( '\\*', '[\w-]+', preg_quote( $attribute['value'], '/' ) );
		$exclude = explode( ',', (string) Obj::path( [ 'attr', 'exclude' ], $attribute ) );
		$regex   = '/' . self::NAME_BOUNDARY . '(?<' . self::GROUP_ATTRIBUTE . '>(?<' . self::GROUP_NAME . '>' . $name . ')\s*=\s*[\"\']{1})'
			. '(?<' . self::GROUP_IDS . '>[^\"\']+)/';

		return [
			self::WILDCARD_TYPE    => $type,
			self::WILDCARD_REGEX   => $regex,
			self::WILDCARD_EXCLUDE => array_values( array_filter( array_map( 'trim', $exclude ), 'strlen' ) ),
		];
	}

	public static function replace_attribute_values( $subject, $attribute, $setting, callable $convert ) {
		if ( is_array( $setting ) ) {
			$type    = $setting[ self::WILDCARD_TYPE ];
			$exclude = $setting[ self::WILDCARD_EXCLUDE ];

			$converted = preg_replace_callback(
				$setting[ self::WILDCARD_REGEX ],
				function ( $arr ) use ( $type, $exclude, $convert ) {
					return in_array( $arr[ self::GROUP_NAME ], $exclude, true )
						? $arr[0]
						: $arr[ self::GROUP_ATTRIBUTE ] . $convert( $arr[ self::GROUP_IDS ], $type );
				},
				$subject
			);
		} else {
			$converted = preg_replace_callback(
				'/' . self::NAME_BOUNDARY . '(' . preg_quote( $attribute, '/' ) . '\s*=\s*[\"\']{1})([^\"\']+)/',
				function ( $arr ) use ( $setting, $convert ) {
					return $arr[1] . $convert( $arr[2], $setting );
				},
				$subject
			);
		}

		if ( is_array( $subject ) ) {
			return is_array( $converted ) && array_keys( $converted ) === array_keys( $subject ) ? $converted : $subject;
		}

		return is_string( $converted ) ? $converted : $subject;
	}

	private function convert_single_attribute_to_multiple_format( array $attribute ) {
		if ( ! is_numeric( key( $attribute ) ) ) {
			$attribute = array( $attribute );
		}

		return $attribute;
	}

	public function get_settings() {
		return $this->st_settings->get_setting( self::PB_SHORTCODE_SETTING );
	}

	public function get_media_settings() {
		return get_option( self::PB_MEDIA_SHORTCODE_SETTING, array() );
	}

	public function get_id_settings() {
		return get_option( self::PB_IDS_SHORTCODE_SETTING, [] );
	}

	public function get_link_settings() {
		return get_option( self::PB_LINKS_SHORTCODE_SETTING, [] );
	}

	public static function get_sticky_link_settings() {
		$settings = get_option( self::PB_STICKY_LINKS_SHORTCODE_SETTING, [] );

		return is_array( $settings ) ? $settings : [];
	}

	public function has_settings() {
		$flag = get_option( self::HAS_SHORTCODE_SETTINGS_FLAG, null );
		if ( null !== $flag ) {
			return (bool) $flag;
		}

		return ! empty( $this->get_settings() );
	}
}
