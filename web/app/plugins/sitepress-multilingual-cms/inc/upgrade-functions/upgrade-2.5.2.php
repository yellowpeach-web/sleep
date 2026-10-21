<?php
  global $wpdb;

	  $eoFlag = wpml_get_flag_file_name('eo');
	  $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_flags SET flag = %s WHERE lang_code = 'eo' AND flag = 'nil.png' ", $eoFlag ) );

	  $quFlag = wpml_get_flag_file_name('qu');
	  $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_flags SET flag = %s WHERE lang_code = 'qu' AND flag = 'nil.png' ", $quFlag ) );

	  $zuFlag = wpml_get_flag_file_name('zu');
	  $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_flags SET flag = %s WHERE lang_code = 'zu' AND flag = 'nil.png' ", $zuFlag ) );

  $cols = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}icl_languages" );
if ( empty( $cols[6] ) || $cols[6]->Field != 'encode_url' ) {
		$wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_languages ADD COLUMN encode_url TINYINT(1) NOT NULL DEFAULT 0" );

		$wpdb->query( "UPDATE {$wpdb->prefix}icl_languages SET encode_url = 1 WHERE code IN ('ru','uk','zh-hans','zh-hant','ja','ko','vi','th','he','ar','el','fa')" );
	}
