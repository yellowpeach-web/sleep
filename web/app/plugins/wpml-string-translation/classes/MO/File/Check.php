<?php

namespace WPML\ST\MO\File;

use WPML\Element\API\Languages;
use WPML\ST\MO\Hooks\CustomTextDomains;
use WPML\ST\DB\Mappers\StringsRetrieve as DbStringsRetrieve;
use WPML\ST\Storage\WpTransientPerLanguage;
use WPML\ST\TranslationFile\Domains;
use WPML\ST\TranslationFile\FileMark;
use WPML\ST\TranslationFile\Sync\TranslationUpdates;
use WPML\Utilities\Lock;
use function WPML\Container\make;

class Check {

	const BATCH = 20;

	private $manager;

	private $domains;

	private $updates;

	private $locales;

	public function __construct( Manager $manager, Domains $domains, TranslationUpdates $updates, callable $locales ) {
		$this->manager = $manager;
		$this->domains = $domains;
		$this->updates = $updates;
		$this->locales = $locales;
	}

	public static function create() {
		return new self(
			ManagerFactory::create(),
			make( Domains::class ),
			make( TranslationUpdates::class ),
			function () {
				$wpLocale = make( \WPML_Locale::class );
				$locales  = [];
				foreach ( array_keys( (array) Languages::getActive() ) as $code ) {
					$locale = $wpLocale->get_locale( $code );
					if ( $locale ) {
						$locales[] = (string) $locale;
					}
				}

				return $locales;
			}
		);
	}

	public function run( $offset ) {
		$offset = max( 0, (int) $offset );
		$lock   = make( Lock::class, [ ':name' => Manager::class ] );

		if ( ! $lock->create( Manager::REBUILD_LOCK_TIMEOUT ) ) {
			return null;
		}

		try {
			return $this->batch( $offset );
		} finally {
			$lock->release();
		}
	}

	private function batch( $offset ) {
		if ( 0 === $offset ) {
			$this->clearCaches();
		}

		$pairs  = $this->pairs();
		$result = [
			'checked' => 0,
			'rebuilt' => 0,
			'failed'  => 0,
			'next'    => null,
			'total'   => count( $pairs ),
		];

		foreach ( $this->byLocale( array_slice( $pairs, $offset, self::BATCH ) ) as $locale => $domains ) {
			$marks = $this->updates->getMarks( $domains, $locale );

			foreach ( $domains as $domain ) {
				$result['checked']++;

				$outcome = $this->check( $domain, $locale, isset( $marks[ $domain ] ) ? $marks[ $domain ] : null );
				if ( $outcome ) {
					$result[ $outcome ]++;
				}
			}
		}

		if ( $offset + self::BATCH < count( $pairs ) ) {
			$result['next'] = $offset + self::BATCH;
		}

		return $result;
	}

	private function check( $domain, $locale, $mark ) {
		if ( null === $mark ) {
			return null;
		}

		$filepath = $this->manager->getFilepath( $domain, $locale );
		$file     = FileMark::read( $filepath );

		if ( $file && null !== $file['mark'] && $file['mark'] === $mark ) {
			return null;
		}

		try {
			$written = $this->manager->add( $domain, $locale );
		} catch ( \Throwable $e ) {
			return 'failed';
		}

		if ( false !== $written ) {
			return 'rebuilt';
		}

		return $this->manager->maybeCreateSubdir() && ! is_readable( $filepath ) ? 'rebuilt' : 'failed';
	}

	private function pairs() {
		$domains = array_values( array_unique( array_map( 'strval', $this->domains->getMODomains()->toArray() ) ) );

		if ( ! in_array( DbStringsRetrieve::CONTEXT_DEFAULT, $domains, true ) && $this->manager->handles( DbStringsRetrieve::CONTEXT_DEFAULT ) ) {
			$domains[] = DbStringsRetrieve::CONTEXT_DEFAULT;
		}
		sort( $domains, SORT_STRING );

		$locales = array_values( array_unique( array_filter( array_map( 'strval', (array) call_user_func( $this->locales ) ), 'strlen' ) ) );
		sort( $locales, SORT_STRING );

		$pairs = [];
		foreach ( $locales as $locale ) {
			foreach ( $domains as $domain ) {
				$pairs[] = [ $locale, $domain ];
			}
		}

		return $pairs;
	}

	private function byLocale( array $pairs ) {
		$grouped = [];
		foreach ( $pairs as $pair ) {
			$grouped[ $pair[0] ][] = $pair[1];
		}

		return $grouped;
	}

	protected function clearCaches() {
		$index = new WpTransientPerLanguage( \WPML\ST\TranslationFile\Manager::INDEX_CACHE_ID . '_mo' );
		foreach ( (array) call_user_func( $this->locales ) as $locale ) {
			$index->delete( (string) $locale );
		}

		CustomTextDomains::clearCacheIn( new WpTransientPerLanguage( CustomTextDomains::CACHE_ID ) );

		Domains::resetCache();
	}
}
