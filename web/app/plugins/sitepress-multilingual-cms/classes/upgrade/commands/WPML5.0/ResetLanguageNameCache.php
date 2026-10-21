<?php

namespace WPML\Upgrade\Commands;

class ResetLanguageNameCache implements \IWPML_Upgrade_Command {

	const HOT_CACHE_OPTION    = '_icl_cache';
	const HOT_CACHE_KEY       = 'language_name_cache_class';
	const COLD_CACHE_OPTION   = '_icl_cache_language_details';
	const SHARD_OPTION_PREFIX = '_icl_cache_language_names_';

	private $sitepress;

	private $result;

	public function __construct( array $args ) {
		$this->sitepress = $args[0];
	}

	public function run_admin() {
		global $switched;

		if ( ! empty( $switched ) ) {
			$this->result = false;

			return false;
		}

		if ( false === \wpml_language_cache_rotate_epoch() ) {
			$this->result = false;

			return false;
		}

		$this->sitepress->get_language_name_cache()->clear();
		$persisted_cleared = $this->clear_persisted_cache();

		$this->result = $persisted_cleared && $this->durable_epoch_exists() && ! $this->persisted_cache_exists();

		return $this->result;
	}

	private function clear_persisted_cache() {
		$hot_cache = get_option( self::HOT_CACHE_OPTION );

		if ( is_array( $hot_cache ) && array_key_exists( self::HOT_CACHE_KEY, $hot_cache ) ) {
			unset( $hot_cache[ self::HOT_CACHE_KEY ] );
			update_option( self::HOT_CACHE_OPTION, $hot_cache );
		}

		delete_option( self::COLD_CACHE_OPTION );

		return \wpml_language_cache_delete_shards();
	}

	private function persisted_cache_exists() {
		$hot_cache = get_option( self::HOT_CACHE_OPTION );

		return ( is_array( $hot_cache ) && array_key_exists( self::HOT_CACHE_KEY, $hot_cache ) )
			|| false !== get_option( self::COLD_CACHE_OPTION )
			|| \wpml_language_cache_shards_exist();
	}

	private function durable_epoch_exists() {
		$epoch = \wpml_language_cache_get_fresh_epoch();

		return is_string( $epoch ) && '' !== $epoch;
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}
}
