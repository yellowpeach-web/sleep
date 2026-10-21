<?php

class OTGS_Installer_Standalone_Plugin_Notice {

	const PLUGIN        = 'otgs-installer-plugin/otgs-installer.php';
	const REASON_OPTION = 'otgs_installer_plugin_deactivated';
	const DISMISS_ARG   = 'otgs_installer_dismiss_deactivated_notice';
	const NONCE_ACTION  = 'otgs_installer_dismiss_deactivated_notice';

	const WPML_ACTIVATE_UPDATE_PAGE = 'wpml-activate-update';

	const FRESH_SECONDS = 60;

	const NOTICE_MAX_AGE = 604800;

	private $installer;

	private $menu_url;

	public function __construct( WP_Installer $installer, $menu_url ) {
		$this->installer = $installer;
		$this->menu_url  = $menu_url;
	}

	public function add_hooks() {
		add_action( 'load-plugins.php', array( $this, 'on_plugins_screen_load' ) );
		add_action( 'admin_notices', array( $this, 'show_deactivated_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'show_deactivated_notice' ) );
		add_action( 'load-plugins.php', array( $this, 'add_row_note_hook' ) );
	}

	public function add_row_note_hook() {
		add_action( 'after_plugin_row_' . $this->get_plugin(), array( $this, 'show_row_note' ), 10, 2 );
	}

	public function on_plugins_screen_load() {
		if ( ! $this->is_plain_screen_load() ) {
			return;
		}

		$url = $this->get_screen_load_redirect();

		if ( $url && wp_safe_redirect( $url ) ) {
			exit;
		}
	}

	public function get_screen_load_redirect() {
		if ( ! isset( $_GET[ self::DISMISS_ARG ] ) ) {
			return $this->get_reload_url();
		}

		if ( $this->is_dismiss_request() ) {
			$reason              = (array) $this->get_reason();
			$reason['dismissed'] = true;
			update_option( self::REASON_OPTION, $reason, false );
		}

		return remove_query_arg( array( self::DISMISS_ARG, '_wpnonce' ) );
	}

	public function get_reload_url() {
		$reason = $this->get_reason();
		if ( ! $reason ) {
			return null;
		}

		if ( is_plugin_active( $this->get_plugin() ) || ! $this->is_plugin_installed() ) {
			delete_option( self::REASON_OPTION );

			return null;
		}

		if ( empty( $reason['own_activation'] ) ) {
			return null;
		}

		$reason['own_activation'] = false;
		update_option( self::REASON_OPTION, $reason, false );

		$is_fresh = isset( $reason['time'] ) && ( time() - (int) $reason['time'] ) <= self::FRESH_SECONDS;

		if ( ! $is_fresh || ! isset( $_GET['activate'] ) ) {
			return null;
		}

		return remove_query_arg( 'activate' );
	}

	public function show_deactivated_notice() {
		$reason = $this->get_reason();

		if ( ! $this->is_plugins_screen() || ! current_user_can( 'activate_plugins' ) || ! $reason || ! empty( $reason['dismissed'] ) ) {
			return;
		}

		if ( ! isset( $reason['time'] ) || ( time() - (int) $reason['time'] ) > self::NOTICE_MAX_AGE ) {
			return;
		}

		if ( is_plugin_active( $this->get_plugin() ) || ! $this->is_plugin_installed() || ! $this->would_be_switched_off() ) {
			return;
		}

		$dismiss_url = wp_nonce_url( add_query_arg( self::DISMISS_ARG, '1' ), self::NONCE_ACTION );
		?>
		<div class="notice notice-info otgs-installer-standalone-deactivated">
			<p>
				<strong><?php esc_html_e( 'OTGS Installer was deactivated.', 'installer' ); ?></strong>
				<?php echo esc_html( $this->get_explanation() ); ?>
				<?php
				list( $installer_url, $installer_label ) = $this->get_installer_screen();
				printf(
					/* translators: %s is a link to the screen where WPML's components are installed and updated, labelled "WPML → Activate & Update" or "Plugins → Add Plugin → Commercial". */
					esc_html__( 'Install and update its components from %s.', 'installer' ),
					'<a href="' . esc_url( $installer_url ) . '">' . esc_html( $installer_label ) . '</a>'
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'installer' ); ?></a>
			</p>
		</div>
		<?php
	}

	public function show_row_note( $plugin_file, $plugin_data ) {
		if ( is_plugin_active( $this->get_plugin() ) || ! $this->would_be_switched_off() ) {
			return;
		}

		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		$host          = $this->get_host_plugin_name();

		if ( $host ) {
			/* translators: %s is the name of the active plugin that already includes the Installer, e.g. "WPML Multilingual CMS". */
			$note = sprintf( __( 'Not needed while %s is active – it already includes the Installer.', 'installer' ), $host );
		} else {
			$note = __( 'Not needed right now – the Installer is already included in your active plugins or theme.', 'installer' );
		}
		?>
		<tr class="plugin-update-tr inactive otgs-installer-standalone-row-note">
			<td colspan="<?php echo esc_attr( (string) $wp_list_table->get_column_count() ); ?>" class="plugin-update colspanchange">
				<div class="notice inline notice-info notice-alt">
					<p><?php echo esc_html( $note ); ?></p>
				</div>
			</td>
		</tr>
		<?php
	}

	private function get_explanation() {
		$host = $this->get_host_plugin_name();

		if ( $host ) {
			/* translators: %s is the name of the active plugin that already includes the Installer, e.g. "WPML Multilingual CMS". */
			return sprintf( __( '%s already includes the Installer, so you don\'t need this plugin.', 'installer' ), $host );
		}

		return __( 'The Installer is already included in your active plugins or theme, so you don\'t need this plugin.', 'installer' );
	}

	private function get_installer_screen() {
		$wpml_page_url = menu_page_url( self::WPML_ACTIVATE_UPDATE_PAGE, false );

		if ( $wpml_page_url ) {
			return array( $wpml_page_url, __( 'WPML → Activate & Update', 'installer' ) );
		}

		if ( is_multisite() && ! is_network_admin() ) {
			return array( $this->menu_url, __( 'Settings → Installer', 'installer' ) );
		}

		return array( $this->menu_url, __( 'Plugins → Add Plugin → Commercial', 'installer' ) );
	}

	private function would_be_switched_off() {
		$standalone_version = $this->get_standalone_installer_version();

		if ( null === $standalone_version ) {
			return (bool) $this->get_reason();
		}

		return max( $this->installer->version(), $standalone_version ) === $this->installer->version();
	}

	private function get_standalone_installer_version() {
		$loader = WP_PLUGIN_DIR . '/' . dirname( $this->get_plugin() ) . '/vendor/otgs/installer/loader.php';

		if ( ! is_readable( $loader ) ) {
			return null;
		}

		$head = (string) file_get_contents( $loader, false, null, 0, 4096 );

		return preg_match( '/\$otgs_installer_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $head, $matches ) ? $matches[1] : null;
	}

	private function is_plugin_installed() {
		return file_exists( WP_PLUGIN_DIR . '/' . $this->get_plugin() );
	}

	private function is_plain_screen_load() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';

		$has_action = false;
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( isset( $_REQUEST[ $key ] ) && ( ! is_scalar( $_REQUEST[ $key ] ) || ! in_array( (string) $_REQUEST[ $key ], array( '', '-1' ), true ) ) ) {
				$has_action = true;
			}
		}

		return 'GET' === $method && ! $has_action;
	}

	private function get_plugin() {
		$reason = $this->get_reason();

		if ( $reason && ! empty( $reason['plugin'] ) && is_string( $reason['plugin'] ) && preg_match( '#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+\.php$#', $reason['plugin'] ) && 0 === validate_file( $reason['plugin'] ) ) {
			return $reason['plugin'];
		}

		return self::PLUGIN;
	}

	private function get_reason() {
		$reason = get_option( self::REASON_OPTION );

		return is_array( $reason ) ? $reason : null;
	}

	private function is_plugins_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && in_array( $screen->id, array( 'plugins', 'plugins-network' ), true );
	}

	private function is_dismiss_request() {
		$nonce = isset( $_GET['_wpnonce'] ) ? (string) wp_unslash( $_GET['_wpnonce'] ) : '';

		return isset( $_GET[ self::DISMISS_ARG ] )
		       && current_user_can( 'activate_plugins' )
		       && wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	private function get_host_plugin_name() {
		$plugins_dir = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
		$path        = wp_normalize_path( $this->installer->plugin_path() );

		if ( 0 !== strpos( $path, $plugins_dir ) ) {
			return null;
		}

		$folder = strtok( substr( $path, strlen( $plugins_dir ) ), '/' );

		foreach ( get_plugins() as $plugin_file => $plugin_data ) {
			if ( 0 === strpos( $plugin_file, $folder . '/' ) && $this->get_plugin() !== $plugin_file && ! empty( $plugin_data['Name'] ) ) {
				return $plugin_data['Name'];
			}
		}

		return null;
	}
}
