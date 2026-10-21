<?php


namespace WPML\ST\MO\Hooks;


use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\ST\DB\Mappers\StringsRetrieve;
use WPML\ST\MO\File\Manager;
use WPML\ST\MO\Generate\DomainsAndLanguagesRepository;
use function WPML\Container\make;
use function WPML\FP\pipe;

class StringsLanguageChanged implements \IWPML_Action {

	private $domainsAndLanguageRepository;
	private $manager;
	private $getDomainsByStringIds;

	private $localeOfLanguage;

	private $queuedStrings = [];

	private $queuedLanguages = [];

	public function __construct(
		DomainsAndLanguagesRepository $domainsAndLanguageRepository,
		Manager $manager,
		callable $getDomainsByStringIds,
		?callable $localeOfLanguage = null
	) {
		$this->domainsAndLanguageRepository = $domainsAndLanguageRepository;
		$this->manager                      = $manager;
		$this->getDomainsByStringIds        = $getDomainsByStringIds;
		$this->localeOfLanguage             = $localeOfLanguage;
	}


	public function add_hooks() {
		add_action( 'wpml_st_language_of_strings_changed', [ $this, 'queue' ], 10, 2 );
	}

	public function queue( $strings, $languages = [] ) {
		$this->queuedStrings   = array_merge( $this->queuedStrings, array_map( 'intval', (array) $strings ) );
		$this->queuedLanguages = array_merge( $this->queuedLanguages, array_map( 'strval', (array) $languages ) );

		if ( ! has_action( 'shutdown', [ $this, 'processQueue' ] ) ) {
			add_action( 'shutdown', [ $this, 'processQueue' ] );
		}
	}

	public function processQueue() {
		remove_action( 'shutdown', [ $this, 'processQueue' ] );

		$strings   = array_values( array_unique( $this->queuedStrings ) );
		$languages = array_values( array_unique( $this->queuedLanguages ) );

		$this->queuedStrings   = [];
		$this->queuedLanguages = [];

		if ( $strings ) {
			$this->regenerateMOFiles( $strings, $languages );
		}
	}

	public function regenerateMOFiles( array $strings, array $languages = [] ) {
		$stringDomains = call_user_func( $this->getDomainsByStringIds, $strings );

		$pairs = $this->domainsAndLanguageRepository
			->get()
			->filter( pipe( Obj::prop( 'domain' ), Lst::includes( Fns::__, $stringDomains ) ) )
			->values();

		foreach ( $this->localesOf( $languages ) as $locale ) {
			foreach ( $stringDomains as $domain ) {
				$pairs->push( (object) [ 'domain' => $domain, 'locale' => $locale ] );

				if ( strtolower( StringsRetrieve::CONTEXT_WORDPRESS ) === strtolower( (string) $domain ) ) {
					$pairs->push( (object) [ 'domain' => StringsRetrieve::CONTEXT_DEFAULT, 'locale' => $locale ] );
				}
			}
		}

		$pairs
			->unique(
				function ( $pair ) {
					return (string) $pair->domain . "\0" . $pair->locale;
				}
			)
			->each(
				function ( $domainLangPair ) {
					$this->manager->add( $domainLangPair->domain, $domainLangPair->locale );
				}
			);
	}

	private function localesOf( array $languages ) {
		if ( ! $languages ) {
			return [];
		}

		$localeOf = $this->localeOfLanguage;
		if ( ! $localeOf ) {
			$wpmlLocale = make( \WPML_Locale::class );
			$localeOf   = function ( $language ) use ( $wpmlLocale ) {
				return $wpmlLocale->get_locale( $language );
			};
		}
		$locales = [];
		foreach ( array_unique( $languages ) as $language ) {
			$locale = call_user_func( $localeOf, $language );
			if ( $locale ) {
				$locales[] = (string) $locale;
			}
		}

		return array_values( array_unique( $locales ) );
	}
}
