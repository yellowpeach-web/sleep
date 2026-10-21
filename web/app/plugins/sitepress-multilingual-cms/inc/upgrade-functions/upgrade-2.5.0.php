<?php
  $iclsettings                                 = get_option( 'icl_sitepress_settings' );
  $iclsettings['translated_document_page_url'] = 'auto-generate';
  $iclsettings['sync_comments_on_duplicates']  = 0;
  update_option( 'icl_sitepress_settings', $iclsettings );

	  global $wpdb;
	  $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_languages ADD COLUMN encode_url TINYINT(1) NOT NULL DEFAULT 0" );

	  $wpdb->query( "UPDATE {$wpdb->prefix}icl_languages SET encode_url = 1 WHERE code IN ('ru','uk','zh-hans','zh-hant','ja','ko','vi','th','he','ar','el','fa')" );
