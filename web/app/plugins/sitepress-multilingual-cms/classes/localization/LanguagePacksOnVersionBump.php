<?php

namespace WPML\Localization;

use WPML\LanguageEditor\PostAddSetup;

class LanguagePacksOnVersionBump implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const VERSION_OPTION = 'icl_sitepress_version';

	const WAITING_OPTION = 'wpml_language_packs_waiting_for_st';

	const QUEUE_PRIORITY = 20;

	private $defer;

	private $isStOutdated;

	private $bumped = false;

	public function __construct( ?callable $defer = null, ?callable $isStOutdated = null ) {
		$this->defer        = $defer;
		$this->isStOutdated = $isStOutdated;
	}

	public function add_hooks() {
		add_action( 'update_option_' . self::VERSION_OPTION, array( $this, 'onVersionRecorded' ), 10, 2 );

		add_action( 'wpml_loaded', array( $this, 'queueIfStCaughtUp' ), self::QUEUE_PRIORITY );
	}

	public function onVersionRecorded( $oldVersion, $newVersion ) {
		if ( ! is_scalar( $oldVersion ) || '' === (string) $oldVersion ) {
			return;
		}

		if ( (string) $oldVersion === (string) $newVersion ) {
			return;
		}

		if ( ! self::isSetupComplete() ) {
			return;
		}

		$this->bumped = true;

		add_action( 'wpml_loaded', array( $this, 'queue' ), self::QUEUE_PRIORITY );
	}

	public function queue() {
		if ( ! $this->bumped ) {
			return;
		}

		$this->bumped = false;

		self::queueForActiveLanguages( $this->defer, $this->isStOutdated );
	}

	public function queueIfStCaughtUp() {
		try {
			$codes = get_option( self::WAITING_OPTION, array() );

			if ( ! is_array( $codes ) || ! $codes ) {
				return false;
			}

			if ( self::stIsOutdated( $this->isStOutdated ) ) {
				return false;
			}

			delete_option( self::WAITING_OPTION );

			return self::defer( array_map( 'strval', $codes ), $this->defer );
		} catch ( \Throwable $throwable ) {
			return false;
		}
	}

	public static function queueForActiveLanguages( ?callable $defer = null, ?callable $isStOutdated = null ) {
		try {
			$codes = self::activeLanguageCodes();

			if ( ! $codes ) {
				return false;
			}

			if ( self::stIsOutdated( $isStOutdated ) ) {
				self::rememberUntilStIsUpdated( $codes );

				return false;
			}

			return self::defer( $codes, $defer );
		} catch ( \Throwable $throwable ) {
			return false;
		}
	}

	private static function defer( array $codes, ?callable $defer = null ) {
		if ( $defer ) {
			call_user_func( $defer, $codes );

			return true;
		}

		if ( class_exists( PostAddSetup::class )
			&& method_exists( PostAddSetup::class, 'deferDownloadLanguagePacks' ) ) {
			PostAddSetup::deferDownloadLanguagePacks( $codes );

			return true;
		}

		return false;
	}

	private static function rememberUntilStIsUpdated( array $codes ) {
		$waiting = get_option( self::WAITING_OPTION, array() );
		$waiting = is_array( $waiting ) ? array_map( 'strval', $waiting ) : array();

		update_option( self::WAITING_OPTION, array_values( array_unique( array_merge( $waiting, $codes ) ) ), false );
	}

	public static function holdWhileStIsOutdated( array $codes, ?callable $isStOutdated = null ) {
		try {
			if ( ! $codes || ! self::stIsOutdated( $isStOutdated ) ) {
				return false;
			}

			self::rememberUntilStIsUpdated( array_map( 'strval', $codes ) );

			return true;
		} catch ( \Throwable $throwable ) {
			return false;
		}
	}

	private static function stIsOutdated( ?callable $isStOutdated = null ) {
		if ( $isStOutdated ) {
			return (bool) call_user_func( $isStOutdated );
		}

		return class_exists( '\WPML_Plugins_Check' )
			&& method_exists( '\WPML_Plugins_Check', 'isStOutdated' )
			&& \WPML_Plugins_Check::isStOutdated();
	}

	private static function activeLanguageCodes() {
		global $sitepress;

		if ( ! $sitepress || ! method_exists( $sitepress, 'get_active_languages' ) ) {
			return array();
		}

		$active = $sitepress->get_active_languages();

		return is_array( $active ) ? array_map( 'strval', array_keys( $active ) ) : array();
	}

	private static function isSetupComplete() {
		global $sitepress;

		return (bool) ( $sitepress
			&& method_exists( $sitepress, 'is_setup_complete' )
			&& $sitepress->is_setup_complete() );
	}
}
