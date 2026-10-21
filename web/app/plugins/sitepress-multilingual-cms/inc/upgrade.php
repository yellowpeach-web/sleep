<?php

$wp_api = new WPML_WP_API();

if ( ! defined( 'ICL_SITEPRESS_DEV_VERSION' ) && ( $wp_api->version_compare_naked( get_option( 'icl_sitepress_version' ), ICL_SITEPRESS_VERSION, '=' ) || ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'error_scrape' ) || ! isset( $wpdb ) ) ) {
	return;
}


if ( get_option( 'icl_sitepress_version' ) && version_compare( get_option( 'icl_sitepress_version' ), '1.7.0', '<' ) ) {
	define( 'WPML_UPGRADE_NOT_POSSIBLE', true );
	add_action( 'admin_notices', 'icl_plugin_too_old' );

	return;
}

add_action( 'plugins_loaded', 'icl_plugin_upgrade', 1 );


function icl_plugin_upgrade() {
	global $wpdb;
	if ( WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
		return;
	}

	$iclsettings = get_option( 'icl_sitepress_settings' );

	require_once WPML_PLUGIN_PATH . '/inc/cache.php';

	if ( get_option( 'icl_sitepress_version' ) && version_compare( get_option( 'icl_sitepress_version' ), '1.7.2', '<' ) ) {
		$kuFlag = wpml_get_flag_file_name('ku');
		$wpdb->update( $wpdb->prefix . 'icl_flags', array( 'flag' => $kuFlag ), array( 'lang_code' => 'ku' ) );
		$wpdb->update(
			$wpdb->prefix . 'icl_languages_translations',
			array( 'name' => 'Magyar' ),
			array(
				'language_code'         => 'hu',
				'display_language_code' => 'hu',
			)
		);
		$wpdb->update(
			$wpdb->prefix . 'icl_languages_translations',
			array( 'name' => 'Hrvatski' ),
			array(
				'language_code'         => 'hr',
				'display_language_code' => 'hr',
			)
		);
		$wpdb->update(
			$wpdb->prefix . 'icl_languages_translations',
			array( 'name' => 'فارسی' ),
			array(
				'language_code'         => 'fa',
				'display_language_code' => 'fa',
			)
		);
	}

	if ( get_option( 'icl_sitepress_version' ) && version_compare( get_option( 'icl_sitepress_version' ), '1.7.3', '<' ) ) {
		$wpdb->update(
			$wpdb->prefix . 'icl_languages_translations',
			array( 'name' => 'پارسی' ),
			array(
				'language_code'         => 'fa',
				'display_language_code' => 'fa',
			)
		);
	}

	if ( get_option( 'icl_sitepress_version' ) && version_compare( get_option( 'icl_sitepress_version' ), '1.7.7', '<' ) ) {
		if ( ! isset( $iclsettings['promote_wpml'] ) ) {
			$iclsettings['promote_wpml'] = 0;
			update_option( 'icl_sitepress_settings', $iclsettings );
		}
		if ( ! isset( $iclsettings['auto_adjust_ids'] ) ) {
			$iclsettings['auto_adjust_ids'] = 0;
			update_option( 'icl_sitepress_settings', $iclsettings );
		}

		$wpdb->query( "UPDATE {$wpdb->prefix}icl_translations SET element_type='tax_post_tag' WHERE element_type='tag'" );
		$wpdb->query( "UPDATE {$wpdb->prefix}icl_translations SET element_type='tax_category' WHERE element_type='category'" );
	}

	if ( get_option( 'icl_sitepress_version' ) && version_compare( get_option( 'icl_sitepress_version' ), '1.7.8', '<' ) ) {
		$res        = $wpdb->get_results( "SELECT ID, post_type FROM {$wpdb->posts}" );
		$post_types = array();
		foreach ( $res as $row ) {
			$post_types[ $row->post_type ][] = $row->ID;
		}
		foreach ( $post_types as $type => $ids ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translations SET element_type=%s WHERE element_type='post' AND element_id IN(" . esc_sql( implode( ',', array_map( 'absint', $ids ) ) ) . ')',
					'post_' . $type
				)
			);
		}

		$res = $wpdb->get_results( "SELECT term_taxonomy_id, taxonomy FROM {$wpdb->term_taxonomy}" );
		foreach ( $res as $row ) {
			$icltr = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT translation_id, element_type FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type LIKE %s",
					array( $row->term_taxonomy_id, wpml_like_escape( 'tax_' ) . '%' )
				)
			);
			if ( 'tax_' . $row->taxonomy != $icltr->element_type ) {
				$wpdb->update( $wpdb->prefix . 'icl_translations', array( 'element_type' => 'tax_' . $row->taxonomy ), array( 'translation_id' => $icltr->translation_id ) );
			}
		}
	}

	if ( get_option( 'icl_sitepress_version' ) && version_compare( get_option( 'icl_sitepress_version' ), '2.0.4', '<' ) ) {
		$wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_translation_status ADD COLUMN `_prevstate` longtext" );
	}

	$versions = array(
		'2.0.5',
		'2.2.2',
		'2.3.0',
		'2.3.1',
		'2.3.3',
		'2.4.0',
		'2.5.0',
		'2.5.2',
		'2.6.0',
		'2.7',
		'2.9',
		'2.9.3',
		'3.1',
		'3.1.5',
		'3.1.8',
		'3.1.9.5',
		'3.2',
		'3.2.3',
		'3.3',
		'3.3.7',
		'3.5.1',
	);

	foreach ( $versions as $version ) {
		icl_upgrade_version( $version );
	}

	if ( defined( 'ICL_SITEPRESS_DEV_VERSION' ) ) {
		icl_upgrade_version( ICL_SITEPRESS_DEV_VERSION, true );
	}

	if ( version_compare( get_option( 'icl_sitepress_version' ), ICL_SITEPRESS_VERSION, '<' ) ) {
		icl_cache_clear( 'locale_cache_class' );
		icl_cache_clear( 'flags_cache_class' );
		icl_cache_clear( 'language_name_cache_class' );
		delete_option( \WPML_LS_Templates::OPTION_NAME );
		update_option( 'icl_sitepress_version', ICL_SITEPRESS_VERSION );
	}

	do_action( 'wpml_upgraded', ICL_SITEPRESS_VERSION );
}

