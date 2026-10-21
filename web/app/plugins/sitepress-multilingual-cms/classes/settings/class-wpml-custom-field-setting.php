<?php

use WPML\FP\Logic;
use WPML\TM\Settings\PreferenceResolver;

abstract class WPML_Custom_Field_Setting extends WPML_TM_User {

	const SETTINGS_INDEX_TRANSLATE_IDS = 'translate_ids';

	private $index;

	public function __construct( &$tm_instance, $index ) {
		parent::__construct( $tm_instance );
		$this->index = (string) $index;
	}

	public function get_index() {
		return $this->index;
	}

	protected function read_sub_setting( $setting ) {
		$value = wpml_get_tm_sub_setting( $setting );

		return is_array( $value ) ? $value : array();
	}

	public function is_read_only() {

		return in_array(
			$this->index,
			$this->read_sub_setting( $this->get_array_setting_index( 'readonly_config' ) ),
			true
		);
	}

	public function is_unlocked() {
		$unlocked = $this->read_sub_setting( $this->get_unlocked_setting_index() );

		return isset( $unlocked[ $this->index ] ) && (bool) $unlocked[ $this->index ];
	}

	public function excluded() {

		return in_array( $this->index, $this->get_excluded_keys() ) ||
			   ( $this->is_read_only() &&
				 $this->status() === WPML_IGNORE_CUSTOM_FIELD &&
				 ! $this->is_unlocked()
			   );
	}

	public function status() {
		$mode = PreferenceResolver::mode( $this->get_element_type(), $this->index );
		if ( null !== $mode ) {
			return $mode;
		}

		$resolved = wpml_resolve_custom_field_preferences( array( $this->index ), $this->get_element_type() );

		return isset( $resolved[ $this->index ] ) ? (int) $resolved[ $this->index ] : WPML_IGNORE_CUSTOM_FIELD;
	}

	public function make_read_only() {
		$ro_index                                   = $this->get_array_setting_index( 'readonly_config' );
		$this->tm_instance->settings[ $ro_index ][] = $this->index;
		$this->tm_instance->settings[ $ro_index ]   = array_unique( $this->tm_instance->settings[ $ro_index ] );
	}

	public function set_to_copy() {
		$this->set_state( WPML_COPY_CUSTOM_FIELD );
	}

	public function set_to_copy_once() {
		$this->set_state( WPML_COPY_ONCE_CUSTOM_FIELD );
	}

	public function set_to_translatable() {
		$this->set_state( WPML_TRANSLATE_CUSTOM_FIELD );
	}

	public function set_to_nothing() {
		$this->set_state( WPML_IGNORE_CUSTOM_FIELD );
	}

	public function set_editor_style( $style ) {
		$this->tm_instance->settings[ $this->get_array_setting_index( 'editor_style' ) ][ $this->index ] = $style;
	}

	public function get_editor_style() {
		$setting = $this->read_sub_setting( $this->get_array_setting_index( 'editor_style' ) );
		return isset( $setting[ $this->index ] ) ? $setting[ $this->index ] : '';
	}

	public function set_editor_label( $label ) {
		$this->tm_instance->settings[ $this->get_array_setting_index( 'editor_label' ) ][ $this->index ] = $label;
	}

	public function get_editor_label() {
		$setting = $this->read_sub_setting( $this->get_array_setting_index( 'editor_label' ) );
		return isset( $setting[ $this->index ] ) ? $setting[ $this->index ] : '';
	}

	public function set_editor_group( $group ) {
		$this->tm_instance->settings[ $this->get_array_setting_index( 'editor_group' ) ][ $this->index ] = $group;
	}

	public function get_editor_group() {
		$setting = $this->read_sub_setting( $this->get_array_setting_index( 'editor_group' ) );

		return isset( $setting[ $this->index ] ) ? $setting[ $this->index ] : '';
	}

	public function set_translate_link_target( $state, $sub_fields ) {
		if ( isset( $sub_fields['value'] ) ) {
			$sub_fields = array( $sub_fields );
		}
		$this->tm_instance->settings[ $this->get_array_setting_index( 'translate_link_target' ) ][ $this->index ] = array(
			'state'      => $state,
			'sub_fields' => $sub_fields,
		);
	}

	public function clear_from_translate_ids() {
		unset( $this->tm_instance->settings[ $this->get_array_setting_index( self::SETTINGS_INDEX_TRANSLATE_IDS ) ][ $this->index ] );
	}

	public function set_field_translatable_ids( $type, $slug, $path = '' ) {
		$settings_index       = $this->get_array_setting_index( self::SETTINGS_INDEX_TRANSLATE_IDS );
		$field_index          = $this->tm_instance->settings[ $settings_index ][ $this->index ] ?? [];
		$field_index[ $path ] = [
			'type' => $type,
			'slug' => $slug,
			'path' => $path,
		];

		$this->tm_instance->settings[ $settings_index ][ $this->index ] = $field_index;
	}


