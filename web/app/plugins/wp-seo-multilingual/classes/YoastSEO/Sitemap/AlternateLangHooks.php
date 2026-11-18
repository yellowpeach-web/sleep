<?php

namespace WPML\WPSEO\YoastSEO\Sitemap;

use WPML\Element\API\Languages;
use WPML\WPSEO\Shared\Sitemap\BaseAlternateLangHooks;
use WPML\WPSEO\YoastSEO\Utils;

class AlternateLangHooks extends BaseAlternateLangHooks {

	public function add_hooks() {
		add_filter( 'wpseo_sitemap_urlset', [ $this, 'addNamespace' ] );
		add_filter( 'wpseo_sitemap_entry', [ $this, 'addAlternateLangData' ], 10, 3 );
		add_filter( 'wpseo_sitemap_url', [ $this, 'insertAlternateLinks' ], 10, 2 );
		add_filter( 'wpseo_sitemap_post_type_first_links', [ $this, 'addAlternateLangDataToFirstLinks' ], 10, 2 );
	}

	protected function getUtils() {
		return Utils::class;
	}

	public function addAlternateLangDataToFirstLinks( $links, $postType = '' ) {
		foreach ( $links as &$link ) {
			$link = $this->addAlternateLangDataToFirstLinkOfPostType( $link, (string) $postType );
		}

		return $links;
	}

	private $pendingEntries = [];

	private $alternateEntries = null;

	public function addAlternateLangData( $entry, $type, $obj ) {
		if ( ! function_exists( 'doing_filter' ) || ! doing_filter( 'wpseo_sitemap_entry' ) ) {
			return parent::addAlternateLangData( $entry, $type, $obj );
		}
		if ( empty( $entry ) ) {
			return $entry;
		}

		$this->pendingEntries[] = [ $entry, $type, $obj ];

		return $entry;
	}

	public function insertAlternateLinks( $output, $url ) {
		if ( null === $this->alternateEntries ) {
			if ( ! $this->pendingEntries ) {
				return parent::insertAlternateLinks( $output, $url );
			}
			$this->alternateEntries = $this->buildAlternateEntries();
		}

		$location = (string) ( $url['loc'] ?? '' );
		if ( isset( $this->alternateEntries[ $location ] ) ) {
			$url = $this->alternateEntries[ $location ];
		}

		return parent::insertAlternateLinks( $output, $url );
	}

	private function buildAlternateEntries() {
		$this->primeCaches( $this->pendingEntries );

		$entries = [];
		foreach ( $this->pendingEntries as $pending ) {
			$entry = parent::addAlternateLangData( $pending[0], $pending[1], $pending[2] );
			if ( isset( $entry['loc'] ) ) {
				$entries[ $entry['loc'] ] = $entry;
			}
		}

		return $entries;
	}

	private function primeCaches( $pendingEntries ) {
		$postIds = [];
		$termIds = [];
		foreach ( $pendingEntries as $pending ) {
			if ( 'post' === $pending[1] ) {
				$postIds[] = (int) $pending[2]->ID;
			} elseif ( 'term' === $pending[1] ) {
				$termIds[] = (int) $pending[2]->term_taxonomy_id;
			}
		}
		$this->prefetchSourceElements( $postIds, $termIds );

		$elements = [];
		$trids    = [];
		foreach ( $pendingEntries as $pending ) {
			if ( 'post' === $pending[1] ) {
				$element = $this->elementFactory->create_post( $pending[2]->ID );
			} elseif ( 'term' === $pending[1] ) {
				$element = $this->elementFactory->create_term( $pending[2]->term_taxonomy_id );
			} else {
				continue;
			}

			$elementType = $element->get_wpml_element_type();
			$trid        = (int) $element->get_trid();
			if ( $elementType && $trid ) {
				$trids[ $elementType ][] = $trid;
			}
			$elements[] = [ $pending[1], $element ];
		}
		$this->primeTranslationGroups( $trids );

		$postIds = [];
		$termIds = [];
		foreach ( $elements as $elementData ) {
			list( $type, $element ) = $elementData;
			foreach ( $element->get_translations() as $translation ) {
				$id = (int) $translation->get_element_id();
				if ( ! $id ) {
					continue;
				}
				if ( 'post' === $type ) {
					$postIds[] = $id;
				} else {
					$termIds[] = $id;
				}
			}
		}
		$this->primeWordPressCaches( $postIds, $termIds );
	}

	protected function prefetchSourceElements( array $postIds, array $termIds ) {
		global $wpml_post_translations, $wpml_term_translations;

		if ( $postIds ) {
			$wpml_post_translations->prefetch_ids( array_values( array_unique( $postIds ) ) );
		}
		if ( $termIds ) {
			$wpml_term_translations->prefetch_ids( array_values( array_unique( $termIds ) ) );
		}
	}

	protected function primeTranslationGroups( array $trids ) {
		global $sitepress;

		$translations = new \WPML_Translations( $sitepress );
		foreach ( $trids as $elementType => $group ) {
			$translations->prime_cache_for_trids( array_values( array_unique( $group ) ), $elementType );
		}
	}

	protected function primeWordPressCaches( array $postIds, array $termIds ) {
		$postIds = array_values( array_unique( $postIds ) );
		$termIds = array_values( array_unique( $termIds ) );

		if ( $postIds ) {
			_prime_post_caches( $postIds, true, false );
			update_meta_cache( 'post', $postIds );
		}
		if ( $termIds ) {
			get_terms( [
				'term_taxonomy_id' => $termIds,
				'hide_empty' => false,
				'update_term_meta_cache' => true,
			] );
		}
	}
}
