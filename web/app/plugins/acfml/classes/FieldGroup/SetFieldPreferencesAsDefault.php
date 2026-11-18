<?php

namespace ACFML\FieldGroup;

use ACFML\FieldPreferences\ModeWrites;
use ACFML\FieldPreferences\SubfieldRules;
use ACFML\Helper\Fields;
use WPML\FP\Fns;

class SetFieldPreferencesAsDefault implements \IWPML_REST_Action {

	const INDEX_POST = 'custom_fields_translation';
	const INDEX_TERM = 'custom_term_fields_translation';

	const PRIORITY_AFTER_GROUP_MODE = SetSameFieldsModeAsDefault::PRIORITY_HOOKS + 1;

	private $fieldNamePatterns;

	private $tm;

	public function __construct( FieldNamePatterns $fieldNamePatterns, \TranslationManagement $tm ) {
		$this->fieldNamePatterns = $fieldNamePatterns;
		$this->tm                = $tm;
	}

	public function add_hooks() {
		add_action( 'wpml_setup_completed', [ $this, 'run' ], self::PRIORITY_AFTER_GROUP_MODE );
		add_action( 'wpml_set_translate_everything', [ $this, 'onTeaToggled' ], self::PRIORITY_AFTER_GROUP_MODE );
	}

	public function onTeaToggled( $enabled ) {
		if ( $enabled ) {
			$this->run();
		}
	}

	public function run() {
		$preferenceByPattern = $this->buildPreferenceByPattern();
		if ( ! $preferenceByPattern ) {
			return;
		}

		$this->tm->load_settings_if_required();

		$factory          = $this->tm->settings_factory();
		$isCoveredByRules = $this->buildIsCoveredBySubfieldRules();

		ModeWrites::save(
			$this->tm,
			[
				self::INDEX_POST => $this->backfill( $factory->get_post_meta_keys(), self::INDEX_POST, $preferenceByPattern, $isCoveredByRules ),
				self::INDEX_TERM => $this->backfill( $factory->get_term_meta_keys(), self::INDEX_TERM, $preferenceByPattern, $isCoveredByRules ),
			]
		);
	}

	private function backfill( $metaKeys, $index, array $preferenceByPattern, callable $isCoveredByRules ) {
		$stored      = $this->tm->settings[ $index ] ?? [];
		$modes       = [];
		$elementType = self::INDEX_TERM === $index ? 'term' : 'post';

		foreach ( (array) $metaKeys as $metaKey ) {
			if ( $isCoveredByRules( $metaKey, $elementType ) ) {
				continue;
			}

			$preference = $this->fieldNamePatterns->findMatchingValue( $metaKey, $preferenceByPattern );
			if ( null === $preference ) {
				continue;
			}
			$modes += [
				$metaKey       => $preference['field'],
				'_' . $metaKey => $preference['companion'],
			];
		}

		return array_diff_key( $modes, (array) $stored );
	}

	private function buildIsCoveredBySubfieldRules() {
		$rules = SubfieldRules::isCoreResolutionAvailable() ? SubfieldRules::buildRules() : null;
		if ( ! $rules ) {
			return Fns::always( false );
		}

		$definitionNames = SubfieldRules::definitionNames();

		return function ( $metaKey, $elementType ) use ( $rules, $definitionNames ) {
			return ! SubfieldRules::isDefinitionKey( $metaKey, $definitionNames )
				&& null !== SubfieldRules::resolveWithRules( $rules, $metaKey, $elementType );
		};
	}

	private function buildPreferenceByPattern() {
		$preferenceByPattern = [];

		foreach ( acf_get_field_groups() as $fieldGroup ) {
			$mode = $this->effectiveMode( $fieldGroup );
			if ( ! $mode ) {
				continue;
			}

			$getFieldPreference = ModeDefaults::get( $mode );
			$companion          = Mode::LOCALIZATION === $mode ? WPML_COPY_ONCE_CUSTOM_FIELD : WPML_COPY_CUSTOM_FIELD;

			$collect = function( $field, $fieldPattern ) use ( &$preferenceByPattern, $getFieldPreference, $companion ) {
				if ( ModeValidity::storesNoValue( $field ) ) {
					return $field;
				}

				$preferenceByPattern[ $fieldPattern ] = [
					'field'     => (int) $getFieldPreference( $field ),
					'companion' => $companion,
				];

				return $field;
			};

			Fields::iterate( acf_get_fields( $fieldGroup ), $collect, Fns::identity() );
		}

		return $preferenceByPattern;
	}

	private function effectiveMode( $fieldGroup ) {
		$mode = Mode::getMode( $fieldGroup );

		if ( ! $mode ) {
			return Mode::TRANSLATION;
		}

		return Mode::ADVANCED === $mode ? null : $mode;
	}
}
