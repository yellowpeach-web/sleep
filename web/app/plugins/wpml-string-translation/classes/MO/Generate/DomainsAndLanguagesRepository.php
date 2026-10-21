<?php

namespace WPML\ST\MO\Generate;

use wpdb;
use WPML\Collect\Support\Collection;
use WPML\ST\DB\Mappers\StringsRetrieve;
use function WPML\Container\make;
use WPML\ST\TranslationFile\Domains;
use function wpml_collect;
use WPML_Locale;

class DomainsAndLanguagesRepository {
	private $wpdb;

	private $domains;

	private $locale;

	public function __construct( wpdb $wpdb, Domains $domains, WPML_Locale $wp_locale ) {
		$this->wpdb    = $wpdb;
		$this->domains = $domains;
		$this->locale  = $wp_locale;
	}


	public function get() {
		$entityKey = function ( $entity ) {
			return (string) $entity->domain . "\0" . $entity->locale;
		};

		return $this->getAllDomains()->flatMap( function ( $row ) {
			$locale   = $this->locale->get_locale( $row->languageCode );
			$entities = [
				(object) [
					'domain' => $row->domain,
					'locale' => $locale,
				],
			];

			if ( strtolower( StringsRetrieve::CONTEXT_WORDPRESS ) === strtolower( (string) $row->domain ) ) {
				$entities[] = (object) [
					'domain' => StringsRetrieve::CONTEXT_DEFAULT,
					'locale' => $locale,
				];
			}

			return $entities;
		} )->filter( function ( $entry ) {
			return false !== $entry->locale;
		} )->unique( $entityKey )->values();
	}

	private function getAllDomains() {
		$moDomains = $this->domains->getMODomains()->toArray();
		if ( ! $moDomains ) {
			return wpml_collect( [] );
		}

		$rows = ( new StringsRetrieve( $this->wpdb ) )->getDomainLanguagesWithChanges( $moDomains );

		return wpml_collect( $rows )->map(
			function ( $row ) {
				return (object) $row;
			}
		);
	}

	public static function hasTranslationFilesTable() {
		return make( \WPML_Upgrade_Schema::class )->does_table_exist( 'icl_mo_files_domains' );
	}
}
