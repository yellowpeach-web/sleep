<?php

use WPML\Convert\Ids;
use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\FP\Obj;
use WPML\TM\Settings\PreferenceResolver;
use WPML\TM\Settings\PreferenceWriter;
use WPML\Utils\XmlTranslatableIds;

class WPML_Custom_Field_XML_Settings_Import {

	private $setting_factory;
	private $xml_object_ids;
	private $settings_array;

	public function __construct( $setting_factory, $xml_object_ids, $settings_array ) {
		$this->setting_factory = $setting_factory;
		$this->xml_object_ids  = $xml_object_ids;
		$this->settings_array  = $settings_array;
	}

	public function run() {
		$config = $this->settings_array;
		foreach (
			array(
				ElementType::POST => array(
					'post_meta_setting',
					WPML_POST_META_CONFIG_INDEX_PLURAL,
					WPML_POST_META_CONFIG_INDEX_SINGULAR
				),
				ElementType::TERM => array(
					'term_meta_setting',
					WPML_TERM_META_CONFIG_INDEX_PLURAL,
					WPML_TERM_META_CONFIG_INDEX_SINGULAR
				)
			) as $element_type => list( $setting_constructor, $plural, $singular )
		) {
			if ( ! empty( $config[ $plural ] ) ) {
				$field = $config[ $plural ][ $singular ];
				$cf    = ! is_numeric( key( current( $config[ $plural ] ) ) ) ? array( $field ) : $field;
				$modes = [];
				foreach ( $cf as $c ) {
					$name    = trim( $c['value'] );
					$setting = call_user_func_array( array(
						$this->setting_factory,
						$setting_constructor
					), array( $name ) );
					$mode = $this->imported_mode( $c, $setting );
					if ( null !== $mode ) {
						$modes[ $name ] = $mode;
					}
					$setting->make_read_only();
					$this->import_editor_settings( $c, $setting );
					if ( isset( $c[ 'attr' ][ 'translate_link_target' ] ) || isset( $c[ 'custom-field' ] ) ) {
						$setting->set_translate_link_target( isset( $c[ 'attr' ][ 'translate_link_target' ] ) ? (bool) $c[ 'attr' ][ 'translate_link_target' ] : false, isset( $c[ 'custom-field' ] ) ? $c[ 'custom-field' ] : array() );
					}
					if ( isset( $c[ 'attr' ][ 'convert_to_sticky' ] ) ) {
						$setting->set_convert_to_sticky( (bool) $c[ 'attr' ][ 'convert_to_sticky' ] );
					}
					$setting->clear_from_translate_ids();
					$status = $modes[ $name ] ?? $setting->status();
					if ( WPML_COPY_CUSTOM_FIELD === $status && $this->xml_object_ids->hasTranslatableIds( $c ) ) {
						$target_type = $this->xml_object_ids->getType( $c );
						$target_slug = $this->xml_object_ids->getSlug( $c, $target_type );
						$setting->set_field_translatable_ids( $target_type, $target_slug );
					}
					$setting->set_encoding( Obj::path( [ 'attr', 'encoding' ], $c ) );
				}
				$this->write_changed_modes( $element_type, $modes );
			}
		}

		$this->import_custom_field_texts();
		$this->import_translatable_ids_in_custom_field_texts();
	}

	private function imported_mode( $c, $setting ) {
		if ( $setting->is_unlocked() ) {
			return null;
		}

		switch ( $c['attr']['action'] ?? '' ) {
			case 'translate':
				return WPML_TRANSLATE_CUSTOM_FIELD;
			case 'copy':
				return WPML_COPY_CUSTOM_FIELD;
			case 'copy-once':
				return WPML_COPY_ONCE_CUSTOM_FIELD;
			default:
				return WPML_IGNORE_CUSTOM_FIELD;
		}
	}

