<?php
namespace WPML\WPSEO\Shared\Sitemap;

use WPML\Element\API\Languages;
use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\Languages\HreflangTag;

use function WPML\FP\invoke;
use function WPML\FP\pipe;

abstract class BaseAlternateLangHooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	protected $elementFactory;

	private $shopUrls;

	private $shopUrlsByLanguage = null;

	private $activeLanguages = null;
	private $hrefLangByLanguageCode = null;

	private $frontPageIds = null;

	const KEY = 'alternateLangs';

	public function __construct( \WPML_Translation_Element_Factory $elementFactory, ShopUrls $shopUrls ) {
		$this->elementFactory = $elementFactory;
		$this->shopUrls       = $shopUrls;
	}

	abstract protected function getUtils();

	public function addNamespace( $urlset ) {
		return str_replace( '>', ' xmlns:xhtml="http://www.w3.org/1999/xhtml">', $urlset );
	}

	private function getActiveLanguages() {
		if ( null === $this->activeLanguages ) {
			$this->activeLanguages = array_keys( Languages::getActive() );
		}

		return $this->activeLanguages;
	}

	public function addAlternateLangDataToFirstLink( $link ) {
		$link[ self::KEY ] = [];
		foreach ( $this->getActiveLanguages() as $lang ) {
			$link[ self::KEY ][ $lang ] = apply_filters( 'wpml_permalink', $link['loc'], $lang );
		}

		return $link;
	}

	protected function addAlternateLangDataToFirstLinkOfPostType( array $link, string $postType ): array {
		$shopUrls = 'product' === $postType ? $this->getShopUrlsByLanguage() : [];

		if ( isset( $link['loc'] ) && in_array( $link['loc'], $shopUrls, true ) ) {
			$link[ self::KEY ] = $shopUrls;

			return $link;
		}

		return $this->addAlternateLangDataToFirstLink( $link );
	}

	private function getShopUrlsByLanguage(): array {
		if ( null === $this->shopUrlsByLanguage ) {
			$this->shopUrlsByLanguage = class_exists( \WooCommerce::class )
				? $this->shopUrls->getByLanguage( [ $this->getUtils(), 'isIndexablePost' ] )
				: [];
		}

		return $this->shopUrlsByLanguage;
	}

	public function addAlternateLangData( $entry, $type, $obj ) {
		if ( empty( $entry ) ) {
			return $entry;
		}

		list( $elements, $getPermalink, $isIndexable ) = $this->getEntryHelpers( $type, $obj );

		if ( $elements && $getPermalink && $isIndexable ) {
			$isActiveLanguage = function ( $element, $language ) {
				return in_array( $language, $this->getActiveLanguages(), true );
			};

			$getUrl = function ( $mixed_id, $language ) use ( $getPermalink, $type ) {
				return $this->getUrl( $mixed_id, $language, $type, $getPermalink );
			};

			$isValidUrl = function ( $url ) {
				return $this->isUrl( $url );
			};

			$entry[ static::KEY ] = wpml_collect( $elements )
				->filter( $isActiveLanguage )
				->filter( $isIndexable )
				->map( $getUrl )
				->filter( $isValidUrl )
				->toArray();
		}

		return $entry;
	}

	private function getUrl( $id, $language, $type, callable $getPermalink ) {
		if ( 'post' === $type && $this->isFrontPage( $id ) ) {
			return HomeUrl::get( $language );
		}

		$permalink = $getPermalink( $id );

		if ( ! $this->isUrl( $permalink ) ) {
			return $permalink;
		}

		return apply_filters( 'wpml_permalink', $permalink, $language );
	}

	private function isUrl( $value ): bool {
		return is_string( $value ) && '' !== $value;
	}

	private function isFrontPage( $id ): bool {
		if ( ! is_numeric( $id ) ) {
			return false;
		}

		$id = (int) $id;

		if ( 0 === $id ) {
			return false;
		}

		if ( null === $this->frontPageIds ) {
			$this->frontPageIds = array_values( FrontPage::getIdsByLanguage() );
		}

		return in_array( $id, $this->frontPageIds, true );
	}

	private function getEntryHelpers( $type, $obj ) {
		switch ( $type ) {
			case 'post':
				$getElements = pipe(
					[ $this->elementFactory, 'create_post' ],
					invoke( 'get_translations' ),
					Fns::map( invoke( 'get_element_id' ) )
				);
				return [
					$getElements( $obj->ID ),
					'get_permalink',
					[ $this->getUtils(), 'isIndexablePost' ],
				];
			case 'term':
				$getElements = function ( $termTaxonomyId ) {
					$translations = $this->elementFactory->create_term( $termTaxonomyId )->get_translations();
					$result       = [];

					foreach ( $translations as $langCode => $translation ) {
						$term = get_term_by( 'term_taxonomy_id', $translation->get_element_id() );
						if ( $term ) {
							$result[ $langCode ] = $term;
						}
					}

					return $result;
				};
				return [
					$getElements( $obj->term_taxonomy_id ),
					'get_term_link',
					[ $this->getUtils(), 'isIndexableTerm' ],
				];
			case 'user':
				$getElements = function ( $userId ) {
					return array_fill_keys( $this->getActiveLanguages(), $userId );
				};
				return [
					$getElements( $obj->ID ),
					'get_author_posts_url',
					Fns::always( true ),
				];
		}
		return [ null, null, null ];
	}

	public function insertAlternateLinks( $output, $url ) {
		$alternateLangs = Obj::prop( static::KEY, $url );

		if ( $alternateLangs ) {
			$defaultLang = apply_filters( 'wpml_default_language', false );
			if ( $defaultLang && isset( $alternateLangs[ $defaultLang ] ) ) {
				$alternateLangs['x-default'] = $alternateLangs[ $defaultLang ];
			}

			$output = str_replace( '</loc>', '</loc>' . $this->getAlternateLinks( $alternateLangs ), $output );
		}

		return $output;
	}

	private function getAlternateLinks( array $alternateUrls ): string {
		$hasHrefLang = function ( $url, $languageCode ) {
			return (bool) $this->getHrefLangForLanguage( $languageCode );
		};

		$buildAlternateLink = function ( $url, $languageCode ) {
			return '<xhtml:link rel="alternate" hreflang="' . esc_attr( $this->getHrefLangForLanguage( $languageCode ) ) . '" href="' . esc_url( $url ) . '" />';
		};

		$links = wpml_collect( $alternateUrls )
			->filter( $hasHrefLang )
			->map( $buildAlternateLink )
			->implode( "\n\t\t" );

		return $links ? "\n\t\t" . $links : '';
	}

	private function getHrefLangForLanguage( string $languageCode ): string {
		if ( is_null( $this->hrefLangByLanguageCode ) ) {
			$this->hrefLangByLanguageCode = wpml_collect( (array) apply_filters( 'wpml_active_languages', null, [] ) )
				->map( function ( $language ) {
					return HreflangTag::forLanguage( (array) $language );
				} )
				->toArray();
		}

		return $this->hrefLangByLanguageCode[ $languageCode ] ?? $languageCode;
	}
}
