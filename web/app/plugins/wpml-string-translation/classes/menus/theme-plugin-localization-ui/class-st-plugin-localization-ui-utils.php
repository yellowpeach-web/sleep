<?php

class WPML_ST_Plugin_Localization_Utils {

	private $mu_plugin_files = null;

	public function get_plugins() {
		$plugins = get_plugins();

		foreach ( $this->get_mu_plugin_files() as $plugin_file => $path ) {
			$plugins[ $plugin_file ] = $this->get_mu_plugin_data( $plugin_file, $path );
		}

		return $plugins;
	}

	private function get_mu_plugin_data( $plugin_file, $path ) {
		$data = is_file( $path ) && is_readable( $path ) && function_exists( 'get_plugin_data' )
			? (array) get_plugin_data( $path, false, false )
			: array();

		$name         = isset( $data['Name'] ) && '' !== $data['Name'] ? $data['Name'] : $plugin_file;
		$data['Name'] = 'MU :: ' . $name;

		if ( empty( $data['TextDomain'] ) ) {
			unset( $data['TextDomain'] );
		}

		return $data;
	}

	private function get_mu_plugin_files() {
		if ( null === $this->mu_plugin_files ) {
			$this->mu_plugin_files = array();

			if ( function_exists( 'wp_get_mu_plugins' ) ) {
				foreach ( (array) wp_get_mu_plugins() as $path ) {
					$this->mu_plugin_files[ basename( $path ) ] = $path;
				}
			}
		}

		return $this->mu_plugin_files;
	}

	public function is_mu_plugin( $plugin_file ) {
		return is_string( $plugin_file )
			&& false === strpos( $plugin_file, '/' )
			&& array_key_exists( $plugin_file, $this->get_mu_plugin_files() );
	}

	public function is_plugin_active( $plugin_file ) {
		$active_plugins = get_option( 'active_plugins', array() );

		if ( in_array( $plugin_file, $active_plugins, true ) ) {
			$is_active = true;
		} else {
			$wpmu_sitewide_plugins = (array) get_site_option( 'active_sitewide_plugins' );
			$is_active             = array_key_exists( $plugin_file, $wpmu_sitewide_plugins ) || $this->is_mu_plugin( $plugin_file );
		}

		return $is_active;
	}

	public function get_plugins_by_status( $active ) {
		$plugins = $this->get_plugins();

		foreach ( $plugins as $plugin_file => $plugin ) {
			if ( $active && ! $this->is_plugin_active( $plugin_file ) ) {
				unset( $plugins[ $plugin_file ] );
			} elseif ( ! $active && $this->is_plugin_active( $plugin_file ) ) {
				unset( $plugins[ $plugin_file ] );
			}
		}

		return $plugins;
	}
}
