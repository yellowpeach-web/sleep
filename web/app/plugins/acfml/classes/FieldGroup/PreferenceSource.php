<?php

namespace ACFML\FieldGroup;

use ACFML\FieldPreferences\SubfieldRules;
use ACFML\Tools\AdminUrl;
use WPML\FP\Obj;

class PreferenceSource {

	const SOURCE_FILTER = 'wpml_custom_field_preference_source';

	const KIND_CONFIG_THEME   = 'theme-config';
	const KIND_CONFIG_PLUGIN  = 'plugin-config';
	const KIND_CONFIG_REMOTE  = 'remote-config';
	const KIND_CONFIG_CUSTOM  = 'custom-xml';
	const KIND_CONFIG_UNKNOWN = 'config';

	const KIND_THIS_GROUP    = 'this-group';
	const KIND_OTHER_GROUP   = 'other-group';
	const KIND_LOCAL_JSON    = 'local-json';
	const KIND_LOCAL_PHP     = 'local-php';
	const KIND_WPML_SETTINGS = 'wpml-settings';

	private $tm;

	private $fieldNamePatterns;

	private $localJsonFiles = null;

	public function __construct( $tm = null, $fieldNamePatterns = null ) {
		$this->tm                = $tm;
		$this->fieldNamePatterns = $fieldNamePatterns ? $fieldNamePatterns : new FieldNamePatterns();
	}

	public function resolve( $field, $fieldGroup = null ) {
		$fieldName  = (string) Obj::propOr( '', 'name', $field );
		$fieldGroup = is_array( $fieldGroup ) ? $fieldGroup : $this->readFieldGroup( $field );
		$storedRaw  = Obj::prop( 'wpml_cf_preferences', $field );
		$stored     = ( null === $storedRaw || '' === $storedRaw ) ? null : (int) $storedRaw;

		$resolution = [
			'preference' => null,
			'stored'     => $stored,
			'isLocked'   => false,
			'kind'       => self::KIND_THIS_GROUP,
			'file'       => null,
			'groupTitle' => null,
			'groupLink'  => null,
			'alsoInJson' => null,
		];

		if ( '' === $fieldName ) {
			return $resolution;
		}

		$elementType = $this->getElementType( $fieldGroup );
		$state       = $this->readSettingState( $fieldName, $elementType );

		if ( $state ) {
			$resolution['preference'] = $state['preference'];
			$resolution['isLocked']   = $state['readOnly'] && ! $state['unlocked'];
		}

		if ( $state && $state['readOnly'] ) {
			return array_merge( $resolution, $this->readConfigSource( $fieldName, $elementType ) );
		}

		return array_merge( $resolution, $this->readGroupSource( $fieldName, $fieldGroup ) );
	}

	private function readConfigSource( $fieldName, $elementType ) {
		$source = apply_filters( self::SOURCE_FILTER, null, $fieldName, $elementType );

		$kind = is_array( $source ) && ! empty( $source['kind'] ) && is_string( $source['kind'] )
			? $source['kind']
			: self::KIND_CONFIG_UNKNOWN;

		$file = is_array( $source ) && ! empty( $source['file'] ) && is_string( $source['file'] )
			? $source['file']
			: null;

		return [
			'kind' => $kind,
			'file' => $file,
		];
	}

	private function readGroupSource( $fieldName, $fieldGroup ) {
		$thisGroupKey = (string) Obj::propOr( '', 'key', (array) $fieldGroup );
		$found        = [
			'kind'       => self::KIND_THIS_GROUP,
			'file'       => null,
			'groupTitle' => null,
			'groupLink'  => null,
			'alsoInJson' => $this->getLocalJsonFile( $thisGroupKey ),
		];

		if ( PreferenceOrigin::isSetOnWpmlSettings( $fieldName ) ) {
			$found['kind'] = self::KIND_WPML_SETTINGS;

			return $found;
		}

		$matchedKey = $this->fieldNamePatterns->findMatchingGroup( $fieldName );

		if ( $matchedKey && $matchedKey !== $thisGroupKey ) {
			$matchedGroup        = $this->readGroupByKey( $matchedKey );
			$found['kind']       = self::KIND_OTHER_GROUP;
			$found['groupTitle'] = (string) Obj::propOr( $matchedKey, 'title', (array) $matchedGroup );
			$found['groupLink']  = $this->getGroupEditLink( $matchedGroup );

			return $found;
		}

		if ( $matchedKey === $thisGroupKey && '' !== $thisGroupKey ) {
			return $found;
		}

		$localJsonKey = $this->fieldNamePatterns->findMatchingLocalGroup( $fieldName, 'json' );
		if ( $localJsonKey ) {
			$found['kind'] = self::KIND_LOCAL_JSON;
			$found['file'] = $this->getLocalJsonFile( $localJsonKey );

			return $found;
		}

		if ( $this->fieldNamePatterns->findMatchingLocalGroup( $fieldName, 'php' ) ) {
			$found['kind'] = self::KIND_LOCAL_PHP;

			return $found;
		}

		return $found;
	}

