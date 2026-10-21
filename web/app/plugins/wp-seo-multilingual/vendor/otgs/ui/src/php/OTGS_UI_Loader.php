<?php

class OTGS_UI_Loader {

	private $assets;

	private $store;

	public function __construct( OTGS_Assets_Store $locator = null, OTGS_UI_Assets $assets = null ) {
		if ( ! $locator || ! $assets ) {
			throw new InvalidArgumentException( 'Missing assets and assets store' );
		}

		$this->store  = $locator;
		$this->assets = $assets;
	}

	public function load() {
		add_action( 'init', array( $this, 'register' ), 1 );
	}

	public function register() {
		$this->store->add_assets_location( dirname( __FILE__ ) . '/../../dist/assets.json' );
		$this->assets->register();
	}
}
