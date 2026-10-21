<?php

namespace ACFML;

class MigrateBlockPreferences implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {
	const OPTION_KEY     = 'acfml_block_migration_result';
	const CHUNK_SIZE     = 10;
	const MIGRATED_VALUE = 'done';

	private $fieldSettings;

	public function __construct( \WPML_ACF_Field_Settings $fieldSettings ) {
		$this->fieldSettings = $fieldSettings;
	}

	public function add_hooks() {
		add_action( 'init', [ $this, 'runMigration' ] );
		add_action( 'plugins_loaded', [ $this, 'showProgressInfo' ] );
	}

	public function runMigration() {
		if ( $this->migrationShouldRun() ) {
			$offset = $this->getOffset();
			if ( $this->migrate( $offset ) ) {
				\update_option( self::OPTION_KEY, $offset + self::CHUNK_SIZE );
			} else {
				\update_option( self::OPTION_KEY, self::MIGRATED_VALUE );
			}
		}
	}

	public function showProgressInfo() {
		if ( $this->migrationShouldRun() ) {
			add_action( 'admin_notices', [ $this, 'blocksBeingUpdated' ] );
		}
	}

	public function blocksBeingUpdated() {
		?>
		<div class="notice notice-info">
			<p>
				<?php
				/* translators: Admin notice shown while the plugin updates preferences in the background. "ACFML" is the plugin's short name and "Gutenberg" is the WordPress editor's name; both stay in English. */
				_e(
					'ACFML is updating translation preferences for strings in Gutenberg Blocks. Keep using your site as usual. This notice will disappear when the process is done.',
					'acfml'
				);
				?>
			</p>
		</div>
		<?php
	}

	private function migrate( $offset = 0 ) {
		$fieldGroups = $this->getOnlyBlockLocatedGroups( (array) $this->getAllFieldGroups( $offset ) );
		if ( count( $fieldGroups ) > 0 ) {
			foreach ( $fieldGroups as $group ) {
				$this->migrateChildren( $group->ID );
			}

			return true;
		} else {
			return false;
		}
	}

	private function migrateChildren( $parentId ) {
		foreach ( $this->getFieldsOfGroup( $parentId ) as $field ) {
			$fieldObject = acf_get_field( $field->post_name );
			if ( $this->fieldSettings->fieldPreferencesNotMigrated( $fieldObject ) ) {
				if ( $this->fieldSettings->field_should_be_set_to_copy_once( $fieldObject ) ) {
					$this->setFieldTranslationPreference( $fieldObject, WPML_COPY_CUSTOM_FIELD );
					$this->migrateChildren( $fieldObject['ID'] );
				} else {
					$this->setFieldTranslationPreference( $fieldObject, WPML_TRANSLATE_CUSTOM_FIELD );
				}
			}
		}
	}

	private function setFieldTranslationPreference( $fieldObject, $preference ) {
		$fieldObject['wpml_cf_preferences'] = $preference;
		$this->fieldSettings->update_field_settings( $fieldObject );
		$this->fieldSettings->update_field_group_post( $fieldObject['ID'], $preference );
	}

	private function getAllFieldGroups( $offset ) {
		return get_posts(
			[
				'numberposts' => self::CHUNK_SIZE,
				'offset'      => $offset,
				'post_type'   => 'acf-field-group',
			]
		);
	}

	private function getOnlyBlockLocatedGroups( array $fieldGroups ) {
		$blockLocatedGroups = [];
		foreach ( $fieldGroups as $fieldGroup ) {
			if ( $this->hasBlockInDisplayRules( maybe_unserialize( $fieldGroup->post_content ) ) ) {
				$blockLocatedGroups[] = $fieldGroup;
			}
		}

		return $blockLocatedGroups;
	}

	private function getFieldsOfGroup( $parentId ) {
		return get_posts(
			[
				'numberposts' => -1,
				'post_type'   => 'acf-field',
				'post_parent' => $parentId,
			]
		);
	}

	private function migrationShouldRun() {
		return self::MIGRATED_VALUE !== \get_option( self::OPTION_KEY );
	}

	private function getOffset() {
		return (int) \get_option( self::OPTION_KEY );
	}

	private function hasBlockInDisplayRules( $fieldGroup ) {
		if ( isset( $fieldGroup['location'] ) && is_array( $fieldGroup['location'] ) ) {
			foreach ( $fieldGroup['location'] as $group ) {
				if ( empty( $group ) || ! is_array( $group ) ) {
					continue;
				}
				foreach ( $group as $rule ) {
					if ( isset( $rule['param'] ) && $rule['param'] === 'block' ) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
