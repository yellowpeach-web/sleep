<?php

class WPML_Verify_SitePress_Settings {

	const RETIRED_LOGGED_KEYS = 'settings_verifier_logged_keys';

	const DERIVED_WHEN_LOST = [ 'existing_content_language_verified' ];

	private $wp_api;

	private $stored_copy;

	public function __construct( WPML_WP_API $wp_api, $stored_copy = null ) {
		$this->wp_api      = $wp_api;
		$this->stored_copy = $stored_copy;
	}

	public function verify( $settings ) {
		$default_settings = [
			'interview_translators'              => 1,
			'existing_content_language_verified' => empty( $settings['setup_complete'] ) ? 0 : 1,
			'language_negotiation_type'          => WPML_URL_Format::DEFAULT_FORMAT,
			'icl_lso_link_empty'                 => 0,
			'sync_page_ordering'                 => 1,
			'sync_page_parent'                   => 1,
			'sync_page_template'                 => 1,
			'sync_ping_status'                   => 1,
			'sync_comment_status'                => 1,
			'sync_sticky_flag'                   => 1,
			'sync_password'                      => 1,
			'sync_private_flag'                  => 1,
			'sync_post_format'                   => 1,
			'sync_delete'                        => 1,
			'sync_delete_tax'                    => 0,
			'sync_post_taxonomies'               => 1,
			'sync_post_date'                     => 1,
			'sync_taxonomy_parents'              => 0,
			'translation_pickup_method'          => 0,
			'notify_complete'                    => 1,
			'translated_document_status'         => 1,
			'translated_document_status_sync'    => 0,
			'remote_management'                  => 0,
			'auto_adjust_ids'                    => 1,
			'alert_delay'                        => 0,
			'promote_wpml'                       => 0,
			'automatic_redirect'                 => 0,
			'remember_language'                  => 24,
			'icl_lang_sel_copy_parameters'       => '',
			'translated_document_page_url'       => 'auto-generate',
			'sync_comments_on_duplicates'        => 0,
			'seo'                                => [
				'head_langs'                  => 1,
				'canonicalization_duplicates' => 1,
				'head_langs_priority'         => 1,
			],
			'posts_slug_translation'             => [
				'on' => 1,
			],
			'languages_order'                    => [],
			'urls'                               => [
				'directory_for_default_language' => 0,
				'show_on_root'                   => '',
				'root_html_file_path'            => '',
				'root_page'                      => 0,
				'hide_language_switchers'        => 1,
			],
			'xdomain_data'                       => $this->wp_api->constant( 'WPML_XDOMAIN_DATA_GET' ),
			'custom_posts_sync_option'           => [
				'post' => WPML_CONTENT_TYPE_TRANSLATE,
				'page' => WPML_CONTENT_TYPE_TRANSLATE,
			],
			'taxonomies_sync_option'             => [
				'category' => WPML_CONTENT_TYPE_TRANSLATE,
				'post_tag' => WPML_CONTENT_TYPE_TRANSLATE,
			],
			'tm_block_retranslating_terms'       => 1,
		];

		$left_missing    = $this->leftMissingKeys( $settings, array_keys( $default_settings ) );
		$update_settings = false;
		if ( is_array( $settings ) && array_key_exists( self::RETIRED_LOGGED_KEYS, $settings ) ) {
			unset( $settings[ self::RETIRED_LOGGED_KEYS ] );
			$update_settings = true;
		}

		$is_fresh_install = ! isset( $settings['sync_delete'] )
			&& ! isset( $settings['sync_delete_tax'] )
			&& ! isset( $settings[ \WPML\ContentDeletion\Settings::KEY ] );

		foreach ( $default_settings as $key => $value ) {
			if ( isset( $left_missing[ $key ] ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				foreach ( $value as $k2 => $v2 ) {
					if ( is_array( $v2 ) ) {
						foreach ( $v2 as $k3 => $v3 ) {
							if ( ! isset( $settings[ $key ][ $k2 ][ $k3 ] ) ) {
								$settings[ $key ][ $k2 ][ $k3 ] = $v3;
								$update_settings                = true;
							}
						}
					} else {
						if ( ! isset( $settings[ $key ][ $k2 ] ) ) {
							$settings[ $key ][ $k2 ] = $v2;
							$update_settings         = true;
						}
					}
				}
			} else {
				if ( ! isset( $settings[ $key ] ) ) {
					$settings[ $key ] = $value;
					$update_settings  = true;
				}
			}
		}

		if ( $is_fresh_install ) {
			$settings[ \WPML\ContentDeletion\Settings::KEY ] = self::seedContentDeletion();
			$settings['sync_delete']                         = 0;
			$settings['sync_delete_tax']                     = 0;
			$update_settings                                 = true;
		}

		return [ $settings, $update_settings ];

	}

	private function leftMissingKeys( $settings, array $default_keys ) {
		if ( null === $this->stored_copy || ! is_array( $settings ) ) {
			return [];
		}

		$left_missing = [];
		foreach ( array_intersect( $this->stored_copy->registeredKeys(), $default_keys ) as $key ) {
			if ( ! array_key_exists( $key, $settings ) && ! in_array( $key, self::DERIVED_WHEN_LOST, true ) ) {
				$left_missing[ $key ] = true;
			}
		}

		return $left_missing;
	}

	private static function seedContentDeletion() {
		$keys = [
			\WPML\ContentDeletion\Settings::postKey( 'post' ),
			\WPML\ContentDeletion\Settings::postKey( 'page' ),
			\WPML\ContentDeletion\Settings::postKey( 'attachment' ),
			\WPML\ContentDeletion\Settings::termKey( 'category' ),
			\WPML\ContentDeletion\Settings::termKey( 'post_tag' ),
			\WPML\ContentDeletion\Settings::termKey( 'nav_menu' ),
		];

		$originals    = [];
		$translations = [];
		foreach ( $keys as $key ) {
			$originals[ $key ]    = \WPML\ContentDeletion\Settings::ASK;
			$translations[ $key ] = \WPML\ContentDeletion\Settings::ONLY;
		}

		return [
			\WPML\ContentDeletion\Settings::ORIGINALS    => $originals,
			\WPML\ContentDeletion\Settings::TRANSLATIONS => $translations,
		];
	}


}
