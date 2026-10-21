<?php

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Infrastructure\WordPress\Component\SettingsStorage\VirtualSettingsOption;
use WPML\TM\Settings\RequestSettings;
use function WPML\Container\make;

function wpml_site_uses_icl() {
	global $wpdb;

	$setting = 'site_does_not_use_icl';

	if ( icl_get_setting( $setting, false ) ) {
		return false;
	}

	$cache = new WPML_WP_Cache( 'wpml-checks' );

	$found         = false;
	$site_uses_icl = $cache->get( 'site_uses_icl', $found );

	if ( ! $found ) {
		$site_uses_icl = false;

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_translation_status'" );

		if ( $table_exists ) {
			$site_uses_icl = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT rid
							FROM {$wpdb->prefix}icl_translation_status
							WHERE translation_service = %s
							LIMIT 1;",
					'icanlocalize'
				)
			);
		}
		$cache->set( 'site_uses_icl', $site_uses_icl );
	}

	if ( icl_get_setting( 'setup_complete', false ) && ! $site_uses_icl ) {
		icl_set_setting( $setting, true, true );
	}

	return $site_uses_icl;
}

function icl_get_setting( $key, $default = false ) {
	return wpml_get_setting( $key, $default );
}

function wpml_get_setting( $key, $default = null ) {
	return RequestSettings::get( $key, $default );
}

function wpml_get_setting_filter( $default, $key ) {
	$args = func_get_args();
	if ( count( $args ) > 2 && $args[2] !== null ) {
		$default = $args[2];
	}

	return wpml_get_setting( $key, $default );
}

function wpml_get_tm_sub_setting( $key, $fallback = array() ) {
	return RequestSettings::tmSubSetting( $key, $fallback );
}

function icl_get_sub_setting( $key, $sub_key, $default = false ) {
	$parent = icl_get_setting( $key, array() );

	return isset( $parent[ $sub_key ] ) ? $parent[ $sub_key ] : $default;
}

function wpml_get_sub_setting_filter( $default, $key, $sub_key, $deprecated = null ) {
	$default = null !== $deprecated && ! $default ? $deprecated : $default;

	if ( ElementType::SETTINGS_KEY === $key && ! in_array( $sub_key, ElementType::BLOB_KEYS, true ) ) {
		return wpml_get_tm_sub_setting( $sub_key, $default );
	}

	$parent = wpml_get_setting_filter( array(), $key );

	return isset( $parent[ $sub_key ] ) ? $parent[ $sub_key ] : $default;
}

function icl_set_setting( $key, $value, $save_now = false ) {
	global $sitepress_settings;

	$result = true;

	$sitepress_settings[ $key ] = $value;
	VirtualSettingsOption::recordSetterValue( (string) $key, $value );

	if ( true === $save_now ) {
		$result = update_option( 'icl_sitepress_settings', $sitepress_settings );
		do_action( 'icl_save_settings', $sitepress_settings );
	}

	return $result;
}

function icl_save_settings() {
	global $sitepress;
	$sitepress->save_settings();
}

function icl_get_settings() {
	global $sitepress;

	return isset( $sitepress ) ? $sitepress->get_settings() : false;
}

function icl_plugin_action_links( SitePress $sitepress, $links, $file ) {
	if ( $file == WPML_PLUGIN_BASENAME ) {
		$url     = $sitepress->is_setup_complete()
			? 'admin.php?page=tm/menu/settings&section=languages'
			: 'admin.php?page=' . WPML_PLUGIN_FOLDER . '/menu/setup.php';
		/* translators: Link next to a plugin that opens its settings. Verb, imperative. */
		$links[] = '<a href="' . esc_url( $url ) . '">' . __( 'Configure', 'sitepress' ) . '</a>';
	}

	return $links;
}

if ( defined( 'ICL_DEBUG_MODE' ) && ICL_DEBUG_MODE ) {
	add_action( 'admin_notices', '_icl_deprecated_icl_debug_mode' );
}

