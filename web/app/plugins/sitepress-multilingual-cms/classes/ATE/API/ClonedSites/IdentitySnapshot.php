<?php

namespace WPML\TM\ATE\ClonedSites;

class IdentitySnapshot {

	const ABSENT = '__wpml_option_absent__';

	private $auth;

	private $websiteUuid;

	private $amsData;

	private $ateSiteId;

	private $siteKey;

	private function __construct( \WPML_TM_ATE_Authentication $auth, $websiteUuid, $amsData, $ateSiteId, $siteKey = null ) {
		$this->auth        = $auth;
		$this->websiteUuid = $websiteUuid;
		$this->amsData     = $amsData;
		$this->ateSiteId   = $ateSiteId;
		$this->siteKey     = $siteKey;
	}

	public static function capture( \WPML_TM_ATE_Authentication $auth ) {
		return new self(
			$auth,
			(string) $auth->get_site_id(),
			get_option( \WPML_TM_ATE_Authentication::AMS_DATA_KEY, self::ABSENT ),
			get_option( self::ateSiteIdOption(), self::ABSENT ),
			self::readSiteKey()
		);
	}

	public function websiteUuid() {
		return $this->websiteUuid;
	}

	public function restore() {
		$this->restoreOption( \WPML_TM_ATE_Authentication::AMS_DATA_KEY, $this->amsData, null );
		$this->restoreOption( self::ateSiteIdOption(), $this->ateSiteId, false );
		$this->restoreSiteKey();

		$this->auth->override_site_id( '' !== $this->websiteUuid ? $this->websiteUuid : null );
	}

	private function restoreSiteKey() {
		if ( ! function_exists( 'icl_set_setting' ) ) {
			return;
		}

		if ( self::readSiteKey() === $this->siteKey ) {
			return;
		}

		icl_set_setting( 'site_key', $this->siteKey, true );
	}

	private static function readSiteKey() {
		return function_exists( 'icl_get_setting' ) ? icl_get_setting( 'site_key' ) : null;
	}

	private function restoreOption( $option, $value, $autoload ) {
		if ( self::ABSENT === $value ) {
			delete_option( $option );

			return;
		}

		update_option( $option, $value, $autoload );
	}

	private static function ateSiteIdOption() {
		return \WPML_Site_ID::SITE_ID_KEY . ':ate';
	}
}
