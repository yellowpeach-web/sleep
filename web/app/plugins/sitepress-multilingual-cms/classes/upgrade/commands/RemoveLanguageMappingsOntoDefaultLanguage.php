<?php

namespace WPML\Upgrade\Commands;

use WPML\Element\API\Languages;
use WPML\FP\Obj;
use WPML\Setup\Option;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\API\CachedATEAPI;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\Upgrade\CommandsStatus;
use function WPML\Container\make;

class RemoveLanguageMappingsOntoDefaultLanguage implements \IWPML_Upgrade_Command {

	const RETRY_TRANSIENT = 'wpml_mappings_onto_default_retry';

	const RETRY_WINDOW = 300;

	const ATTEMPTS_OPTION = 'wpml_mappings_onto_default_attempts';

	const MAX_ATTEMPTS = 12;

	private $ateApi;

	private $defaultCode;

	private $result;

	private $tagBackfillPending;

	public function __construct( array $args = array() ) {
		$this->ateApi             = isset( $args[0] ) && $args[0] instanceof \WPML_TM_ATE_API ? $args[0] : null;
		$this->defaultCode        = isset( $args[1] ) && is_string( $args[1] ) ? $args[1] : null;
		$this->tagBackfillPending = isset( $args[3] ) && is_callable( $args[3] ) ? $args[3] : null;
	}

	private function run() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			$this->result = false;