function _icl_deprecated_icl_debug_mode() {
	echo '<div class="updated"><p><strong>ICL_DEBUG_MODE</strong> no longer supported. Please use <strong>WP_DEBUG</strong> instead.</p></div>';
}

if ( ! function_exists( 'icl_js_escape' ) ) {
	function icl_js_escape( $str ) {
		$str = esc_js( $str );
		$str = htmlspecialchars_decode( $str );

		return $str;
	}
}

function wpml_get_site_id( $scope = WPML_Site_ID::SITE_SCOPES_GLOBAL ) {
	static $site_id;

	if ( ! $site_id ) {
		$site_id = new WPML_Site_ID();
	}

	return $site_id->get_site_id( $scope );
}

function _icl_tax_has_objects_recursive( $id, $term_id = -1, $rec = 0 ) {
	if ( $rec > 60 ) {
		return false;
	}

	global $wpdb;

	if ( $term_id === -1 ) {
		$term_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $id ) );
	}

	$children = $wpdb->get_results(
		$wpdb->prepare(
			"
        SELECT term_taxonomy_id, term_id, count FROM {$wpdb->term_taxonomy} WHERE parent = %d
    ",
			$term_id
		)
	);

	$count = 0;
	foreach ( $children as $ch ) {
		$count += $ch->count;
	}

	if ( $count ) {
		return true;
	} else {
		foreach ( $children as $ch ) {
			if ( _icl_tax_has_objects_recursive( $ch->term_taxonomy_id, $ch->term_id, $rec + 1 ) ) {
				return true;
			}
		}
	}

	return false;
}

function _icl_trash_restore_prompt() {
	if ( isset( $_GET['lang'] ) ) {
		$post = get_post( intval( $_GET['post'] ) );
		if ( isset( $post->post_status ) && $post->post_status == 'trash' ) {
			$post_type_object = get_post_type_object( $post->post_type );
			$delete_post_url  = get_delete_post_link( $post->ID, '', true );

			if ( is_string( $delete_post_url ) ) {
				/* translators: Link text inside a sentence about a post in the Trash; "it" is that post. It starts in lower case because it sits inside the sentence. Verb phrase, imperative. */
				$delete_post_link  = '<a href="' . esc_url( $delete_post_url ) . '">' . esc_html__( 'delete it permanently', 'sitepress' ) . '</a>';
				/* translators: Link text inside a sentence about a post in the Trash: bring it back. It starts in lower case because it sits inside the sentence. Verb, imperative. */
				$restore_post_link = '<a href="' . wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ) . '">' . esc_html__( 'restore', 'sitepress' ) . '</a>';
				/* translators: %1$s is the link text 'delete it permanently' and %2$s the link text 'restore'; both sit inside the sentence. */
				$ret               = '<p>' . sprintf( esc_html__( 'This translation is currently in the trash. You need to either %1$s or %2$s it in order to continue.', 'sitepress' ), $delete_post_link, $restore_post_link );

				wp_die( $ret );
			}
		}
	}
}

function icl_makes_duplicates( $master_post_id ) {
	wpml_admin_make_post_duplicates_action( (int) $master_post_id );
}

function wpml_admin_make_post_duplicates_action( $master_post_id ) {
	$post      = get_post( $master_post_id );
	$post_type = $post->post_type;

	if ( $post->post_status == 'auto-draft' || $post->post_type == 'revision' ) {
		return;
	}

	global $sitepress;
	$iclTranslationManagement = wpml_load_core_tm();
	if ( $sitepress->is_translated_post_type( $post_type ) ) {
		$iclTranslationManagement->make_duplicates_all( $master_post_id );
	}
}

function icl_makes_duplicates_public( $master_post_id ) {
	wpml_make_post_duplicates_action( (int) $master_post_id );
}

