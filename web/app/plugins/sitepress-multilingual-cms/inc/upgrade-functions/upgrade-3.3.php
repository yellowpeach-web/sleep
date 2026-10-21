<?php

global $wpdb;

$wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_translation_status MODIFY COLUMN translation_package longtext NOT NULL" );

