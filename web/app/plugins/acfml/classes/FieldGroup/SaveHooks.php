<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\Fields;
use ACFML\Notice\FieldNameCollisions;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use WPML\Element\API\Languages;
use WPML\TM\Settings\CustomFieldChangeDetector;
use function WPML\FP\spreadArgs;

class SaveHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const UPDATE_FIELD_GROUP_PRIORITY = 9;

	const PREFERENCE_CHANGES_FILTER = 'acfml_field_group_preference_changes';

	const POSTED_CHANGES_INPUT = 'acfml_preference_changes';

	const APPLY_TO_EXISTING_INPUT = 'acfml_apply_preference_changes_to_existing';

	private $fieldNamePatterns;

	private $detectNonTranslatableLoc;

	private $preferenceChanges = [];

	private $fieldNameCollisions;

	public function __construct(
		FieldNamePatterns $fieldNamePatterns,
		DetectNonTranslatableLocations $detectNonTranslatableLoc,
		FieldNameCollisions $fieldNameCollisions
	) {
		$this->fieldNamePatterns        = $fieldNamePatterns;
		$this->detectNonTranslatableLoc = $detectNonTranslatableLoc;
		$this->fieldNameCollisions      = $fieldNameCollisions;
	}

	public function add_hooks() {
		Hooks::onAction( 'acf/update_field_group', self::UPDATE_FIELD_GROUP_PRIORITY )
			->then( spreadArgs( [ $this, 'onUpdateFieldGroup' ] ) );

		add_filter( self::PREFERENCE_CHANGES_FILTER, [ $this, 'getPreferenceChanges' ] );
	}

	public function getPreferenceChanges() {
		return $this->preferenceChanges;
	}

	public function onUpdateFieldGroup( $fieldGroup ) {
		$this->overwriteAllFieldPreferencesWithGroupMode( $fieldGroup );
		$this->fieldNamePatterns->updateFieldNamePatterns( $fieldGroup );
		$this->detectNonTranslatableLoc->process( $fieldGroup );
		$this->fieldNameCollisions->process( $fieldGroup );
		$this->maybeForceTranslationStatusProcessOnAttachedPosts();
		$this->flushAcfCache( $fieldGroup );
	}

	private function overwriteAllFieldPreferencesWithGroupMode( $fieldGroup ) {
		$this->preferenceChanges = [];

		if ( Mode::isAdvanced( $fieldGroup ) ) {
			return;
		}

		$getFieldPreference = ModeDefaults::get( Mode::getMode( $fieldGroup ) );

		$updateFieldTranslationPreference = Fns::tap(
			function ( $field ) use ( $getFieldPreference ) {
				if ( ModeValidity::storesNoValue( $field ) ) {
					return;
				}

				$preference = $getFieldPreference( $field );

				$this->collectPreferenceChange( $field, $preference );

				acf_update_field( Obj::assoc( 'wpml_cf_preferences', $preference, wp_slash( $field ) ) );
			}
		);

		$updateOwnFieldTranslationPreference = function ( $field ) use ( $updateFieldTranslationPreference ) {
			if ( self::isShownBySeamlessClone( $field ) ) {
				return self::withoutChildFields( $field );
			}

			return self::withoutCloneSubFields( $updateFieldTranslationPreference( $field ) );
		};

		Fields::iterate( Fields::getFresh( $fieldGroup ), $updateOwnFieldTranslationPreference, Fns::identity() );
	}

	private static function isShownBySeamlessClone( array $field ): bool {
		return ! empty( $field['_clone'] );
	}

	private static function withoutCloneSubFields( array $field ): array {
		if ( 'clone' === Obj::prop( 'type', $field ) ) {
			unset( $field['sub_fields'] );
		}

		return $field;
	}

	private static function withoutChildFields( array $field ): array {
		unset( $field['sub_fields'], $field['layouts'] );

		return $field;
	}

	private function collectPreferenceChange( $field, $preference ) {
		$stored = Obj::prop( 'wpml_cf_preferences', $field );

		if ( null !== $stored && (int) $stored === (int) $preference ) {
			return;
		}

		$this->preferenceChanges[] = [
			'name' => (string) Obj::propOr( '', 'name', $field ),
			'key'  => (string) Obj::propOr( '', 'key', $field ),
			'type' => (string) Obj::propOr( '', 'type', $field ),
			'from' => null === $stored ? null : (int) $stored,
			'to'   => (int) $preference,
		];
	}

	private function maybeForceTranslationStatusProcessOnAttachedPosts() {
		$isConfirmed = isset( $_POST['acfml_force_translation_status_process'] );

		$postedApplyToExisting = isset( $_POST[ self::APPLY_TO_EXISTING_INPUT ] )
			? intval( wp_unslash( $_POST[ self::APPLY_TO_EXISTING_INPUT ] ) )
			: 0;

		$applyToExisting = 1 === $postedApplyToExisting;

		if ( ! $isConfirmed && ! $applyToExisting ) {
			return;
		}

		$changes = $this->getConfirmedChanges();

		if ( ! $changes ) {
			return;
		}

		$service = PreferenceReapplyService::get();

		if ( $service && method_exists( $service, 'schedule' ) ) {
			$service->schedule( $changes, $applyToExisting );

			return;
		}

		if ( $isConfirmed ) {
			CustomFieldChangeDetector::notify( array_column( $changes, 'field' ) );
		}
	}

	private function getConfirmedChanges() {
		$changes = [];

		foreach ( $this->readPostedChanges() as $change ) {
			$changes[ $change['field'] ] = $change;
		}

		foreach ( $this->getPreferenceChanges() as $change ) {
			if ( '' === $change['name'] ) {
				continue;
			}

			$changes[ $change['name'] ] = [
				'field'   => $change['name'],
				'oldPref' => $change['from'],
				'newPref' => $change['to'],
			];
		}

		return array_values( $changes );
	}

	private function readPostedChanges() {
		if ( ! isset( $_POST[ self::POSTED_CHANGES_INPUT ] ) ) {
			return [];
		}

		$posted = json_decode( wp_unslash( (string) $_POST[ self::POSTED_CHANGES_INPUT ] ), true );

		if ( ! is_array( $posted ) ) {
			return [];
		}

		$changes = [];

		foreach ( $posted as $change ) {
			if ( ! is_array( $change ) || empty( $change['name'] ) || ! isset( $change['to'] ) ) {
				continue;
			}

			$changes[] = [
				'field'   => sanitize_text_field( (string) $change['name'] ),
				'oldPref' => isset( $change['from'] ) && '' !== $change['from'] ? (int) $change['from'] : null,
				'newPref' => (int) $change['to'],
			];
		}

		return $changes;
	}

	private function flushAcfCache( $fieldGroup ) {
		$active_languages = Languages::getActive();

		wp_cache_delete( 'acf_get_field_group_posts', 'acf' );
		wp_cache_delete( 'acf_get_field_group_post:key:' . $fieldGroup['key'], 'acf' );
		wp_cache_delete( 'acf_get_field_posts:' . $fieldGroup['ID'], 'acf' );
		if ( ! empty( $active_languages ) ) {
			foreach ( $active_languages as $lang_code => $language ) {
				wp_cache_delete( 'acf_get_field_posts:' . $fieldGroup['ID'] . ':' . $lang_code, 'acf' );
			}
		}
	}
}
