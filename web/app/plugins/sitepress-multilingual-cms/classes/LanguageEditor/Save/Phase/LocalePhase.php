<?php

namespace WPML\LanguageEditor\Save\Phase;

class LocalePhase implements PhaseProcessor {

	const ID = 'locale';

	const STEP_DOWNLOAD = 0;
	const STEP_FLUSH    = 1;

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function getId() {
		return self::ID;
	}

	public function applies( array $change ) {
		$old = isset( $change['locale']['old'] ) ? (string) $change['locale']['old'] : '';
		$new = isset( $change['locale']['new'] ) ? (string) $change['locale']['new'] : '';
		return '' !== $new && $old !== $new && ! empty( $change['code'] );
	}

	public function getTotal( array $change ) {
		return $this->applies( $change ) ? 2 : 0;
	}

	public function getChunkSize() {
		return 1;
	}

	public function isSkippable() {
		return false;
	}

	public function processChunk( array $change, $offset ) {
		switch ( (int) $offset ) {
			case self::STEP_DOWNLOAD:
				return $this->downloadPack( $change );
			case self::STEP_FLUSH:
			default:
				return $this->flushCaches( $change );
		}
	}

	private function downloadPack( array $change ) {
		$newLocale = (string) $change['locale']['new'];

		$sitepress = $this->sitepress();
		if ( ! $sitepress || ! class_exists( 'WPML_Download_Localization' ) ) {
			return PhaseResult::ok( 1, [ [ 'item' => $newLocale, 'reason' => 'language_pack_download_unavailable' ] ] );
		}

		try {
			$active   = (array) $sitepress->get_active_languages();
			$default  = (string) $sitepress->get_default_language();
			$download = $this->downloader( $active, $default );
			$download->download_language_packs();

			$notFounds = (array) $download->get_not_founds();
			$errors    = (array) $download->get_errors();
			if ( $errors || $this->missesChangedRow( $notFounds, $change, $newLocale ) ) {
				return PhaseResult::ok( 1, [ [ 'item' => $newLocale, 'reason' => 'language_pack_not_found' ] ] );
			}
			return PhaseResult::ok( 1 );
		} catch ( \Throwable $e ) {
			return PhaseResult::ok( 1, [ [ 'item' => $newLocale, 'reason' => 'language_pack_download_failed' ] ] );
		}
	}

	private function missesChangedRow( array $notFounds, array $change, $newLocale ) {
		$code = strtolower( trim( (string) $change['code'] ) );
		foreach ( $notFounds as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$rowCode   = strtolower( trim( (string) ( isset( $row['code'] ) ? $row['code'] : '' ) ) );
			$rowLocale = (string) ( isset( $row['default_locale'] ) ? $row['default_locale'] : '' );
			if ( '' !== $rowCode ) {
				if ( $rowCode === $code ) {
					return true;
				}
				continue;
			}
			if ( '' !== $rowLocale && $rowLocale === $newLocale ) {
				return true;
			}
		}
		return false;
	}

	protected function downloader( array $active, $default ) {
		return new \WPML_Download_Localization( $active, $default );
	}

	private function flushCaches( array $change ) {
		CacheFlush::afterColumnWrite();

		return PhaseResult::ok( 1 );
	}

	private function sitepress() {
		global $sitepress;
		return ( $sitepress instanceof \SitePress ) ? $sitepress : null;
	}
}
