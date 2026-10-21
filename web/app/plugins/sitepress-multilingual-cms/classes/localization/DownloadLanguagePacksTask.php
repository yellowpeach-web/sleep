<?php

namespace WPML\Localization;

use WPML\BackgroundTask\AbstractTaskEndpoint;
use WPML\Collect\Support\Collection;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\FP\Obj;
use WPML\LanguageEditor\PostAddSetup;

class DownloadLanguagePacksTask extends AbstractTaskEndpoint {

	const LOCK_TIME = 2 * 60;

	const MAX_RETRIES = 3;

	const UNIT_BUDGET_SECONDS = 10;

	public function runBackgroundTask( BackgroundTask $task ) {
		$payload  = $task->getPayload();
		$requests = $this->packRequests();

		$passStartedAt = (float) Obj::propOr( 0, 'passStartedAt', $payload );
		if ( self::newestRequest( $requests ) > $passStartedAt ) {
			$passStartedAt              = $this->now();
			$payload['coreDone']        = false;
			$payload['coreDoneCodes']   = array();
			$payload['donePlugins']     = false;
			$payload['pluginPacksDone'] = array();
		}

		$langsByCode = $this->activeLanguagesByCode( array_keys( $requests ) );
		$plugins     = $this->activePlugins();

		$task->setTotalCount( self::unitsFor( $langsByCode, $plugins ) );

		$coreDoneCodes = (array) Obj::propOr( array(), 'coreDoneCodes', $payload );
		$coreDone = (bool) Obj::propOr( false, 'coreDone', $payload )
			|| self::coreDoneFor( $langsByCode, $coreDoneCodes );
		$pluginsDone = true === Obj::propOr( false, 'donePlugins', $payload );
		$pluginPacksDone = (array) Obj::propOr( array(), 'pluginPacksDone', $payload );
		$notFounds       = (array) Obj::propOr( array(), 'notFounds', $payload );

		if ( $langsByCode && ! $coreDone ) {
			$results = null;
			$remaining = array_diff_key( $langsByCode, array_flip( $coreDoneCodes ) );
			$finished  = array_keys( $remaining );

			TranslationsApiBreaker::arm();
			try {
				$downloader = $this->downloaderFor( $remaining );
				$downloader->set_deadline( $this->now() + self::UNIT_BUDGET_SECONDS );
				$results = $downloader->download_core_language_packs();

				if ( $downloader->was_cut_short() ) {
					$finished = (array) $downloader->get_processed_language_codes();
				}

				$notFounds = self::withNotFounds( $notFounds, $downloader->get_not_founds() );
			} finally {
				TranslationsApiBreaker::disarm();
			}

			$coreDoneCodes = array_values( array_unique( array_merge( $coreDoneCodes, $finished ) ) );
			$coreDone      = self::coreDoneFor( $langsByCode, $coreDoneCodes );

			if ( false === $results ) {
				$coreDone    = true;
				$pluginsDone = true;
			}

			$this->progressed( $task );
		} elseif ( $langsByCode && $plugins && ! $pluginsDone ) {
			TranslationsApiBreaker::arm();
			try {
				$downloader = $this->downloaderFor( $langsByCode );
				$downloader->set_skipped_plugin_packs( $pluginPacksDone );
				$downloader->set_deadline( $this->now() + self::UNIT_BUDGET_SECONDS );
				$downloader->download_plugin_translations_bulk();

				$pluginPacksDone = array_values(
					array_unique( array_merge( $pluginPacksDone, (array) $downloader->get_processed_plugin_packs() ) )
				);
				$pluginsDone = ! $downloader->was_cut_short();
			} finally {
				TranslationsApiBreaker::disarm();
			}

			$this->progressed( $task );
		}

		$payload['passStartedAt']   = $passStartedAt;
		$payload['coreDone']        = $coreDone;
		$payload['coreDoneCodes']   = $coreDoneCodes;
		$payload['donePlugins']     = $pluginsDone;
		$payload['pluginPacksDone'] = $pluginPacksDone;
		$payload['notFounds']       = $notFounds;

		$task->setPayload( $payload );
		$task->setCompletedCount(
			count( array_intersect( $coreDoneCodes, array_keys( $langsByCode ) ) )
			+ ( ( $pluginsDone && $plugins ) ? 1 : 0 )
		);

		if ( $langsByCode && ( ! $coreDone || ( $plugins && ! $pluginsDone ) ) ) {
			return $task;
		}

		if ( $this->takeCoveredPackRequests( $passStartedAt ) ) {
			$payload['passStartedAt']   = $this->now();
			$payload['coreDone']        = false;
			$payload['coreDoneCodes']   = array();
			$payload['donePlugins']     = false;
			$payload['pluginPacksDone'] = array();

			$task->setPayload( $payload );
			$task->setCompletedCount( 0 );

			return $task;
		}

		TranslationsApiBreaker::arm();
		try {
			$this->refreshMissingPacksNotice( $langsByCode, array_values( $notFounds ) );
		} finally {
			TranslationsApiBreaker::disarm();
		}

		$this->applyPendingSiteLocale();

		$task->finish();

		return $task;
	}