function icl_upgrade_version( $version, $force = false ) {
	global $wpdb, $sitepress_settings, $sitepress, $iclsettings;

	if ( ! $force && defined( 'WPML_FORCE_UPDATES' ) ) {
		$force = WPML_FORCE_UPDATES;
	}

	if ( $force || ( get_option( 'icl_sitepress_version' ) && version_compare( get_option( 'icl_sitepress_version' ), $version, '<' ) ) ) {
		$upg_file = WPML_PLUGIN_PATH . '/inc/upgrade-functions/upgrade-' . $version . '.php';
		if ( file_exists( $upg_file ) && is_readable( $upg_file ) ) {
			if ( ! defined( 'WPML_DOING_UPGRADE' ) ) {
				define( 'WPML_DOING_UPGRADE', true );
			}
			include_once $upg_file;
		}
	}
}

function icl_plugin_too_old() {
	?>
	<div class="error message">
		<p>
		<?php
			printf(
				/* translators: Notice shown when the site cannot be brought up to this version of WPML in one step. %1$s: the oldest version this one can be reached from, %2$s: the version the site is on now, %3$s: the address of the older version to install first, filling the link tag that is already in the text. */
				__( '<strong>WPML notice:</strong> Upgrades to this version are only supported from versions %1$s and above. To upgrade from version %2$s, first, download <a%3$s>2.0.4</a>, do the DB upgrade and then go to this version.', 'sitepress' ),
				'1.7.0',
				get_option( 'icl_sitepress_version' ),
				' href="http://downloads.wordpress.org/plugin/sitepress-multilingual-cms.2.0.4.zip"'
			);
		?>
				</p>
	</div>
	<?php

}

function icl_table_column_exists( $table_name, $column_name ) {
	global $wpdb;

	$column_exists = $wpdb->get_var(
		$wpdb->prepare(
			'
			SELECT count(*) FROM information_schema.COLUMNS
			WHERE COLUMN_NAME = %s AND TABLE_NAME = %s AND TABLE_SCHEMA = %s
			',
			$column_name,
			$wpdb->prefix . $table_name,
			DB_NAME
		)
	);

	return (bool) $column_exists;
}

function icl_table_index_exists( $table_name, $index_name ) {
	global $wpdb;

	$column_exists = $wpdb->get_var(
		$wpdb->prepare(
			'
			SELECT count(*) FROM information_schema.STATISTICS
			WHERE INDEX_NAME = %s AND TABLE_NAME = %s AND TABLE_SCHEMA = %s;
			',
			$index_name,
			$wpdb->prefix . $table_name,
			DB_NAME
		)
	);

	return (bool) $column_exists;
}

