<?php

namespace WPML\TranslationPolicy\Settings;

use WPML\TranslationPolicy\FeatureSwitch;

class Loader implements \IWPML_Backend_Action_Loader {

	const SEARCH_SECTION = 'translation-editor';

	public function create() {
		if ( ! self::isSaveRequest() ) {
			return null;
		}

		return FeatureSwitch::isOn() ? new SaveEndpoint( [ Section::class, 'saveSettings' ], [ Section::class, 'stateAfterSave' ] ) : null;
	}

	private static function isSaveRequest(): bool {
		$action = (string) filter_input( INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		return wp_doing_ajax() && SaveEndpoint::ACTION === $action;
	}

	public static function section() {
		return FeatureSwitch::isOn() ? Section::create() : null;
	}

	public static function setupWizardData() {
		return FeatureSwitch::isOn() ? [ 'translateSomeMethod' => SetupChoice::translateSomeMethod() ] : null;
	}

	public static function afterSetupWizard( bool $translateEverything ) {
		if ( FeatureSwitch::isOn() ) {
			SetupChoice::create()->write( $translateEverything );
		}
	}

	public static function searchIndex( array $index ): array {
		if ( ! FeatureSwitch::isOn() ) {
			return $index;
		}

		foreach ( $index as $i => $row ) {
			if ( is_array( $row ) && isset( $row['href'] ) && is_string( $row['href'] ) && false !== strpos( $row['href'], 'section=' . self::SEARCH_SECTION ) ) {
				$index[ $i ]['section']     = Words::title();
				$index[ $i ]['description'] = Words::searchDescription();
				$index[ $i ]['subs']        = array_merge(
					self::withoutOldJobsCheckbox( isset( $row['subs'] ) && is_array( $row['subs'] ) ? $row['subs'] : [] ),
					[
						[
							'label'  => Words::methodStep(),
							'anchor' => Section::SECTION_ID,
						],
						[
							'label'  => Words::typesStep(),
							'anchor' => Section::SECTION_ID,
						],
					]
				);
			}
		}

		return $index;
	}

	private static function withoutOldJobsCheckbox( array $subs ): array {
		return array_values(
			array_filter(
				$subs,
				fn( $sub ) => ! ( is_array( $sub ) && isset( $sub['target'] ) && \WPML_TM_Old_Jobs_Editor::OPTION_NAME === $sub['target'] )
			)
		);
	}
}
