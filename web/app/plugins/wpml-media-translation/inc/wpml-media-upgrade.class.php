<?php

class WPML_Media_Upgrade {
	private static $versions = array(
		'2.0',
		'2.0.1',
	);

	static function run() {
		global $wpdb;

		$wpml_media_settings          = $wpdb->get_col( $wpdb->prepare( "select option_value from {$wpdb->prefix}options where option_name = %s", '_wpml_media' ) );

		$is_existing_install = (bool) ( $wpml_media_settings || get_option( '_wpml_media_starting_help' ) );

		$needs_version_update = true;

		if ( $is_existing_install ) {
			$current_version = WPML_Media::get_setting( 'version', null );

			if ( $current_version ) {
				$needs_version_update = version_compare( $current_version, WPML_MEDIA_VERSION, '<' );
				self::run_upgrades_before_2_3_0( $current_version );
			} elseif ( self::is_media_version_older_than_2_0() ) {
				$needs_version_update = true;
				self::run_upgrades_before_2_3_0( '1.6' );
			}
		}

		if ( $needs_version_update ) {
			if ( $is_existing_install ) {
				icl_cache_clear( '_media_upload_attachments', true );
			}

			WPML_Media::update_setting( 'version', WPML_MEDIA_VERSION );
		}
	}

	const PRIOR_2_3_0_STAMP = 'wpml_media_upgraded_from_prior_2_3_0';

	private static function run_upgrades_before_2_3_0( $current_version ) {
		if ( version_compare( $current_version, '2.3.0', '<' ) ) {

			if ( get_option( self::PRIOR_2_3_0_STAMP ) ) {
				return;
			}

			update_option( self::PRIOR_2_3_0_STAMP, 1 );

			foreach ( self::$versions as $version ) {
				if ( version_compare( $version, WPML_MEDIA_VERSION, '<=' ) && version_compare( $version, $current_version, '>' ) ) {

					$upgrade_method = 'upgrade_' . str_replace( '.', '_', $version );
					if ( method_exists( __CLASS__, $upgrade_method ) ) {
						self::$upgrade_method();
					}
				}
			}
		}
	}

	private static function is_media_version_older_than_2_0() {
		global $wpdb;
		return (bool) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'wpml_media_duplicate_of'" );
	}

	private static function upgrade_2_0() {
		global $wpdb;
		global $sitepress;

		$old_starting_help = get_option( '_wpml_media_starting_help' );
		if ( $old_starting_help ) {
			WPML_Media::update_setting( 'starting_help', $old_starting_help );
			delete_option( '_wpml_media_starting_help' );
		}


		$target_language = $sitepress->get_default_language();
		$attachment_ids  = $wpdb->get_col(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'attachment' )
		);

		foreach ( $attachment_ids as $attachment_id ) {
			$wpml_media_lang         = get_post_meta( $attachment_id, 'wpml_media_lang', true );
			$wpml_media_duplicate_of = get_post_meta( $attachment_id, 'wpml_media_duplicate_of', true );

			if ( ! $wpml_media_duplicate_of && ( ! $wpml_media_lang || $wpml_media_lang == $target_language ) ) {
				$trid = $sitepress->get_element_trid( $attachment_id, 'post_attachment' );
				if ( $trid ) {
					$target_language = $sitepress->get_language_for_element( $attachment_id, 'post_attachment' );
				}

				$sitepress->set_element_language_details( $attachment_id, 'post_attachment', $trid, $target_language );
			}
		}

		foreach ( $attachment_ids as $attachment_id ) {
			$wpml_media_lang         = get_post_meta( $attachment_id, 'wpml_media_lang', true );
			$wpml_media_duplicate_of = get_post_meta( $attachment_id, 'wpml_media_duplicate_of', true );

			if ( $wpml_media_duplicate_of ) {
				$source_language = null;
				$trid            = $sitepress->get_element_trid( $wpml_media_duplicate_of, 'post_attachment' );
				$source_language = false;
				if ( $trid ) {
					$source_language = $sitepress->get_language_for_element( $wpml_media_duplicate_of, 'post_attachment' );

					if ( $wpml_media_lang == $source_language ) {
						$wpml_media_lang = false;
						$attachment      = get_post( $attachment_id );
						if ( $attachment->post_parent ) {
							$parent_post          = get_post( $attachment->post_parent );
							$post_parent_language = $sitepress->get_language_for_element( $parent_post->ID, 'post_' . $parent_post->post_type );
							if ( $post_parent_language ) {
								$wpml_media_lang = $post_parent_language;
							}
						}

						if ( ! $wpml_media_lang ) {
							wp_delete_attachment( $attachment_id );
						}
					}
				}

				if ( $wpml_media_lang ) {
					$sitepress->set_element_language_details( $attachment_id, 'post_attachment', $trid, $wpml_media_lang, $target_language, $source_language );
				}
			}
		}

		$attachment_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'attachment' )
		);
		foreach ( $attachment_ids as $attachment_id ) {
			delete_post_meta( $attachment_id, 'wpml_media_duplicate_of' );
			delete_post_meta( $attachment_id, 'wpml_media_lang' );
		}

	}

	private static function upgrade_2_0_1() {
		global $wpdb;
		global $sitepress;


		$original_attachments = $wpdb->get_results( $wpdb->prepare( "
				SELECT t.element_id, t.trid, t.language_code
				FROM {$wpdb->prefix}icl_translations t
				  LEFT JOIN {$wpdb->postmeta} pm
				  ON t.element_id = pm.post_id AND pm.meta_key=%s
				WHERE t.element_type = %s AND pm.meta_id IS NULL AND element_id IS NOT NULL
				", array( '_wp_attachment_metadata', 'post_attachment' ) ) );

		foreach ( $original_attachments as $original_attachment ) {
			$attachment_metadata = get_post_meta( $original_attachment->element_id, '_wp_attachment_metadata', true );
			if ( ! $attachment_metadata ) {
				$attachment_translations = $sitepress->get_element_translations( $original_attachment->trid, 'post_attachment', true, true );
				foreach ( $attachment_translations as $attachment_translation ) {
					if ( $attachment_translation->language_code != $original_attachment->language_code ) {
						$attachment_metadata = get_post_meta( $attachment_translation->element_id, '_wp_attachment_metadata', true );
						if ( $attachment_metadata ) {
							update_post_meta( $original_attachment->element_id, '_wp_attachment_metadata', $attachment_metadata );
							break;
						}
					}
				}
			}
		}

		return true;
	}

}