	public function set_translate_link_target_sub_keys( $sub_keys, $link_keys ) {
		$settings_index = $this->get_array_setting_index( 'translate_link_target' );
		$had_entry      = isset( $this->tm_instance->settings[ $settings_index ][ $this->index ] )
			&& is_array( $this->tm_instance->settings[ $settings_index ][ $this->index ] );

		if ( ! $had_entry && ! $sub_keys ) {
			return;
		}

		$entry = $had_entry
			? $this->tm_instance->settings[ $settings_index ][ $this->index ]
			: array( 'state' => false, 'sub_fields' => array() );

		unset( $entry['sub_keys'], $entry['link_keys'] );
		if ( $sub_keys ) {
			$entry['sub_keys'] = $sub_keys;
		}
		if ( $link_keys ) {
			$entry['link_keys'] = $link_keys;
		}

		$this->tm_instance->settings[ $settings_index ][ $this->index ] = $entry;
	}

	public function get_translate_link_target_state() {
		$link_targets = $this->read_sub_setting( $this->get_array_setting_index( 'translate_link_target' ) );

		return ! empty( $link_targets[ $this->index ]['state'] );
	}

	public function is_translate_link_target() {
		$link_targets = $this->read_sub_setting( $this->get_array_setting_index( 'translate_link_target' ) );
		if ( ! isset( $link_targets[ $this->index ] ) ) {
			return false;
		}

		return ! empty( $link_targets[ $this->index ]['state'] )
			|| (bool) $this->get_translate_link_target_sub_fields()
			|| in_array( true, $this->get_translate_link_target_sub_keys(), true );
	}

	public function get_translate_link_target_sub_fields() {
		$link_targets = $this->read_sub_setting( $this->get_array_setting_index( 'translate_link_target' ) );
		return isset( $link_targets[ $this->index ]['sub_fields'] ) ?
					$link_targets[ $this->index ]['sub_fields'] :
					array();
	}

	public function get_translate_link_target_sub_keys() {
		return $this->get_translate_link_target_path_map( 'sub_keys' );
	}

	public function get_translate_link_target_link_keys() {
		return $this->get_translate_link_target_path_map( 'link_keys' );
	}

	private function get_translate_link_target_path_map( $map ) {
		$link_targets = $this->read_sub_setting( $this->get_array_setting_index( 'translate_link_target' ) );

		return isset( $link_targets[ $this->index ][ $map ] ) && is_array( $link_targets[ $this->index ][ $map ] )
			? $link_targets[ $this->index ][ $map ]
			: array();
	}

	public function set_convert_to_sticky( $state ) {
		$this->tm_instance->settings[ $this->get_array_setting_index( 'convert_to_sticky' ) ][ $this->index ] = $state;
	}

	public function is_convert_to_sticky() {
		$sticky = $this->read_sub_setting( $this->get_array_setting_index( 'convert_to_sticky' ) );
		return isset( $sticky[ $this->index ] ) ?
					$sticky[ $this->index ] :
					false;
	}

	public function set_encoding( $encoding ) {
		if ( Logic::isNotNull( $encoding ) ) {
			$this->tm_instance->settings[ $this->get_array_setting_index( 'encoding' ) ][ $this->index ] = $encoding;
		} else {
			unset( $this->tm_instance->settings[ $this->get_array_setting_index( 'encoding' ) ][ $this->index ] );
		}
	}

	public function get_encoding() {
		$setting = $this->read_sub_setting( $this->get_array_setting_index( 'encoding' ) );
		return isset( $setting[ $this->index ] ) ? $setting[ $this->index ] : '';
	}

	public function set_attributes_whitelist( $whitelist ) {
		if ( ! is_array( $whitelist ) ) {
			throw new InvalidArgumentException( '$whitelist should be an array.' );
		}
		$this->tm_instance->settings[ $this->get_array_setting_index( 'attributes_whitelist' ) ][ $this->index ] = $whitelist;
	}

	public function get_attributes_whitelist() {
		$setting = $this->read_sub_setting( $this->get_array_setting_index( 'attributes_whitelist' ) );
		return isset( $setting[ $this->index ] ) ? $setting[ $this->index ] : array();
	}

	private function set_state( $state ) {
		$this->tm_instance->settings[ $this->get_state_array_setting_index() ][ $this->index ] = $state;
	}

	private function get_array_setting_index( $index ) {
		return $this->get_setting_prefix() . $index;
	}

	abstract protected function get_state_array_setting_index();

	abstract protected function get_element_type();

	abstract protected function get_unlocked_setting_index();

	abstract protected function get_excluded_keys();

	abstract protected function get_setting_prefix();

	public function get_html_disabled() {
		$isDisabled = $this->is_read_only() && ! $this->is_unlocked();

		return apply_filters( 'wpml_custom_field_setting_is_html_disabled', $isDisabled, $this )
			? 'disabled="disabled"'
			: '';
	}
}
