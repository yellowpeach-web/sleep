<?php

global $wpdb;

$current_table = 'icl_languages';
$result        = $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_languages MODIFY default_locale varchar(35), MODIFY tag varchar(35);" );
if ( false !== $result ) {
	$current_table = 'icl_locale_map';
	$result        = $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_locale_map MODIFY locale varchar(35);" );
}

function update_seo_settings() {
	global $wpdb;

	$data               = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s;", array( 'icl_sitepress_settings' ) ) );
	$sitepress_settings = unserialize( $data, array( 'allowed_classes' => array( 'stdClass', 'WPML_TP_Service' ) ) );

	$settings_updated = false;
	if ( ! array_key_exists( 'seo', $sitepress_settings ) ) {
		$sitepress_settings['seo'] = array();
	}

	if ( ! array_key_exists( 'head_langs', $sitepress_settings['seo'] ) ) {
		$sitepress_settings['head_langs'] = 1;
		$settings_updated                 = true;
	}

	if ( ! array_key_exists( 'canonicalization_duplicates', $sitepress_settings['seo'] ) ) {
		$sitepress_settings['canonicalization_duplicates'] = 1;
		$settings_updated                                  = true;
	}

	if ( ! array_key_exists( 'head_langs_priority', $sitepress_settings['seo'] ) ) {
		$sitepress_settings['head_langs_priority'] = 1;
		$settings_updated                          = true;
	}

	if ( $settings_updated ) {
		$data = serialize( $sitepress_settings );
		$wpdb->update( $wpdb->options, array( 'option_value' => $data ), array( 'option_name' => 'icl_sitepress_settings' ) );
	}
}

update_seo_settings();

if ( false == $result ) {
	throw new Exception( 'Error upgrading schema for table "' . $current_table . '": ' . $wpdb->last_error );
}
