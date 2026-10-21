<?php
global $wpdb;

$wpdb->query( "ALTER TABLE  `{$wpdb->prefix}icl_translations` CHANGE  `element_type`  `element_type` VARCHAR( 36 ) NOT NULL DEFAULT 'post_post'" );

$iclsettings                      = get_option( 'icl_sitepress_settings' );
$iclsettings['seo']['head_langs'] = 1;
update_option( 'icl_sitepress_settings', $iclsettings );
