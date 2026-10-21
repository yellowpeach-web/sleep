<?php

namespace WPML\classes\wizard;

use Exception;
use wpdb;
use WPML\Setup\Option;
use WPML\WP\OptionManager;

class WPML_Reset_Wizard
{
	private $wpdb;

	public function __construct( $wpdb_instance = null ) {
		if ( null === $wpdb_instance ) {
			global $wpdb;
			$this->wpdb = $wpdb;
		} else {
			$this->wpdb = $wpdb_instance;
		}
	}

	public function execute(): array
	{
		try {
			$this->reset_languages_table();
			$this->delete_wizard_options();
			$this->delete_wizard_transients();
			$this->reset_sitepress_settings();
			$this->flush_cache();

			return [
				'success' => true
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'error' => $e->getMessage()
			];
		}
	}

	private function reset_languages_table() {
		$wpdb = $this->wpdb;

		return $wpdb->query( "UPDATE {$wpdb->prefix}icl_languages SET active = 0, major = 0" );
	}

	public function delete_wizard_options(): array
	{
		$options_to_delete = array_merge(
			$this->setup_group_options(),
			[
				'WPML_TM_Wizard_For_Manager_Current_Step',
				'WPML_TM_Wizard_For_Manager_Complete',
				'WPML_TM_Wizard_For_Admin_Complete',
				'WPML_TM_Wizard_Who_Mode',
			]
		);

		$results = [];
		foreach ($options_to_delete as $option) {
			$results[$option] = delete_option($option);
		}

		( new OptionManager() )->invalidateGroup( Option::OPTION_GROUP );

		return $results;
	}

	private function setup_group_options(): array
	{
		$blob = 'WPML(' . Option::OPTION_GROUP . ')';

		$wpdb = $this->wpdb;

		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name = %s OR option_name LIKE %s",
				$blob,
				$wpdb->esc_like( 'WPML(' . Option::OPTION_GROUP . '/' ) . '%'
			)
		);

		if ( ! is_array( $names ) || ! $names ) {
			return [ $blob ];
		}

		return in_array( $blob, $names, true ) ? $names : array_merge( [ $blob ], $names );
	}

	private function delete_wizard_transients(): array
	{
		$wpdb    = $this->wpdb;
		$results = [
			$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '%_transient_wpml_wizard%'" ),
			$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '%_transient_timeout_wpml_wizard%'" ),
		];

		return $results;
	}

	private function reset_sitepress_settings(): bool
	{
		$settings = get_option('icl_sitepress_settings', []);

		$settings['default_language'] = null;
		$settings['setup_complete'] = false;
		$settings['languages_order'] = [];
		$settings['existing_content_language_verified'] = 0;
		$settings['active_languages'] = [];

		return update_option('icl_sitepress_settings', $settings);
	}

	private function flush_cache(): bool
	{
		return wp_cache_flush();
	}
}