			return false;
		}

		if ( $this->isTagBackfillPending() ) {
			$this->result = false;

			return false;
		}

		$defaultCode = $this->defaultCode();
		if ( '' === $defaultCode ) {
			$this->result = true;

			return true;
		}

		$this->repairStoredOption( $defaultCode );

		$this->result = AutomaticTranslationCapabilities::isAvailable()
			? $this->repairAteRows( $defaultCode )
			: true;

		return $this->result;
	}

	private function repairStoredOption( $defaultCode ) {
		try {
			$stored = (array) Option::getLanguageMappings();
		} catch ( \Throwable $e ) {
			return;
		}

		$stored = array_values( $stored );

		list( , $refused, $doubtful ) = LanguageMappings::partitionMappingsOntoDefaultLanguage( $stored, $defaultCode, array() );

		$partners    = array();
		$partnerKeys = LanguageMappings::swappedPairPartners( $stored, $refused, $defaultCode );
		foreach ( $partnerKeys as $key ) {
			$partners[] = $stored[ $key ];
		}

		$lone = array();
		foreach ( array_diff( $this->loneDefaultMappingKeys( $stored, $defaultCode ), $partnerKeys ) as $key ) {
			$lone[] = $stored[ $key ];
		}

		foreach ( array_merge( $partners, $lone, $refused ) as $mapping ) {
			Option::removeLanguageMapping( Obj::prop( 'sourceCode', $mapping ) );
		}

		LanguageMappings::logMappings(
			$refused,
			__( 'A stored language mapping onto the default language was removed.', 'sitepress' )
		);
		LanguageMappings::logMappings(
			$partners,
			__( 'A stored mapping of the default language onto a language mapped back onto it was removed.', 'sitepress' )
		);
		LanguageMappings::logMappings(
			$lone,
			__( 'A stored mapping of the default language onto one of the site\'s other languages was removed.', 'sitepress' )
		);
		LanguageMappings::logMappings( $doubtful, LanguageMappings::doubtfulMappingsLogText() );
	}

	private function repairAteRows( $defaultCode ) {
		if ( get_transient( static::RETRY_TRANSIENT ) ) {
			return false;
		}

		$api    = $this->ateApi();
		$answer = $api->get_language_mapping()->getOrElse( null );

		if ( ! LanguageMappings::isMappingList( $answer ) ) {
			return $this->retryLater();
		}

		$all      = array();
		$rowIds   = array();
		$refused  = array();
		$doubtful = array();
		foreach ( LanguageMappings::mappingRows( $answer ) as $i => $row ) {
			$id      = (int) Obj::propOr( 0, 'id', $row );
			$mapping = array(
				'sourceCode' => (string) Obj::propOr( '', 'source_code', $row ),
				'targetCode' => (string) Obj::propOr( '', 'target_code', $row ),
			);
			$verdict = LanguageMappings::ontoDefaultLanguageVerdict( $mapping['sourceCode'], $mapping['targetCode'], $defaultCode );

			$all[ $i ]    = $mapping;
			$rowIds[ $i ] = $id;
			if ( $id > 0 && LanguageMappings::VERDICT_REFUSE === $verdict ) {
				$refused[ $i ] = $mapping;
			} elseif ( LanguageMappings::VERDICT_DOUBT === $verdict ) {
				$doubtful[] = $mapping;
			}
		}

		$partners = array();
		foreach ( LanguageMappings::swappedPairPartners( $all, $refused, $defaultCode ) as $i ) {
			if ( $rowIds[ $i ] > 0 ) {
				$partners[ $i ] = $all[ $i ];
			}
		}

		$lone = array();
		foreach ( $this->loneDefaultMappingKeys( $all, $defaultCode ) as $i ) {
			if ( $rowIds[ $i ] > 0 && ! isset( $partners[ $i ] ) ) {
				$lone[ $i ] = $all[ $i ];
			}
		}

		LanguageMappings::logMappings( $doubtful, LanguageMappings::doubtfulMappingsLogText() );

		$ids = array();
		foreach ( $rowIds as $i => $id ) {
			if ( isset( $refused[ $i ] ) || isset( $partners[ $i ] ) || isset( $lone[ $i ] ) ) {
				$ids[] = $id;
			}
		}

		if ( ! $ids ) {
			return $this->done();
		}

		$removed = $api->remove_language_mapping( $ids );
		CachedATEAPI::clearAllCaches();

		if ( false === $removed ) {
			return $this->retryLater();
		}

		LanguageMappings::logMappings(
			array_values( $refused ),
			__( 'A language mapping onto the default language was removed from ATE.', 'sitepress' )
		);
		LanguageMappings::logMappings(
			array_values( $partners ),
			__( 'A mapping of the default language onto a language mapped back onto it was removed from ATE.', 'sitepress' )
		);
		LanguageMappings::logMappings(
			array_values( $lone ),
			__( 'A mapping of the default language onto one of the site\'s other languages was removed from ATE.', 'sitepress' )
		);

		return $this->done();
	}

	protected function loneDefaultMappingKeys( array $mappings, $defaultCode ) {
		return array();
	}

	private function retryLater() {
		$attempts = (int) get_option( static::ATTEMPTS_OPTION, 0 ) + 1;

		if ( $attempts >= static::MAX_ATTEMPTS ) {
			delete_option( static::ATTEMPTS_OPTION );
			LanguageMappings::logLine(
				__( 'ATE did not answer the removal of language mappings onto the default language. WPML stopped asking.', 'sitepress' ),
				array( 'attempts' => static::MAX_ATTEMPTS )
			);

			return true;
		}

		update_option( static::ATTEMPTS_OPTION, $attempts, false );
		set_transient( static::RETRY_TRANSIENT, 1, static::RETRY_WINDOW );

		return false;
	}

	private function done() {
		if ( false !== get_option( static::ATTEMPTS_OPTION, false ) ) {
			delete_option( static::ATTEMPTS_OPTION );
		}

		return true;
	}

	private function defaultCode() {
		if ( null !== $this->defaultCode ) {
			return $this->defaultCode;
		}

		try {
			return (string) Languages::getDefaultCode();
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	private function isTagBackfillPending() {
		if ( null !== $this->tagBackfillPending ) {
			return (bool) call_user_func( $this->tagBackfillPending );
		}

		return ! ( new CommandsStatus() )->hasBeenExecuted( BackfillBcp47Column::class );
	}

	private function ateApi() {
		if ( null === $this->ateApi ) {
			$this->ateApi = make( \WPML_TM_ATE_API::class );
		}

		return $this->ateApi;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}
}