function wpml_make_post_duplicates_action( $master_post_id ) {

	global $sitepress;

	$master_post = get_post( $master_post_id );

	if ( 'auto-draft' === $master_post->post_status || 'revision' === $master_post->post_type ) {
		return;
	}

	$active_langs = $sitepress->get_active_languages();

	foreach ( $active_langs as $lang_to => $one ) {

		$trid      = $sitepress->get_element_trid( $master_post->ID, 'post_' . $master_post->post_type );
		$lang_from = $sitepress->get_source_language_by_trid( $trid );

		if ( $lang_from == $lang_to ) {
			continue;
		}

		$sitepress->make_duplicate( $master_post_id, $lang_to );
	}
}

function wpml_like_escape( $text ) {
	global $wpdb;

	if ( method_exists( $wpdb, 'esc_like' ) ) {
		return $wpdb->esc_like( $text );
	}


	return like_escape( $text );
}

function icl_do_not_promote() {
	return defined( 'ICL_DONT_PROMOTE' ) && ICL_DONT_PROMOTE;
}

function icl_convert_to_user_time( $time ) {

	$time_offset = get_option( 'gmt_offset' ) * 3600;
	$local_time  = __( 'Last Update Time could not be determined', 'sitepress' );

	try {
		$creation_time = strtotime( $time );
		if ( $creation_time !== false ) {
			$local_time = date( 'Y-m-d H:i:s', $creation_time + $time_offset );
		}
	} catch ( Exception $e ) {
	}

	return $local_time;
}

function icl_is_language_active( $language ) {
	global $sitepress;

	$active_languages = $sitepress->get_active_languages();

	return isset( $active_languages[ $language ] );
}

function wpml_language_is_active_filter( $empty_value, $language_code ) {
	global $sitepress;

	return $sitepress->is_active_language( $language_code );
}

function wpml_strip_subdir_from_url( $url ) {
	global $wpml_url_converter;

	$subdir       = wpml_parse_url( $wpml_url_converter->get_abs_home(), PHP_URL_PATH );
	$subdir_slugs = ! empty( $subdir ) ? array_values( array_filter( explode( '/', $subdir ) ) ) : [];

	if ( ! $subdir_slugs ) {
		return untrailingslashit( $url );
	}

	$scheme = '';
	if ( preg_match( '#^(https?://)#', $url, $matches ) ) {
		$scheme = $matches[1];
		$url    = substr( $url, strlen( $scheme ) );
	}

	$segments = explode( '/', $url );
	$host     = array_shift( $segments );

	foreach ( $subdir_slugs as $subdir_slug ) {
		if ( isset( $segments[0] ) && $segments[0] === $subdir_slug ) {
			array_shift( $segments );
		} else {
			break;
		}
	}

	$path = $segments ? '/' . implode( '/', $segments ) : '';

	return untrailingslashit( $scheme . $host . $path );
}

function wpml_prepare_in( $items, $format = '%s' ) {
	global $wpdb;

	$items = (array) $items;
	if ( ! $items ) {
		return '';
	}

	$prepare_item = '%d' === $format
		? static function( $item ) use ( $wpdb ) {
			return $wpdb->prepare( '%d', $item );
		}
		: static function( $item ) use ( $wpdb ) {
			return $wpdb->prepare( '%s', $item );
		};

	return implode( ',', array_map( $prepare_item, $items ) );
}

function wpml_post_element_types( $post_types ) {
	$element_types = array();
	foreach ( (array) $post_types as $post_type ) {
		$element_types[] = 'post_' . $post_type;
	}

	return $element_types;
}

function is_not_installing_plugins() {
	global $sitepress;

	$checked = isset( $_REQUEST['checked'] ) ? (array) $_REQUEST['checked'] : array();

	if ( ! isset( $_REQUEST['action'] ) ) {
		return true;
	} elseif ( $_REQUEST['action'] != 'activate' && $_REQUEST['action'] != 'activate-selected' ) {
		return true;
	} elseif ( ( ! isset( $_REQUEST['plugin'] ) || $_REQUEST['plugin'] != WPML_PLUGIN_FOLDER . '/' . basename( __FILE__ ) ) && ! in_array( WPML_PLUGIN_FOLDER . '/' . basename( __FILE__ ), $checked ) ) {
		return true;
	} elseif ( in_array( WPML_PLUGIN_FOLDER . '/' . basename( __FILE__ ), $checked ) && ! isset( $sitepress ) ) {
		return true;
	}

	return false;
}

