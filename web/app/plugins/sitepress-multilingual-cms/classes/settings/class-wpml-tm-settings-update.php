<?php

use WPML\FP\Obj;
use WPML\TM\Settings\TranslationModeTransition;

class WPML_TM_Settings_Update extends WPML_SP_User {

	private $index_singular;
	private $index_ro;
	private $index_sync;
	private $index_plural;
	private $index_unlocked;
	private $tm_instance;
	private $settings_helper;

	public function __construct( $index_singular, $index_plural, &$tm_instance, &$sitepress, $settings_helper ) {
		parent::__construct( $sitepress );
		$this->tm_instance     = &$tm_instance;
		$this->index_singular  = $index_singular;
		$this->index_plural    = $index_plural;
		$this->index_ro        = $index_plural . '_readonly_config';
		$this->index_sync      = 'custom-type' === $index_singular ? 'custom_posts_sync_option' : $index_plural . '_sync_option';
		$this->index_unlocked  = 'custom-type' == $index_singular ? 'custom_posts_unlocked_option' : 'taxonomies_unlocked_option';
		$this->settings_helper = $settings_helper;
	}

	public function update_from_config( array $config ) {
		$this->update_tm_settings( Obj::propOr( [], $this->index_plural, $config ) );
	}

	private function sync_settings( array $config ) {
		$section_singular = $this->index_singular;
		$section_plural   = $this->index_plural;

		if ( ! empty( $config[ $section_singular ] ) ) {
			$sync_option     = $this->sitepress->get_setting( $this->index_sync, [] );
			$unlocked_option = $this->sitepress->get_setting( $this->index_unlocked, [] );
			if ( ! is_numeric( key( current( $config ) ) ) ) {
				$cf[0] = $config[ $section_singular ];
			} else {
				$cf = $config[ $section_singular ];
			}
			foreach ( $cf as $c ) {
				$val = $c['value'];

				if ( ! $this->is_unlocked_type( $val, $unlocked_option ) ) {

					$sync_existing_setting                                  = isset( $sync_option[ $val ] ) ? $sync_option[ $val ] : ( 'custom-types' === $section_plural ? WPML_CONTENT_TYPE_DONT_TRANSLATE : false );
					$sync_new_setting                                       = (int) $c['attr']['translate'];
					$this->tm_instance->settings[ $this->index_ro ][ $val ] = $sync_new_setting;
					$sync_option[ $val ]                                    = $sync_new_setting;

					if ( TranslationModeTransition::isBecomingTranslatable( $sync_new_setting, $sync_existing_setting ) ) {
						$this->tm_instance->save_settings();
						if ( $section_plural === 'taxonomies' ) {
							$this->settings_helper->set_taxonomy_translatable_mode( $val, $sync_new_setting );
						} else {
							$this->sitepress->verify_post_translations( $val );
						}
					} elseif (
						$section_plural === 'taxonomies'
						&& TranslationModeTransition::isBecomingNotTranslatable( $sync_new_setting, $sync_existing_setting )
					) {
						$this->tm_instance->save_settings();
						$this->settings_helper->set_taxonomy_translatable_mode( $val, $sync_new_setting );
					}
				}
			}

			$this->sitepress->set_setting( $this->index_sync, $sync_option );
			$this->settings_helper->maybe_add_filter( $section_plural );
		}
	}

	private function update_tm_settings( array $config ) {
		$section_singular            = $this->index_singular;
		$config                      = array_filter( $config );
		$config[ $section_singular ] = Obj::propOr( [], $section_singular, $config );
		$this->sync_settings( $config );

		if ( ! empty( $this->tm_instance->settings[ $this->index_ro ] ) ) {
			$config_values = [];
			foreach ( $config[ $section_singular ] as $config_value ) {
				$config_values[ $config_value['value'] ] = $config_value['attr']['translate'];
			}
			foreach ( $this->tm_instance->settings[ $this->index_ro ] as $key => $translation_option ) {
				if ( ! isset( $config_values[ $key ] ) ) {
					unset( $this->tm_instance->settings[ $this->index_ro ][ $key ] );
				}
			}

			$this->tm_instance->save_settings();
		}
	}

	private function is_unlocked_type( $type, $unlocked_options ) {
		return isset( $unlocked_options[ $type ] ) && $unlocked_options[ $type ];
	}
}