	public function getDescription( Collection $data ) {
		$codes  = array_keys( $this->packRequests() );
		$active = $this->activeLanguagesByCode( $codes );
		$names  = array();

		foreach ( $codes as $code ) {
			$language = isset( $active[ $code ] ) ? $active[ $code ] : array();

			if ( ! empty( $language['display_name'] ) ) {
				$names[] = $language['display_name'];
			} elseif ( ! empty( $language['english_name'] ) ) {
				$names[] = $language['english_name'];
			} else {
				$names[] = $code;
			}
		}

		if ( ! $names ) {
			$names[] = __( 'the added languages', 'sitepress' );
		}

		return sprintf(
			/* translators: %s is a comma-separated list of language names. */
			__( 'Downloading language packs for %s (WordPress and active plugins)…', 'sitepress' ),
			implode( ', ', $names )
		);
	}

	public function getTotalRecords( Collection $data ) {
		return self::unitsFor(
			$this->activeLanguagesByCode( array_keys( $this->packRequests() ) ),
			$this->activePlugins()
		);
	}

	private static function unitsFor( array $langsByCode, array $plugins ) {
		return count( $langsByCode ) + ( $plugins ? 1 : 0 );
	}

	private static function coreDoneFor( array $langsByCode, array $coreDoneCodes ) {
		return ! array_diff( array_keys( $langsByCode ), $coreDoneCodes );
	}

	public function isDisplayed() {
		return true;
	}

	private function progressed( BackgroundTask $task ) {
		$task->setRetryCount( 0 );
	}

	protected function packRequests() {
		return PostAddSetup::packRequests();
	}

	protected function takeCoveredPackRequests( $passStartedAt ) {
		return PostAddSetup::takeCoveredPackRequests( $passStartedAt );
	}

	protected function activeLanguagesByCode( array $codes ) {
		return PostAddSetup::activeLanguagesByCode( $codes );
	}

	protected function activePlugins() {
		return \WPML_Download_Localization::get_active_plugins();
	}

	protected function downloaderFor( array $langsByCode ) {
		return PostAddSetup::downloaderFor( $langsByCode );
	}

	protected function refreshMissingPacksNotice( array $langsByCode, array $notFounds ) {
		PostAddSetup::refreshMissingPacksNotice( $langsByCode, $notFounds );
	}

	protected function applyPendingSiteLocale() {
		PostAddSetup::applyPendingSiteLocale();
	}

	protected function now() {
		return microtime( true );
	}

	private static function newestRequest( array $requests ) {
		$times = array_map( 'floatval', array_values( $requests ) );

		return $times ? (float) max( $times ) : 0.0;
	}

	private static function withNotFounds( array $notFounds, array $found ) {
		foreach ( $found as $index => $language ) {
			$key = isset( $language['code'] ) ? (string) $language['code'] : $index;

			$notFounds[ $key ] = $language;
		}

		return $notFounds;
	}
}
