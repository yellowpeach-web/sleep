<?php
global $wpdb;

$wpdb->query( 'ALTER TABLE `' . $wpdb->prefix . 'icl_translations` ADD KEY `id_type_language` ( `element_id`, `element_type`, `language_code`)' );
