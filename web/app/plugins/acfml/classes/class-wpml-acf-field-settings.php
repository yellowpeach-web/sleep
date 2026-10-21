<?php

use ACFML\FieldGroup\Mode;
use ACFML\Helper\FieldGroup;
use ACFML\Helper\Fields;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\FP\Str;

class WPML_ACF_Field_Settings implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $tm_setting_index = [ 'custom_fields_translation', 'custom_term_fields_translation' ];

	private $translation_management;

	private $new_preference_set = false;

	private $pendingDefinitionFields = [];

	private $pendingDefinitionHookAdded = false;

	private $subfieldsQueue = [];

	private $preferenceSource = null;

	public function __construct( TranslationManagement $translation_management ) {
		$this->translation_management = $translation_management;
	}

	public function add_hooks() {
		add_action( 'acf/render_field_settings', [ $this, 'render_field_settings' ], 10, 1 );

		add_action( 'acf/updated_field', [ $this, 'update_field_settings' ], 10, 1 );

		add_filter( 'acf/update_value', [ $this, 'field_value_updated' ], 10, 4 );

		add_action( 'wpml_single_custom_field_sync_option_updated', [ $this, 'user_set_sync_preferences' ], 10, 1 );

		add_action( 'wpml_custom_fields_sync_option_updated', [ $this, 'settings_screen_set_sync_preferences' ], 10, 1 );

		add_filter( 'acf/get_field_label', [ $this, 'mark_not_migrated_field' ], 10, 2 );

		add_action( 'shutdown', [ $this, 'processSubfieldsQueue' ] );
	}

	public function render_field_settings( $field ) {
		if ( \ACFML\FieldGroup\ModeValidity::storesNoValue( $field ) ) {
			return;
		}

		$source  = $this->getPreferenceSource()->resolve( $field );
		$choices = $this->getRenderChoices( $field, $source );

		$setting = [
			/* translators: Label of the radio group on an ACF field that sets how the field is translated. */
			'label'        => __( 'Translation preferences', 'acfml' ),
			/* translators: Explanation under the translation-preferences radio group of an ACF field. */
			'instructions' => __( 'What to do with field\'s value when post/page is going to be translated', 'acfml' ),
			'type'         => 'radio',
			'name'         => 'wpml_cf_preferences',
			'layout'       => 'horizontal',
			'choices'      => $choices,
		];

		if ( $source['isLocked'] ) {
			$setting['value']    = (string) $source['preference'];
			$setting['disabled'] = array_keys( $choices );
		}

		acf_render_field_setting( $field, $setting, true );

		if ( $source['isLocked'] ) {
			$this->render_locked_preference_input( $field );
		}

		$this->render_field_preference_descriptions( $field, $choices );
		$this->render_field_source_line( $source );
	}

	private function getPreferenceSource() {
		if ( ! $this->preferenceSource ) {
			$this->preferenceSource = new \ACFML\FieldGroup\PreferenceSource( $this->translation_management );
		}

		return $this->preferenceSource;
	}

	private function getRenderChoices( $field, array $source ) {
		$choices = $this->getFieldOptionsForField( $field );

		if ( ! $source['isLocked'] || null === $source['preference'] ) {
			return $choices;
		}

		$all        = $this->getFieldOptions();
		$preference = (int) $source['preference'];

		if ( ! isset( $choices[ $preference ] ) && isset( $all[ $preference ] ) ) {
			$choices[ $preference ] = $all[ $preference ];
		}

		return $choices;
	}

	private function render_locked_preference_input( $field ) {
		$stored = Obj::prop( 'wpml_cf_preferences', $field );
		$prefix = Obj::prop( 'prefix', $field );

		if ( null === $stored || '' === $stored || ! $prefix ) {
			return;
		}

		printf(
			'<input type="hidden" name="%s[wpml_cf_preferences]" value="%s" />',
			esc_attr( $prefix ),
			esc_attr( (string) $stored )
		);
	}

	private function render_field_source_line( array $source ) {
		$copy = \ACFML\FieldGroup\PreferenceSource::getLines( $source );

		if ( '' === $copy['headline'] && ! $copy['lines'] ) {
			return;
		}

		$lines = '';
		foreach ( $copy['lines'] as $line ) {
			$lines .= '<span class="acfml-cf-source__line">' . $line . '</span>';
		}

		$class = 'acfml-cf-source' . ( $source['isLocked'] ? ' acfml-cf-source--locked' : '' );

		echo '<div class="' . esc_attr( $class ) . '" data-name="wpml_cf_preferences">'
			. '<span class="acfml-cf-source__headline">' . $copy['headline'] . '</span>' . $lines
			. '</div>';
	}

	private function getFieldOptionsForField( $field ) {
		$options = $this->getFieldOptions();

		if ( \ACFML\FieldGroup\ModeValidity::isTranslateOffered( $field ) ) {
			return $options;
		}

		if ( $this->is_unoffered_preference_kept( $field ) ) {
			/* translators: Option kept on a field that was already set to Translate, for a field type where the option is no longer offered. "Translate" is the preference's name, translated the same way in the preference list. */
			$options[ WPML_TRANSLATE_CUSTOM_FIELD ] = esc_html__( 'Translate (your current setting, no longer offered for this field type)', 'acfml' );

			return $options;
		}

		unset( $options[ WPML_TRANSLATE_CUSTOM_FIELD ] );

		return $options;
	}

	private function is_unoffered_preference_kept( $field ) {
		$stored = Obj::prop( 'wpml_cf_preferences', $field );

		return null !== $stored
			&& WPML_TRANSLATE_CUSTOM_FIELD === (int) $stored
			&& ! \ACFML\FieldGroup\ModeValidity::isTranslateOffered( $field );
	}

	private function render_field_preference_descriptions( $field, $offered = null ) {
		$descriptions = $this->get_field_option_descriptions();
		$offered      = null === $offered ? $this->getFieldOptionsForField( $field ) : $offered;
		$items        = '';

		foreach ( $this->getFieldOptions() as $value => $label ) {
			if ( ! isset( $descriptions[ $value ], $offered[ $value ] ) ) {
				continue;
			}

			$flagText = $this->get_preference_flag( $field, $value );
			$flag     = $flagText
				? '<span class="acfml-cf-preferences-help__flag">' . $flagText . '</span>'
				: '';

			$items .= '<li class="acfml-cf-preferences-help__item' . ( $flagText ? ' acfml-cf-preferences-help__item--flagged' : '' ) . '">'
				. '<span class="acfml-cf-preferences-help__value">' . esc_html( $label ) . '</span>'
				. '<span class="acfml-cf-preferences-help__outcome">' . $descriptions[ $value ][0] . '</span>'
				. '<span class="acfml-cf-preferences-help__editing">' . $descriptions[ $value ][1] . '</span>'
				. $flag
				. '</li>';
		}

		$reason = \ACFML\FieldGroup\ModeValidity::getReason( $field );
		$note   = $reason
			? '<p class="acfml-cf-preferences-help__reason">' . $reason . '</p>'
			: '';

		echo '<div class="acfml-cf-preferences-help" data-name="wpml_cf_preferences">'
			. '<ul class="acfml-cf-preferences-help__list">' . $items . '</ul>' . $note
			. '</div>';
	}

	private function get_preference_flag( $field, $value ) {
		if ( WPML_TRANSLATE_CUSTOM_FIELD !== $value ) {
			return '';
		}

		if ( $this->is_unoffered_preference_kept( $field ) ) {
			/* translators: Explanation under the Translate option that was kept on a field type where it is no longer offered. */
			return esc_html__( 'Kept because it is your current setting. This value is no longer offered for this field type.', 'acfml' );
		}

		if ( \ACFML\FieldGroup\ModeValidity::isTranslateFlagged( $field ) ) {
			/* translators: Warning under a translation preference that WPML advises against for the field being edited. */
			return esc_html__( 'Not recommended for this field type.', 'acfml' );
		}

		return '';
	}

	private function get_field_option_descriptions() {
		return [
			WPML_IGNORE_CUSTOM_FIELD    => [
				/* translators: First line describing the "Don't translate" preference of an ACF field. */
				esc_html__( 'Translated pages start without this content.', 'acfml' ),
				/* translators: Second line describing the "Don't translate" preference of an ACF field. */
				esc_html__( 'Editable in other languages, and starts empty.', 'acfml' ),
			],
			WPML_COPY_CUSTOM_FIELD      => [
				/* translators: First line describing the "Copy" preference of an ACF field. */
				esc_html__( 'Visitors in every language always see exactly what the original shows.', 'acfml' ),
				/* translators: Second line describing the "Copy" preference of an ACF field; "it" is the field. */
				esc_html__( 'Shown locked in other languages, so it cannot be edited there.', 'acfml' ),
			],
			WPML_COPY_ONCE_CUSTOM_FIELD => [
				/* translators: First line describing the "Copy once" preference of an ACF field. */
				esc_html__( 'Starts the same as the original, then each language goes its own way.', 'acfml' ),
				/* translators: Second line describing the "Copy once" preference of an ACF field. */
				esc_html__( 'Editable in every language, and permanently yours to maintain by hand.', 'acfml' ),
			],
			WPML_TRANSLATE_CUSTOM_FIELD => [
				/* translators: First line describing the "Translate" preference of an ACF field; "this" is the field's value. */
				esc_html__( 'Visitors see this in their own language.', 'acfml' ),
				/* translators: Second line describing the "Translate" preference of an ACF field. */
				esc_html__( 'Filled by the translation and edited in the translation editor.', 'acfml' ),
			],
		];
	}

	public function update_field_settings( $field, $updateSubfields = true ) {
		if ( $this->is_field_parsable( $field ) ) {
			\ACFML\FieldGroup\PreferenceOrigin::clear( Obj::propOr( '', 'name', $field ) );
			$this->save_field_settings( $field );
			if ( $updateSubfields ) {
				$this->maybeAddToSubfieldsQueue( $field );
			}
		}
	}

	public function field_value_updated( $value, $post_id, $field, $_value = null ) {
		if ( $this->is_field_parsable( $field ) ) {
			if ( $this->is_pattern_resolved_subfield_instance( $field, $post_id ) ) {
				return $value;
			}

			$this->save_field_settings( $field, $post_id );
		}

		return $value;
	}

	private function is_pattern_resolved_subfield_instance( $field, $post_id ) {
		if ( ! \ACFML\FieldPreferences\SubfieldRules::isCoreResolutionAvailable()
			|| ! isset( $field['name'], $field['_name'] )
			|| ! is_string( $field['_name'] )
			|| '' === $field['_name']
			|| $field['name'] === $field['_name']
		) {
			return false;
		}

		$type = $this->tm_setting_index[1] === $this->get_setting_index_for_context( $post_id ) ? 'term' : 'post';

		return null !== \ACFML\FieldPreferences\SubfieldRules::resolveKey( $field['name'], $type );
	}

	private function is_field_parsable( $field ) {
		return ( isset( $field['wpml_cf_preferences'], $field['name'] ) && $this->isValidFieldPreference( $field['wpml_cf_preferences'] ) && $field['name'] )
			|| $this->field_should_be_set_to_copy_once( $field );
	}

	private function getFieldOptions() {
		return \ACFML\FieldGroup\PreferenceSource::getPreferenceLabels();
	}

	private function isValidFieldPreference( $preference ) {
		return array_key_exists( $preference, $this->getFieldOptions() );
	}

	private function save_field_settings( $field, $post_id = null ) {
		if ( isset( $field['wpml_cf_preferences'] ) ) {
			$setting_indexes = null === $post_id
				? $this->get_setting_indexes_for_definition( $field )
				: [ $this->get_setting_index_for_context( $post_id ) ];

			if ( null === $setting_indexes ) {
				$this->defer_definition_write( $field );

				return;
			}

			$this->write_preference_to_indexes( $field, $setting_indexes );

			if ( $this->new_preference_set ) {
				$this->translation_management->save_settings();
				$this->new_preference_set = false;
			}
		}
	}

	private function write_preference_to_indexes( $field, $setting_indexes ) {
		foreach ( $setting_indexes as $setting_index ) {
			$this->maybe_set_new_preference( $setting_index, $field['name'], $field['wpml_cf_preferences'] );
			if ( WPML_IGNORE_CUSTOM_FIELD !== (int) $field['wpml_cf_preferences'] ) {
				$this->update_corresponding_system_field_settings( $field, $field['name'], $setting_index );
			}
		}
	}

	private function defer_definition_write( $field ) {
		$this->pendingDefinitionFields[] = $field;

		if ( ! $this->pendingDefinitionHookAdded ) {
			add_action( 'shutdown', [ $this, 'processPendingDefinitionWrites' ], 5 );
			$this->pendingDefinitionHookAdded = true;
		}
	}

	public function processPendingDefinitionWrites() {
		foreach ( $this->pendingDefinitionFields as $field ) {
			$setting_indexes = $this->get_setting_indexes_for_definition( $field );
			$this->write_preference_to_indexes( $field, null === $setting_indexes ? [ $this->tm_setting_index[0] ] : $setting_indexes );
		}
		$this->pendingDefinitionFields = [];

		if ( $this->new_preference_set ) {
			$this->translation_management->save_settings();
			$this->new_preference_set = false;
		}
	}

	private function get_setting_indexes_for_definition( $field ) {
		$fieldGroupKey = FieldGroup::getKey( Obj::prop( 'parent', $field ) );
		$fieldGroup    = $fieldGroupKey && function_exists( 'acf_get_field_group' ) ? acf_get_field_group( $fieldGroupKey ) : null;

		if ( ! is_array( $fieldGroup ) ) {
			$fieldGroup = $this->read_field_group_from_posts( Obj::prop( 'parent', $field ) );
		}

		if ( ! is_array( $fieldGroup ) ) {
			return null;
		}

		$indexes = [];
		foreach ( \ACFML\FieldPreferences\SubfieldRules::elementTypesForGroup( $fieldGroup ) as $type ) {
			$indexes[] = 'term' === $type ? $this->tm_setting_index[1] : $this->tm_setting_index[0];
		}

		return $indexes;
	}

	private function read_field_group_from_posts( $reference ) {
		if ( is_string( $reference ) && 0 === strpos( $reference, 'group_' ) ) {
			$posts     = get_posts(
				[
					'post_type'      => 'acf-field-group',
					'name'           => $reference,
					'posts_per_page' => 1,
					'post_status'    => 'any',
				]
			);
			$reference = $posts ? $posts[0]->ID : null;
		}

		for ( $hops = 0; $hops < 10 && is_numeric( $reference ) && $reference; $hops++ ) {
			$post = get_post( (int) $reference );
			if ( ! $post ) {
				return null;
			}
			if ( 'acf-field-group' === $post->post_type ) {
				$settings = maybe_unserialize( $post->post_content );

				return is_array( $settings ) ? $settings : null;
			}
			if ( 'acf-field' !== $post->post_type ) {
				return null;
			}
			$reference = $post->post_parent;
		}

		return null;
	}

	private function get_setting_index_for_context( $post_id = null ) {
		if ( null !== $post_id && function_exists( 'acf_get_post_id_info' ) ) {
			$info = acf_get_post_id_info( $post_id );
			if ( isset( $info['type'] ) && 'term' === $info['type'] ) {
				return $this->tm_setting_index[1];
			}
		}

		return $this->tm_setting_index[0];
	}

	public function settings_screen_set_sync_preferences( $cft ) {
		foreach ( array_keys( (array) $cft ) as $field_name ) {
			\ACFML\FieldGroup\PreferenceOrigin::stampWpmlSettings( $field_name );
		}

		$this->user_set_sync_preferences( $cft );
	}

	public function user_set_sync_preferences( $cft ) {
		$definition_ids_by_name = $this->get_field_definition_ids( array_keys( (array) $cft ) );

		foreach ( $cft as $field_name => $field_preferences ) {
			$definition_ids = $definition_ids_by_name[ $field_name ] ?? [];

			if ( ! $definition_ids ) {
				continue;
			}

			if ( 1 === count( $definition_ids ) ) {
				$this->update_field_group_post( (int) $definition_ids[0], $field_preferences );
				continue;
			}

			$post_id = get_the_ID() ?: get_queried_object_id();
			if ( ! $post_id ) {
				continue;
			}
			$field_object = get_field_object( $field_name, $post_id );

			if ( $this->is_field_object_valid( $field_object ) ) {
				$this->update_field_group_post( $field_object['ID'], $field_preferences );
			}
		}

		remove_action( 'wpml_custom_fields_sync_option_updated', [ $this, 'settings_screen_set_sync_preferences' ], 10 );
	}

	private function get_field_definition_ids( array $field_names ) {
		$field_names = array_values( array_unique( array_filter( $field_names, 'is_string' ) ) );
		if ( ! $field_names ) {
			return [];
		}

		global $wpdb;
		$placeholders = implode( ', ', array_fill( 0, count( $field_names ), '%s' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_excerpt FROM {$wpdb->posts}
				 WHERE post_type = 'acf-field' AND post_status = 'publish'
				   AND post_excerpt IN ($placeholders)",
				$field_names
			)
		);

		$ids_by_name = [];
		foreach ( (array) $rows as $row ) {
			$ids_by_name[ (string) $row->post_excerpt ][] = (int) $row->ID;
		}

		return $ids_by_name;
	}

	public function update_field_group_post( $field_object_id, $field_preferences ) {
		if ( ! $this->isValidFieldPreference( $field_preferences ) ) {
			return;
		}
		$field_post = get_post( $field_object_id );
		if ( is_object( $field_post ) ) {
			$field_post_content = maybe_unserialize( $field_post->post_content );
			if ( is_array( $field_post_content ) ) {
				if ( isset( $field_post_content['wpml_cf_preferences'] )
					&& (int) $field_post_content['wpml_cf_preferences'] === (int) $field_preferences
				) {
					return;
				}
				$field_post_content['wpml_cf_preferences'] = $field_preferences;
				wp_update_post(
					[
						'ID'           => $field_object_id,
						'post_content' => maybe_serialize( $field_post_content ),
					]
				);
				( new \ACFML\FieldPreferences\SubfieldRules() )->invalidate();
			}
		}
	}

	private function is_field_object_valid( $field_object ) {
		return is_array( $field_object ) && is_numeric( $field_object['ID'] ) && $field_object['ID'] > 0;
	}

	private function get_post_with_custom_field( $field_name ) {
		$post_id = get_the_ID() ?: get_queried_object();
		if ( ! is_numeric( $post_id ) ) {
			global $wpdb;
			$query   = "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1";
			$post_id = $wpdb->get_var( $wpdb->prepare( $query, $field_name ) );
		}

		return $post_id;
	}

	public function maybeAddToSubfieldsQueue( $field ) {
		if (
			$this->is_field_parsable( $field )
			&& isset( $field['parent'] )
			&& isset( $field['wpml_cf_preferences'] )
		) {
			$this->subfieldsQueue[ $field['key'] ] = [
				'parent'              => $field['parent'],
				'wpml_cf_preferences' => $field['wpml_cf_preferences'],
			];
		}
	}

	private function update_corresponding_system_field_settings( $field, $field_name, $setting_index ) {
		$fieldGroupKey = FieldGroup::getKey( Obj::prop( 'parent', $field ) );
		if ( $fieldGroupKey ) {
			$preference = Mode::LOCALIZATION === Mode::getMode( acf_get_field_group( $fieldGroupKey ) ) ? WPML_COPY_ONCE_CUSTOM_FIELD : WPML_COPY_CUSTOM_FIELD;

			$corresponding_field_name = '_' . $field_name;
			$this->maybe_set_new_preference( $setting_index, $corresponding_field_name, $preference );
		}
	}

	public function mark_not_migrated_field( $label, $field ) {
		if ( ! isset( $field['wpml_cf_preferences'] ) && $field['ID'] > 0 && $this->isFieldGroupEditScreen() ) {
			$post_exist = $this->get_post_with_custom_field( $field['name'] );
			if ( $post_exist ) {
				$label .= sprintf(
					' <i class="otgs-ico-warning-o js-otgs-popover-tooltip"  data-tippy-zIndex="999999" title="%s"></i>',
					/* translators: Tooltip of the warning icon next to a field whose translation preference has not been chosen. */
					esc_attr__( 'Edit the field to set the translation preference.', 'acfml' )
				);
			}
		}

		return $label;
	}

	private function isFieldGroupEditScreen() {
		global $post_type, $editing;

		return 'acf-field-group' === $post_type && true === $editing;
	}

	public function field_should_be_set_to_copy_once( $field ) {
		$fields_always_copied = [
			'repeater',
			'flexible_content',
		];

		return isset( $field['type'] ) && in_array( $field['type'], $fields_always_copied, true );
	}

	public function fieldPreferencesNotMigrated( $field ) {
		return isset( $field['wpml_cf_preferences'] )
			&& WPML_IGNORE_CUSTOM_FIELD === $field['wpml_cf_preferences'];
	}

	private function maybe_set_new_preference( $setting_index, $field, $preference ) {
		if ( ! isset( $this->translation_management->settings[ $setting_index ][ $field ] )
			|| $this->translation_management->settings[ $setting_index ][ $field ] !== $preference
		) {
			$this->translation_management->settings[ $setting_index ][ $field ] = $preference;
			$this->new_preference_set = true;
		}
	}

	public function processSubfieldsQueue() {
		if ( empty( $this->subfieldsQueue ) ) {
			return;
		}

		$definitionNames = \ACFML\FieldPreferences\SubfieldRules::definitionNames();

		$isLocalEnabled = acf_is_local_enabled();
		if ( ! $isLocalEnabled ) {
			acf_enable_local();
		}

		$patternsByGroup    = [];
		$getPatternsByGroup = function ( $fieldGroupKey ) use ( &$patternsByGroup ) {
			if ( ! array_key_exists( $fieldGroupKey, $patternsByGroup ) ) {
				$fieldNamePatterns   = wpml_collect();
				$getFieldNamePattern = function ( $field, $fieldPattern ) use ( $fieldNamePatterns ) {
					$fieldNamePatterns->put( $field['key'], $fieldPattern );

					return $field;
				};
				Fields::iterate( acf_get_fields( $fieldGroupKey ), $getFieldNamePattern, Fns::identity() );
				$patternsByGroup[ $fieldGroupKey ] = $fieldNamePatterns->toArray();
			}

			return $patternsByGroup[ $fieldGroupKey ];
		};

		$getGroupKeyFromParentField = function ( $fieldData ) {
			$parentField = acf_get_field( $fieldData['parent'] );
			if ( ! $parentField ) {
				return null;
			}
			$grandParentKey = Obj::prop( 'parent', $parentField );
			if ( ! $grandParentKey ) {
				return null;
			}

			return FieldGroup::getKey( $grandParentKey );
		};

		foreach ( $this->subfieldsQueue as $fieldKey => $fieldData ) {
			$fieldGroupKey = $getGroupKeyFromParentField( $fieldData );
			if ( ! $fieldGroupKey ) {
				continue;
			}

			$fieldNamePatternsByKey = $getPatternsByGroup( $fieldGroupKey );

			if ( array_key_exists( $fieldKey, $fieldNamePatternsByKey ) ) {
				$fieldPattern = $fieldNamePatternsByKey[ $fieldKey ];
				$this->updateSubfieldsByPatterns( $fieldPattern, $fieldData['wpml_cf_preferences'], $fieldGroupKey, $definitionNames );
			}
		}

		$this->subfieldsQueue = [];
		if ( ! $isLocalEnabled ) {
			acf_disable_local();
		}

		if ( $this->new_preference_set ) {
			$this->translation_management->save_settings();
			$this->new_preference_set = false;
		}
	}

	private function updateSubfieldsByPatterns( $fieldPattern, $preference, $fieldGroupKey, $definitionNames ) {
		$fieldPatterns = [
			$fieldPattern => (int) $preference,
		];

		if ( WPML_IGNORE_CUSTOM_FIELD !== (int) $preference ) {
			$systemFieldPattern                   = '_' . $fieldPattern;
			$fieldPatterns[ $systemFieldPattern ] = ( Mode::LOCALIZATION === Mode::getMode( acf_get_field_group( $fieldGroupKey ) ) ) ? WPML_COPY_ONCE_CUSTOM_FIELD : WPML_COPY_CUSTOM_FIELD;
		}

		foreach ( $this->tm_setting_index as $settingIndex ) {
			$this->updateSubfieldsInIndexByPatterns( $settingIndex, $fieldPatterns, $definitionNames );
		}
	}

	private function updateSubfieldsInIndexByPatterns( $settingIndex, $fieldPatterns, $definitionNames ) {
		$matchByPatterns = function ( $fieldName, $storedPreference ) use ( $settingIndex, $fieldPatterns, $definitionNames ) {
			$ownName = 0 === strpos( $fieldName, '_' ) ? substr( $fieldName, 1 ) : $fieldName;
			if ( isset( $definitionNames[ $fieldName ] ) || isset( $definitionNames[ $ownName ] ) ) {
				return;
			}

			foreach ( $fieldPatterns as $fieldPattern => $newPreference ) {
				if ( $storedPreference === $newPreference ) {
					continue;
				}
				if ( ! Str::match( '#^' . $fieldPattern . '$#', $fieldName ) ) {
					continue;
				}
				$this->translation_management->settings[ $settingIndex ][ $fieldName ] = $newPreference;
				$this->new_preference_set = true;
			}
		};

		$storedPreferences = isset( $this->translation_management->settings[ $settingIndex ] )
			? $this->translation_management->settings[ $settingIndex ]
			: [];

		if ( ! is_array( $storedPreferences ) ) {
			return;
		}

		foreach ( $storedPreferences as $fieldName => $storedPreference ) {
			$matchByPatterns( $fieldName, $storedPreference );
		}
	}
}