function icl_alter_table_columns( $table_name, $column_definitions ) {
	global $wpdb;

	$result = false;

	if ( ! icl_is_safe_sql_identifier( $table_name ) ) {
		return $result;
	}

	if ( ! is_array( $column_definitions ) ) {
		$column_definitions = array( $column_definitions );
	}

	$args = array();

	$counter = 0;

	$query_parts = array();
	foreach ( $column_definitions as $column_definition ) {

		if ( isset( $column_definition['action'] ) && $column_definition['action'] == 'ADD' ) {
			$required_keys = array(
				'action',
				'name',
				'type',
			);
		} else {
			$required_keys = array(
				'action',
				'name',
			);
		}

		if (
			icl_array_has_required_keys( $column_definition, $required_keys )
			&& in_array( $column_definition['action'], array( 'ADD', 'DROP', 'MODIFY', 'CHANGE' ), true )
			&& icl_is_safe_sql_identifier( $column_definition['name'] )
			&& ( ! isset( $column_definition['type'] ) || icl_is_safe_sql_type( $column_definition['type'] ) )
			&& ( ! isset( $column_definition['charset'] ) || icl_is_safe_sql_identifier( $column_definition['charset'] ) )
			&& ( ! isset( $column_definition['after'] ) || icl_is_safe_sql_identifier( $column_definition['after'] ) )
		) {

			if ( $counter > 0 ) {
				$query_parts[] = ',';
			}
			$query_parts[] = $column_definition['action'];
			$query_parts[] = '`' . $column_definition['name'] . '`';
			if ( isset( $column_definition['type'] ) ) {
				$query_parts[] = $column_definition['type'];
			}
			if ( isset( $column_definition['charset'] ) ) {
				$query_parts[] = 'CHARACTER SET ' . $column_definition['charset'];
			}
			if ( isset( $column_definition['null'] ) ) {
				$query_parts[] = $column_definition['null'] ? 'NULL' : 'NOT NULL';
			}
			if ( isset( $column_definition['default'] ) ) {
				$query_parts[] = 'DEFAULT %s';
				$args[]        = $column_definition['default'];
			}
			if ( isset( $column_definition['after'] ) ) {
				$query_parts[] = 'AFTER `' . $column_definition['after'] . '`';
			}
			$counter ++;
		} else {
			$args = array();
			break;
		}
	}

	if ( $query_parts ) {
		if ( 1 === count( $args ) ) {
			$query_around_default = explode( '%s', implode( ' ', $query_parts ), 2 );
			$result = $wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE `' . esc_sql( $wpdb->prefix . $table_name ) . '` ' . esc_sql( $query_around_default[0] ) . '%s' . esc_sql( $query_around_default[1] ),
					$args[0]
				)
			);
		} elseif ( empty( $args ) ) {
			$result = $wpdb->query(
				'ALTER TABLE `' . esc_sql( $wpdb->prefix . $table_name ) . '` ' . esc_sql( implode( ' ', $query_parts ) )
			);
		}
	}

	return $result;
}

function icl_drop_table_index( $table_name, $index_name ) {
	global $wpdb;

	if ( ! icl_is_safe_sql_identifier( $table_name ) || ! icl_is_safe_sql_identifier( $index_name ) ) {
		return false;
	}

	return $wpdb->query(
		'ALTER TABLE `' . esc_sql( $wpdb->prefix . $table_name ) . '` DROP INDEX `' . esc_sql( $index_name ) . '`;'
	);
}

function icl_create_table_index( $table_name, $index_definition ) {
	global $wpdb;

	$result = false;

	$required_keys = array(
		'name',
		'columns',
	);

	if (
		icl_array_has_required_keys( $index_definition, $required_keys )
		&& is_array( $index_definition['columns'] )
		&& $index_definition['columns']
		&& icl_is_safe_sql_identifier( $table_name )
		&& icl_is_safe_sql_identifier( $index_definition['name'] )
		&& ! array_diff( $index_definition['columns'], array_filter( $index_definition['columns'], 'icl_is_safe_sql_identifier' ) )
	) {
		$choice = isset( $index_definition['choice'] ) ? strtoupper( $index_definition['choice'] ) : '';
		$type   = isset( $index_definition['type'] ) ? strtoupper( $index_definition['type'] ) : '';

		if ( ! in_array( $choice, array( '', 'UNIQUE', 'FULLTEXT', 'SPATIAL' ), true ) || ! in_array( $type, array( '', 'BTREE', 'HASH' ), true ) ) {
			return false;
		}

		$result = $wpdb->query(
			'ALTER TABLE `' . esc_sql( $wpdb->prefix . $table_name ) . '` ADD '
			. esc_sql( $choice ? $choice . ' ' : '' )
			. '`' . esc_sql( $index_definition['name'] ) . '` '
			. '(`' . esc_sql( implode( '`, `', $index_definition['columns'] ) ) . '`) '
			. esc_sql( $type ? 'USING ' . $type : '' )
		);
	}

	return $result;
}

function icl_is_safe_sql_identifier( $identifier ) {
	return is_string( $identifier ) && 1 === preg_match( '/\A[A-Za-z0-9_]+\z/D', $identifier );
}

function icl_is_safe_sql_type( $type ) {
	return is_string( $type )
		&& 1 === preg_match( '/\A[A-Za-z]+(?:\s+[A-Za-z]+)*(?:\(\s*\d+(?:\s*,\s*\d+)?\s*\))?(?:\s+UNSIGNED)?\z/iD', $type );
}

function icl_array_has_required_keys( $array, $required_keys ) {
	return count( array_intersect_key( array_flip( $required_keys ), $array ) ) === count( $required_keys );
}