function wpml_mb_strtolower( $string ) {
	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $string );
	}

	return strtolower( $string );
}

function wpml_mb_strpos( $haystack, $needle, $offset = 0 ) {
	if ( function_exists( 'mb_strpos' ) ) {
		return mb_strpos( $haystack, $needle, $offset );
	}

	return strpos( $haystack, $needle, $offset );
}

function wpml_set_plugin_as_inactive() {
	global $icl_plugin_inactive;
	if ( ! defined( 'ICL_PLUGIN_INACTIVE' ) ) {
		define( 'ICL_PLUGIN_INACTIVE', true );
	}
	$icl_plugin_inactive = true;
}

function wpml_version_is( $version_to_check, $comparison = '==' ) {
	return version_compare( ICL_SITEPRESS_VERSION, $version_to_check, $comparison ) && function_exists( 'wpml_site_uses_icl' );
}

function icl_suppress_activation() {
	$active_plugins    = get_option( 'active_plugins' );
	$icl_sitepress_idx = array_search( WPML_PLUGIN_BASENAME, $active_plugins );
	if ( false !== $icl_sitepress_idx ) {
		unset( $active_plugins[ $icl_sitepress_idx ] );
		update_option( 'active_plugins', $active_plugins );
		unset( $_GET['activate'] );
		$recently_activated = get_option( 'recently_activated' );
		if ( ! isset( $recently_activated[ WPML_PLUGIN_BASENAME ] ) ) {
			$recently_activated[ WPML_PLUGIN_BASENAME ] = time();
			update_option( 'recently_activated', $recently_activated );
		}
	}
}

function activate_installer( ?SitePress $sitepress = null ) {
	include_once WPML_PLUGIN_PATH . '/vendor/otgs/installer/loader.php';
	$args = array(
		'plugins_install_tab' => 1,
	);

	if ( $sitepress ) {
		$args['site_key_nags'] = array(
			array(
				'repository_id' => 'wpml',
				'product_name'  => 'WPML',
				'condition_cb'  => array( $sitepress, 'setup' ),
			),
		);
	}
	WP_Installer_Setup( $wp_installer_instance, $args );

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once dirname( __DIR__ ) . '/classes/Installer/CliDeferredLoader.php';
		\WPML\Installer\CliDeferredLoader::register();
	}
}

function wpml_missing_filter_input_notice() {
	$uses_filter_extension = sprintf(
		/* translators: %s: link to the PHP documentation of the extension, with the text "PHP Data Filtering". */
		esc_html__( 'To ensure and improve the security of your website, WPML makes use of the %s extension.', 'sitepress' ),
		'<a href="http://php.net/manual/en/book.filter.php">' .
		/* translators: Name of a PHP extension, used as a link in "WPML makes use of the PHP Data Filtering extension." */
		esc_html__( 'PHP Data Filtering', 'sitepress' ) . '</a>'
	);
	?>
	<div class="message error">
		<h3><?php esc_html_e( "WPML can't be functional because it requires a disabled PHP extension!", 'sitepress' ); ?></h3>

		<p><?php echo wp_kses( $uses_filter_extension, array( 'a' => array( 'href' => array() ) ) ); ?><br><br>
			<?php
			esc_html_e(
				'The filter extension is enabled by default as of PHP 5.2.0. Before this time an experimental PECL extension was
            used, however, the PECL version is no longer recommended to be used or updated. (source: ',
				'sitepress'
			);
			?>
			<a href="http://php.net/manual/en/filter.installation.php">PHP Manual Function Reference Variable and
				Type Related Extensions Filter
				Installing/Configuring</a>)<br>
			<br>
			<?php esc_html_e( 'The filter extension is enabled by default as of PHP 5.2, therefore it must have been disabled by either you or your host.', 'sitepress' ); ?>
			<br><?php /* translators: Line under that sentence; "it" is the extension named just above. */ esc_html_e( "To enable it, either you or your host will need to open your website's php.ini file and either:", 'sitepress' ); ?><br>
		<ol>
			<li><?php esc_html_e( "Remove the 'filter_var' string from the 'disable_functions' directive or...", 'sitepress' ); ?>
			</li>
			<li><?php esc_html_e( 'Add the following line:', 'sitepress' ); ?> <code class="inline-code">extension=filter.so</code></li>
		</ol>
		<?php
		$ini_location = php_ini_loaded_file();
		if ( $ini_location !== false ) {
			?>
			<strong><?php esc_html_e( 'Your php.ini file is located at', 'sitepress' ) . ' ' . esc_html( $ini_location ); ?>.</strong>
			<?php
		}
		?>
	</div>
	<?php
}

