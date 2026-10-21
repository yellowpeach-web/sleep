<?php

function update_icl_strings_charset_and_collations() {
	global $wpdb;

	$charset = 'CHARACTER SET ' . $wpdb->charset;
	$collate = '';

	if ( method_exists( $wpdb, 'has_cap' ) && $wpdb->has_cap( 'collation' ) && ! empty( $wpdb->collate ) ) {
		$collate .= 'COLLATE ' . $wpdb->collate;
	}

	$fields = array(
		'name'                    => WPML_STRING_TABLE_NAME_CONTEXT_LENGTH,
		'context'                 => WPML_STRING_TABLE_NAME_CONTEXT_LENGTH,
		'domain_name_context_md5' => 32,
	);

	foreach ( $fields as $field => $size ) {
		if ( $wpdb->query(
			sprintf(
				"ALTER TABLE `{$wpdb->prefix}icl_strings` MODIFY `%s` VARCHAR(%d) %s %s",
				esc_sql( $field ),
				absint( $size ),
				esc_sql( $charset ),
				esc_sql( $collate )
			)
		) === false ) {
			throw new Exception( $wpdb->last_error );
		}
	}

}

update_icl_strings_charset_and_collations();
