<?php

namespace WPML\TM\Upgrade\Commands;

use WPML\Core\Component\Cdt\Application\Service\AutoTranslationStamps;
use WPML\Core\Component\Cdt\Application\Service\EngineOption;
use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Infrastructure\WordPress\Component\Cdt\Query\HealthStatementsQuery;
use WPML\Infrastructure\WordPress\Component\Cdt\Repository\CdtOptionStore;
use WPML\Infrastructure\WordPress\Port\Persistence\Options;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryHandler;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryPrepare;
use WPML\Legacy\Component\ATE\Application\Service\ActiveEngineQuery;
use WPML\Legacy\Component\Cdt\Query\AtePollLimitQuery;
use WPML\TM\ATE\API\CachedAMSAPI;

class BackfillCdtOptions implements \IWPML_Upgrade_Command {

	private $result = false;

	public function run_admin() {
		global $wpdb;

		$store = new CdtOptionStore( new Options() );

		( new EngineOption( new ActiveEngineQuery( self::cachedEngineListOnly() ), $store ) )->backfill();

		try {
			( new AutoTranslationStamps( $store, new HealthStatementsQuery( new QueryHandler( $wpdb ), new QueryPrepare( $wpdb ), new AtePollLimitQuery() ) ) )->backfill();
		} catch ( DatabaseErrorException $e ) {
			unset( $e );
		}

		$store->seedDefaults();

		$this->result = true;

		return $this->result;
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

	private static function cachedEngineListOnly() {
		return new class() {

			public function get_translation_engines() {
				$cache = get_transient( CachedAMSAPI::CACHE_OPTION );
				if ( ! is_array( $cache ) || ! isset( $cache['get_translation_engines'] ) || ! is_array( $cache['get_translation_engines'] ) ) {
					return null;
				}

				$list = reset( $cache['get_translation_engines'] );

				return is_array( $list ) ? $list : null;
			}
		};
	}
}