function repair_el_type_collate() {
	global $wpdb;

	$correct_collate = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT collation_name
	          FROM information_schema.COLUMNS
	          WHERE TABLE_NAME = %s
	                AND COLUMN_NAME = 'post_type'
	                    AND table_schema = (SELECT DATABASE())
	          LIMIT 1",
			$wpdb->posts
		)
	);

	if ( ! is_string( $correct_collate ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $correct_collate ) ) {
		throw new Exception( 'Could not determine a safe collation.' );
	}

	if ( false === $wpdb->query( "ALTER TABLE `{$wpdb->prefix}icl_translations` CHANGE `element_type` `element_type` VARCHAR( 60 ) NOT NULL DEFAULT 'post_post' COLLATE " . esc_sql( $correct_collate ) ) ) {
		throw new Exception( $wpdb->last_error );
	}
}

function wpml_parse_url( $url, $component = -1 ) {
	$ret = null;

	$component_map = array(
		PHP_URL_SCHEME   => 'scheme',
		PHP_URL_HOST     => 'host',
		PHP_URL_PORT     => 'port',
		PHP_URL_USER     => 'user',
		PHP_URL_PASS     => 'pass',
		PHP_URL_PATH     => 'path',
		PHP_URL_QUERY    => 'query',
		PHP_URL_FRAGMENT => 'fragment',
	);

	if ( $component === -1 ) {
		$ret = wp_parse_url( $url );
	} elseif ( isset( $component_map[ $component ] ) ) {
		$key    = $component_map[ $component ];
		$parsed = wp_parse_url( $url );
		$ret    = isset( $parsed[ $key ] ) ? $parsed[ $key ] : null;
	}

	return $ret;
}

function wpml_http_build_query( $query_data ) {
	return http_build_query( $query_data, '', '&' );
}

function wpml_array_unique( $array, $sort_flags = SORT_REGULAR ) {
	if ( version_compare( phpversion(), '5.2.9', '>=' ) ) {
		return array_unique( $array, $sort_flags );
	}

	return wpml_array_unique_fallback( $array, true );
}

function wpml_array_unique_fallback( $array, $keep_key_assoc ) {
	$duplicate_keys = array();
	$tmp            = array();

	foreach ( $array as $key => $val ) {
		if ( is_object( $val ) ) {
			$val = (array) $val;
		}

		if ( ! in_array( $val, $tmp ) ) {
			$tmp[] = $val;
		} else {
			$duplicate_keys[] = $key;
		}
	}

	foreach ( $duplicate_keys as $key ) {
		unset( $array[ $key ] );
	}

	return $keep_key_assoc ? $array : array_values( $array );
}

function wpml_is_rest_request() {
	return make( WPML_REST_Request_Analyze::class )->is_rest_request();
}

function wpml_is_rest_enabled(bool $useCache = true) {
	global $wpml_dic;
	return $wpml_dic->make( WPML\Infrastructure\WordPress\SharedKernel\Server\Application\CheckRestIsEnabled::class )->isEnabled($useCache);
}

