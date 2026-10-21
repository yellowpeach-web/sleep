<?php

namespace ACFML\FieldGroup;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class SettingsLockHooks implements \IWPML_Action {

	private $fieldNamePatterns;

	public function __construct( FieldNamePatterns $fieldNamePatterns ) {
		$this->fieldNamePatterns = $fieldNamePatterns;
	}

	public function add_hooks() {
		Hooks::onFilter( 'wpml_custom_field_setting_is_html_disabled', 10, 2 )
			->then( spreadArgs( [ $this, 'disableCustomFieldPreference' ] ) );

		Hooks::onFilter( 'wpml_custom_field_settings_override_lock_render', 10, 2 )
			->then( spreadArgs( [ $this, 'renderCustomFieldLock' ] ) );

		Hooks::onAction( 'acf/delete_field_group' )
			->then( spreadArgs( [ $this, 'removeFieldNamePatterns' ] ) );

		Hooks::onAction( 'acf/trash_field_group' )
			->then( spreadArgs( [ $this, 'removeFieldNamePatterns' ] ) );

		Hooks::onAction( 'acf/untrash_field_group' )
			->then( spreadArgs( [ $this, 'restoreFieldNamePatterns' ] ) );
	}

	public function disableCustomFieldPreference( $isDisabled, $cfSetting ) {
		$fieldName = $this->getFieldNameOwningTheSetting( $cfSetting->get_index() );
		$groupKey  = $this->fieldNamePatterns->findMatchingGroup( $fieldName );

		if ( $groupKey ) {
			$fieldGroup = acf_get_field_group( $groupKey );
			if ( false === $fieldGroup ) {
				return $isDisabled;
			}
			return true;
		}

		if ( $this->fieldNamePatterns->findMatchingLocalGroup( $fieldName ) ) {
			return true;
		}

		return $isDisabled;
	}

	public function renderCustomFieldLock( $override, $cfSetting ) {
		$fieldName = $this->getFieldNameOwningTheSetting( $cfSetting->get_index() );
		$groupKey  = $this->fieldNamePatterns->findMatchingGroup( $fieldName );

		if ( $groupKey ) {
			$fieldGroup = acf_get_field_group( $groupKey );
			if ( false === $fieldGroup ) {
				return $override;
			}

			$groupId = Obj::prop( 'ID', $fieldGroup );
			if ( ! $groupId ) {
				return $override;
			}

			$groupTitle = Obj::propOr( $groupKey, 'title', $fieldGroup );

			?>
			<a href="<?php echo esc_url( acf_get_field_group_edit_link( $groupId ) ); ?>" style="text-decoration: none;">
				<button type="button"
						class="button-secondary wpml-button-lock"
						<?php /* translators: %s is the field group title. */ ?>
						title="<?php printf( esc_attr__( 'To change the translation options for custom fields, edit the field group "%s".', 'acfml' ), $groupTitle );  ?>">
					<i class="otgs-ico-lock"></i>
				</button>
			</a>
			<?php

			return true;
		}

		if ( $this->fieldNamePatterns->findMatchingLocalGroup( $fieldName ) ) {
			?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=acf-field-group&post_status=sync' ) ); ?>" style="text-decoration: none;">
				<button type="button"
						class="button-secondary wpml-button-lock"
						title="<?php /* translators: Tooltip of the padlock on fields that come from ACF's local JSON files. "ACF" and "Field Groups" are that plugin's own screen names; the arrow separates the menu item from the screen inside it. */ esc_attr_e( 'These fields come from ACF’s Local JSON files. To change their translation options, go to ACF → Field Groups, sync them, and then edit their settings.', 'acfml' );  ?>">
					<i class="otgs-ico-lock"></i>
				</button>
			</a>
			<?php

			return true;
		}

		if ( $this->fieldNamePatterns->findMatchingLocalGroup( $fieldName, 'php' ) ) {
			?>
			<span class="acfml-field-info">
				<i class="otgs-ico-info-o" title="<?php /* translators: Tooltip of the information icon on a field the theme or a plugin registers in code. */ esc_attr_e( 'This field and its translation setting are registered via PHP by your theme or plugin. Changes made here will override the original configuration.', 'acfml' ); ?>"></i>
			</span>
			<?php

			return true;
		}

		return $override;
	}

	private function getFieldNameOwningTheSetting( $metaKey ) {
		$isSystemField = '_' === substr( $metaKey, 0, 1 ) && ! $this->isFieldName( $metaKey );

		return $isSystemField ? substr( $metaKey, 1 ) : $metaKey;
	}

	private function isFieldName( $metaKey ) {
		return $this->fieldNamePatterns->findMatchingGroup( $metaKey )
			|| $this->fieldNamePatterns->findMatchingLocalGroup( $metaKey )
			|| $this->fieldNamePatterns->findMatchingLocalGroup( $metaKey, 'php' );
	}

	public function removeFieldNamePatterns( $fieldGroup ) {
		$groupKey = self::getGroupKey( $fieldGroup );

		if ( $groupKey ) {
			$this->fieldNamePatterns->removeGroup( $groupKey );
		}
	}

	public function restoreFieldNamePatterns( $fieldGroup ) {
		$groupKey = self::getGroupKey( $fieldGroup );

		if ( $groupKey ) {
			$this->fieldNamePatterns->updateFieldNamePatterns( Obj::assoc( 'key', $groupKey, $fieldGroup ) );
		}
	}

	private static function getGroupKey( $fieldGroup ) {
		$groupKey = Obj::prop( 'key', $fieldGroup );

		return is_string( $groupKey ) && '' !== $groupKey
			? str_replace( '__trashed', '', $groupKey )
			: null;
	}

}