	private function write_changed_modes( $element_type, array $modes ) {
		if ( ! $modes ) {
			return;
		}

		$stored  = PreferenceResolver::modes( $element_type, array_map( 'strval', array_keys( $modes ) ) );
		$changed = array_filter(
			$modes,
			fn( $mode, $name ) => ( $stored[ $name ] ?? null ) !== $mode,
			ARRAY_FILTER_USE_BOTH
		);

		if ( $changed ) {
			PreferenceWriter::setModes( $element_type, $changed );
		}
	}

	private function import_editor_settings( $c, $setting ) {
		if ( isset( $c[ 'attr' ][ 'style' ] ) ) {
			$setting->set_editor_style( $c[ 'attr' ][ 'style' ] );
		}
		if ( isset( $c[ 'attr' ][ 'label' ] ) ) {
			$setting->set_editor_label( $c[ 'attr' ][ 'label' ] );
		}
		if ( isset( $c[ 'attr' ][ 'group' ] ) ) {
			$setting->set_editor_group( $c[ 'attr' ][ 'group' ] );
		}
	}

	private function import_custom_field_texts() {
		$config = $this->settings_array;

		if ( isset( $config['custom-fields-texts']['key'] ) ) {
			foreach( $config['custom-fields-texts']['key'] as $field ) {
				$setting = $this->setting_factory->post_meta_setting( $field['attr']['name'] );
				$setting->set_attributes_whitelist( $this->get_custom_field_texts_keys( $field['key'] ) );
				$paths = $this->get_custom_field_texts_link_target_paths( $field['key'] );
				$setting->set_translate_link_target_sub_keys( $paths['sub_keys'], $paths['link_keys'] );
			}
		}
	}

	private function import_translatable_ids_in_custom_field_texts() {
		$config = $this->settings_array;

		if ( isset( $config['custom-fields-texts']['key'] ) ) {
			foreach( $config['custom-fields-texts']['key'] as $field ) {
				$setting   = $this->setting_factory->post_meta_setting( $field['attr']['name'] );
				$pathsData = $this->xml_object_ids->findPaths( $field );
				$setting->clear_from_translate_ids();
				if ( $pathsData ) {
					foreach ( $pathsData as $path => $type_and_slug ) {
						$setting->set_field_translatable_ids( $type_and_slug['type'], $type_and_slug['slug'], $path );
					}
				}
			}
		}
	}

	private function get_custom_field_texts_keys( $data ) {
		if ( isset( $data['attr'] ) ) {
			$data = array( $data );
		}

		$sub_fields = array();

		foreach( $data as $key ) {
			if ( $this->xml_object_ids->hasTranslatableIds( $key ) ) {
				continue;
			}
			$sub_fields[ $key['attr']['name'] ] = isset( $key['key'] )
				? $this->get_custom_field_texts_keys( $key['key'] )
				: $key['attr']['label'] ?? '';
		}
		return $sub_fields;
	}
	private function get_custom_field_texts_link_target_paths( $data, $prefix = '' ) {
		if ( isset( $data['attr'] ) ) {
			$data = array( $data );
		}

		$paths = array(
			'sub_keys'  => array(),
			'link_keys' => array(),
		);

		foreach ( (array) $data as $key ) {
			if ( ! isset( $key['attr']['name'] ) || $this->xml_object_ids->hasTranslatableIds( $key ) ) {
				continue;
			}
			$path = '' === $prefix ? (string) $key['attr']['name'] : $prefix . '>' . $key['attr']['name'];

			if ( isset( $key['attr']['translate_link_target'] ) ) {
				$paths['sub_keys'][ $path ] = (bool) (int) $key['attr']['translate_link_target'];
			}
			if ( isset( $key['attr']['type'] ) && 'link' === strtolower( trim( $key['attr']['type'] ) ) ) {
				$paths['link_keys'][ $path ] = true;
			}
			if ( isset( $key['key'] ) ) {
				$child              = $this->get_custom_field_texts_link_target_paths( $key['key'], $path );
				$paths['sub_keys']  = $paths['sub_keys'] + $child['sub_keys'];
				$paths['link_keys'] = $paths['link_keys'] + $child['link_keys'];
			}
		}

		return $paths;
	}
}
