<?php

namespace WPML\TM\ATE\ClonedSites\SetupMigration\Resetter;

use WPML\TM\ATE\ClonedSites\SetupMigration\SiteKeyRemoveServiceFactory;

class SiteKeyRegistrar {

	const REGISTERED = 'registered';

	const REFUSED = 'refused';

	const NOT_CHECKED = 'not_checked';

	private $factory;

	public function __construct( ?SiteKeyRemoveServiceFactory $factory = null ) {
		$this->factory = $factory ?: new SiteKeyRemoveServiceFactory();
	}

	public function register( string $siteKey ): bool {
		return self::REGISTERED === $this->registerWithOutcome( $siteKey );
	}

	public function registerWithOutcome( string $siteKey ): string {
		$result = $this->factory->createInstaller()->save_site_key( [
			'repository_id' => 'wpml',
			'nonce'         => wp_create_nonce( 'save_site_key_wpml' ),
			'site_key'      => $siteKey,
			'return'        => 1,
		] );

		if ( ! empty( $result['error'] ) ) {
			return empty( $result['key_not_checked'] ) && empty( $result['postponed'] )
				? self::REFUSED
				: self::NOT_CHECKED;
		}

		icl_set_setting( 'site_key', $siteKey, true );

		return self::REGISTERED;
	}
}
