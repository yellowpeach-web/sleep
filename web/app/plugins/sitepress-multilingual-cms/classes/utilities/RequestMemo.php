<?php

namespace WPML\Utilities;

class RequestMemo {

	const REWRITE_RULES_WRITES = [
		'add_option_rewrite_rules',
		'update_option_rewrite_rules',
		'delete_option_rewrite_rules',
		'update_option_permalink_structure',
		'update_option_woocommerce_permalinks',
		'registered_post_type',
		'registered_taxonomy',
		'wpml_st_add_string_translation',
		'wpml_st_string_updated',
		'woocommerce_attribute_added',
		'woocommerce_attribute_updated',
		'woocommerce_attribute_deleted',
		'save_post',
		'wpml_translation_update',
		'switch_blog',
	];

	private $values = [];

	private $droppedOn;

	private $armed = false;

	public function __construct( array $droppedOn = [] ) {
		$this->droppedOn = $droppedOn;
	}

	public static function forRewriteRules( array $except = [] ) {
		return new self( array_values( array_diff( self::REWRITE_RULES_WRITES, $except ) ) );
	}

	public function remember( $key, callable $build, $keep = true ) {
		if ( array_key_exists( $key, $this->values ) ) {
			return $this->values[ $key ];
		}

		$value = $build();

		if ( $keep ) {
			$this->arm();
			$this->values[ $key ] = $value;
		}

		return $value;
	}

	public function rewriteRules( $rules, callable $translate, $state = '', $keepBeforeWpLoaded = false ) {
		if ( ! is_array( $rules ) || ! $rules ) {
			return $translate( $rules );
		}

		$slot = (string) apply_filters( 'wpml_current_language', null ) . '|' . $state;

		if ( isset( $this->values[ $slot ] ) && $this->values[ $slot ][0] === $rules ) {
			return $this->values[ $slot ][1];
		}

		$translated = $translate( $rules );

		if ( $keepBeforeWpLoaded || ( function_exists( 'did_action' ) && did_action( 'wp_loaded' ) ) ) {
			$this->arm();
			$this->values[ $slot ] = [ $rules, $translated ];
		}

		return $translated;
	}

	public function forget() {
		$this->values = [];
	}

	private function arm() {
		if ( $this->armed ) {
			return;
		}
		$this->armed = true;

		foreach ( $this->droppedOn as $action ) {
			add_action( $action, [ $this, 'forget' ] );
		}
	}
}