function wpml_is_cli() {
	return defined( 'WP_CLI' ) && WP_CLI;
}

function wpml_is_trusted_no_user_context() {
	return \WPML\Core\Security\ExecutionContext\ExecutionContextHolder::isTrusted();
}

function wpml_validate_requested_language( $raw, $scope = \WPML\Language\RequestedLanguage::SCOPE_VIEWER, $allow_all = false ) {
	return \WPML\Language\RequestedLanguage::validate( $raw, $scope, $allow_all );
}

function wpml_requested_language_filter( $default, $raw, $scope = \WPML\Language\RequestedLanguage::SCOPE_VIEWER, $allow_all = false ) {
	return wpml_validate_requested_language( $raw, $scope, $allow_all );
}

function wpml_sticky_post_sync( ?SitePress $sitepress = null ) {
	static $instance;

	if ( ! $instance ) {
		global $wpml_post_translations;

		if ( ! $sitepress ) {
			global $sitepress;
		}

		$instance = new WPML_Sticky_Posts_Sync(
			$sitepress,
			$wpml_post_translations,
			new WPML_Sticky_Posts_Lang_Filter(
				$sitepress,
				$wpml_post_translations
			)
		);
	}

	return $instance;
}

function wpml_autoload_wp_filesystem_class( $class ) {
	$files = [
		'wp_filesystem_base'   => 'class-wp-filesystem-base.php',
		'wp_filesystem_direct' => 'class-wp-filesystem-direct.php',
	];

	$key = strtolower( $class );
	if ( ! isset( $files[ $key ] ) ) {
		return;
	}

	$file = ABSPATH . 'wp-admin/includes/' . $files[ $key ];
	if ( file_exists( $file ) ) {
		require_once $file;
	}
}

spl_autoload_register( 'wpml_autoload_wp_filesystem_class' );

function wpml_get_filesystem() {
	$wp_api = new WPML_WP_API();

	return $wp_api->get_wp_filesystem();
}

function wpml_get_filesystem_direct() {
	static $instance;

	if ( ! $instance ) {
		$wp_api   = new WPML_WP_API();
		$instance = $wp_api->get_wp_filesystem_direct();
	}

	return $instance;
}

function wpml_update_escaped_post( array $postarray, $lang = null, $wp_error = false ) {
	return wpml_get_create_post_helper()->insert_post( $postarray, $lang, $wp_error );
}

function wpml_get_cache( $group = '' ) {
	return new WPML_WP_Cache( $group );
}


if ( ! function_exists( 'wpml_is_ajax' ) ) {
	function wpml_is_ajax() {
		if ( defined( 'DOING_AJAX' ) ) {
			return true;
		}

		return ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && wpml_mb_strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' ) ? true : false;
	}
}

if ( ! function_exists( 'wpml_get_flag_file_name' ) ) {
	function wpml_get_flag_file_name( $lang_code ) {
		if ( file_exists( WPML_PLUGIN_PATH . '/res/flags/' . $lang_code . '.svg' ) ) {
			$file = $lang_code . '.svg';
		} elseif ( file_exists( WPML_PLUGIN_PATH . '/res/flags/' . $lang_code . '.png' ) ) {
			$file = $lang_code . '.png';
		} elseif ( file_exists( WPML_PLUGIN_PATH . '/res/flags/nil.svg' ) ) {
			$file = 'nil.svg';
		} else {
			$file = 'nil.png';
		}

		return $file;
	}
}


function wpml_is_st_loaded(): bool {
	return defined( 'WPML_ST_VERSION' );
}

function wpml_trigger_error( $function_name, $message, $error_level = E_USER_NOTICE ) {
	if ( function_exists( 'wp_trigger_error' ) ) {
		wp_trigger_error( $function_name, $message, $error_level );
	} else {
		trigger_error( $message, $error_level );
	}
}