	private function readSettingState( $fieldName, $elementType ) {
		if ( ! $this->tm ) {
			return null;
		}

		try {
			$factory = $this->tm->settings_factory();

			$setting = 'term' === $elementType
				? $factory->term_meta_setting( $fieldName )
				: $factory->post_meta_setting( $fieldName );

			return [
				'preference' => (int) $setting->status(),
				'readOnly'   => (bool) $setting->is_read_only(),
				'unlocked'   => (bool) $setting->is_unlocked(),
			];
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private function getElementType( $fieldGroup ) {
		if ( ! is_array( $fieldGroup ) ) {
			return 'post';
		}

		$types = SubfieldRules::elementTypesForGroup( $fieldGroup );

		return [ 'term' ] === $types ? 'term' : 'post';
	}

	private function readFieldGroup( $field ) {
		$parent = Obj::prop( 'parent', $field );

		if ( ! $parent || ! function_exists( 'acf_get_field_group' ) ) {
			return null;
		}

		$fieldGroup = acf_get_field_group( $parent );

		return is_array( $fieldGroup ) ? $fieldGroup : null;
	}

	private function readGroupByKey( $groupKey ) {
		if ( ! function_exists( 'acf_get_field_group' ) ) {
			return null;
		}

		$fieldGroup = acf_get_field_group( $groupKey );

		return is_array( $fieldGroup ) ? $fieldGroup : null;
	}

	private function getGroupEditLink( $fieldGroup ) {
		$groupId = (int) Obj::propOr( 0, 'ID', (array) $fieldGroup );

		if ( ! $groupId || ! function_exists( 'acf_get_field_group_edit_link' ) ) {
			return null;
		}

		return acf_get_field_group_edit_link( $groupId );
	}

	private function getLocalJsonFile( $groupKey ) {
		if ( '' === $groupKey || ! function_exists( 'acf_get_local_json_files' ) ) {
			return null;
		}

		if ( null === $this->localJsonFiles ) {
			$this->localJsonFiles = (array) acf_get_local_json_files();
		}

		$path = isset( $this->localJsonFiles[ $groupKey ] ) ? (string) $this->localJsonFiles[ $groupKey ] : '';

		if ( '' === $path ) {
			return null;
		}

		$parts = array_slice( explode( '/', str_replace( '\\', '/', $path ) ), -2 );

		return implode( '/', $parts );
	}

	public static function getLines( array $source ) {
		$preference = isset( $source['preference'] ) ? $source['preference'] : null;
		$file       = isset( $source['file'] ) ? $source['file'] : null;
		$lines      = [];

		if ( ! empty( $source['isLocked'] ) ) {
			$headline = self::getLockHeadline( isset( $source['kind'] ) ? $source['kind'] : self::KIND_CONFIG_UNKNOWN );
			$lines[]  = self::getOutcomeSentence( $preference );
			/* translators: Line in the tooltip of a field whose translation preference comes from a wpml-config.xml file; "this" is the preference. */
			$lines[]  = esc_html__( 'The file decides this again on every import, so changes made elsewhere do not stick.', 'acfml' );
			$lines[]  = $file
				/* translators: %s is a configuration file path, for example themes/acme/wpml-config.xml. */
				? sprintf( esc_html__( 'Edit %s, or remove the entry there, to manage this field on this screen.', 'acfml' ), esc_html( $file ) )
				/* translators: Last line of the tooltip of a field whose preference comes from a configuration file whose path is unknown. */
				: esc_html__( 'Edit the configuration file, or remove the entry there, to manage this field on this screen.', 'acfml' );

			return [
				'headline' => $headline,
				'lines'    => array_values( array_filter( $lines ) ),
			];
		}

		$lines[] = self::getOutcomeSentence( $preference );

		if ( null !== $preference && isset( $source['stored'] ) && (int) $source['stored'] !== (int) $preference ) {
			/* translators: Warning in the tooltip when the stored preference of a field differs from the one now chosen on the screen. */
			$lines[] = esc_html__( 'That is not the value selected above. Saving this field group applies the value above.', 'acfml' );
		}

		if ( ! empty( $source['alsoInJson'] ) ) {
			$lines[] = sprintf(
				/* translators: %s is a local JSON file path, for example acf-json/group_640ab1.json. */
				esc_html__( 'This field group is also in %s. That file re-applies these settings the next time the group is synced.', 'acfml' ),
				esc_html( $source['alsoInJson'] )
			);
		}

		return [
			'headline' => self::getSetWhereSentence( $source ),
			'lines'    => array_values( array_filter( $lines ) ),
		];
	}

	private static function getLockHeadline( $kind ) {
		switch ( $kind ) {
			case self::KIND_CONFIG_THEME:
				/* translators: First line of the tooltip of a field whose translation preference the theme sets. Keep the file name in English. */
				return esc_html__( 'Locked. Managed by your theme, in its wpml-config.xml file.', 'acfml' );

			case self::KIND_CONFIG_PLUGIN:
				/* translators: First line of the tooltip of a field whose translation preference a plugin sets. Keep the file name in English. */
				return esc_html__( 'Locked. Managed by a plugin, in its wpml-config.xml file.', 'acfml' );

			case self::KIND_CONFIG_REMOTE:
				/* translators: First line of the tooltip of a field whose translation preference comes from a configuration file WPML fetched. */
				return esc_html__( 'Locked. Managed by a configuration file WPML downloaded for one of your plugins or themes.', 'acfml' );

			case self::KIND_CONFIG_CUSTOM:
				/* translators: First line of the tooltip of a field whose translation preference comes from the XML the site owner pasted into WPML's settings. */
				return esc_html__( 'Locked. Managed by the custom XML configuration saved in WPML settings.', 'acfml' );

			default:
				/* translators: First line of the tooltip of a field whose translation preference comes from a configuration file WPML cannot name. */
				return esc_html__( 'Locked. Managed by a configuration file on this site.', 'acfml' );
		}
	}

	private static function getSetWhereSentence( array $source ) {
		switch ( isset( $source['kind'] ) ? $source['kind'] : self::KIND_THIS_GROUP ) {
			case self::KIND_OTHER_GROUP:
				$title = isset( $source['groupTitle'] ) ? (string) $source['groupTitle'] : '';
				$text  = sprintf(
					/* translators: %s is another field group's title. */
					esc_html__( 'Set in the field group "%s", which uses the same field name.', 'acfml' ),
					esc_html( $title )
				);

				return empty( $source['groupLink'] )
					? $text
					: '<a href="' . esc_url( $source['groupLink'] ) . '">' . $text . '</a>';

			case self::KIND_LOCAL_JSON:
				return empty( $source['file'] )
					/* translators: Line in the tooltip saying where a field's translation preference comes from, when the JSON file's path is unknown. */
					? esc_html__( 'Set in the field group\'s local JSON file.', 'acfml' )
					: sprintf(
						/* translators: %s is a local JSON file path, for example acf-json/group_640ab1.json. */
						esc_html__( 'Set in the field group\'s local JSON file, %s.', 'acfml' ),
						esc_html( $source['file'] )
					);

			case self::KIND_LOCAL_PHP:
				/* translators: Line in the tooltip saying where a field's translation preference comes from. */
				return esc_html__( 'Registered with PHP by your theme or a plugin.', 'acfml' );

			case self::KIND_WPML_SETTINGS:
				return '<a href="' . esc_url( AdminUrl::getCustomFieldsTranslationSettings() ) . '">'
					/* translators: Link in the tooltip saying where a field's translation preference comes from; "Custom Fields Translation" is the name of the section on that screen. */
					. esc_html__( 'Set on the WPML Settings screen (Custom Fields Translation).', 'acfml' )
					. '</a>';

			default:
				/* translators: Line in the tooltip saying that a field's translation preference was set on the field group screen the reader is looking at. */
				return esc_html__( 'Set here, on this screen.', 'acfml' );
		}
	}

	private static function getOutcomeSentence( $preference ) {
		if ( null === $preference ) {
			return '';
		}

		switch ( (int) $preference ) {
			case WPML_TRANSLATE_CUSTOM_FIELD:
				/* translators: Line in the tooltip saying what a field's current translation preference does. */
				return esc_html__( 'Right now this field is translated, so visitors see it in their own language.', 'acfml' );

			case WPML_COPY_CUSTOM_FIELD:
				/* translators: Line in the tooltip saying what a field's current translation preference does. */
				return esc_html__( 'Right now this field stays identical in every language.', 'acfml' );

			case WPML_COPY_ONCE_CUSTOM_FIELD:
				/* translators: Line in the tooltip saying what a field's current translation preference does. */
				return esc_html__( 'Right now this field starts as a copy in every language, then each language keeps its own.', 'acfml' );

			case WPML_IGNORE_CUSTOM_FIELD:
				/* translators: Line in the tooltip saying what a field's current translation preference does. */
				return esc_html__( 'Right now this field stays out of translations.', 'acfml' );

			default:
				return '';
		}
	}

	public static function getPreferenceLabels() {
		return [
			/* translators: Name of the translation preference that leaves a field out of translations. Verb phrase, imperative. */
			WPML_IGNORE_CUSTOM_FIELD    => __( "Don't translate", 'acfml' ),
			/* translators: Name of the translation preference that gives every language the original's value. Verb, imperative: the action, not a duplicate. */
			WPML_COPY_CUSTOM_FIELD      => __( 'Copy', 'acfml' ),
			/* translators: Name of the translation preference that seeds each language from the original and then lets it diverge. Verb phrase, imperative. */
			WPML_COPY_ONCE_CUSTOM_FIELD => __( 'Copy once', 'acfml' ),
			/* translators: Name of the translation preference that sends a field for translation. Verb, imperative: the action, not the noun "translation". */
			WPML_TRANSLATE_CUSTOM_FIELD => __( 'Translate', 'acfml' ),
		];
	}
}
