<?php

class WPML_ACF_Requirements {

	public function check_wpml_core() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_wpml_notice' ) );
		}
	}

	public function checkAcf() {
		add_action( 'after_setup_theme', [ $this, 'addMissingAcfNotice' ] );
	}

	public function addMissingAcfNotice() {
		if ( ! class_exists( \ACF::class ) ) {
			add_action( 'admin_notices', [ $this, 'showMissingAcfNotice' ] );
		}
	}

	public function missing_wpml_notice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php /* translators: Admin notice shown when the plugin is active but WPML is not; "It" is this plugin. */ esc_html_e( 'ACFML is enabled but not effective. It requires WPML in order to work.', 'acfml' ); ?></p>
		</div>
		<?php
	}

	public function showMissingAcfNotice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$remedy = $this->getAcfRemedy();
		if ( ! $remedy ) {
			return;
		}
		?>
		<div class="notice notice-error acfml-missing-acf-notice">
			<p><?php /* translators: Admin notice shown when this plugin is active but Advanced Custom Fields is not; "It" is this plugin. */ esc_html_e( 'ACFML is enabled but not effective. It requires Advanced Custom Fields or ACF PRO in order to work.', 'acfml' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( $remedy['url'] ); ?>"><?php echo esc_html( $remedy['label'] ); ?></a></p>
		</div>
		<?php
	}

	private function getAcfRemedy() {
		$installedAcfPluginFile = $this->getInstalledAcfPluginFile();

		if ( $installedAcfPluginFile ) {
			return [
				/* translators: Button on the notice shown when Advanced Custom Fields is installed but not active. */
				'label' => __( 'Activate Advanced Custom Fields', 'acfml' ),
				'url'   => wp_nonce_url(
					add_query_arg(
						[
							'action' => 'activate',
							'plugin' => $installedAcfPluginFile,
						],
						admin_url( 'plugins.php' )
					),
					'activate-plugin_' . $installedAcfPluginFile
				),
			];
		}

		if ( current_user_can( 'install_plugins' ) ) {
			return [
				/* translators: Button on the notice shown when Advanced Custom Fields is not installed. */
				'label' => __( 'Install Advanced Custom Fields', 'acfml' ),
				'url'   => add_query_arg(
					[
						'tab'  => 'search',
						'type' => 'term',
						's'    => 'Advanced Custom Fields',
					],
					admin_url( 'plugin-install.php' )
				),
			];
		}

		return null;
	}

	private function getInstalledAcfPluginFile() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( array_keys( get_plugins() ) as $pluginFile ) {
			if ( 'acf.php' === basename( $pluginFile ) ) {
				return $pluginFile;
			}
		}

		return null;
	}
}
