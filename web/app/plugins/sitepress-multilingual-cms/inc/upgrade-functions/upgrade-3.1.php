<?php
global $wpdb;

$wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_translate` CHANGE field_data field_data longtext NOT NULL" );
$wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_translate` CHANGE field_data_translated field_data_translated longtext NOT NULL" );
