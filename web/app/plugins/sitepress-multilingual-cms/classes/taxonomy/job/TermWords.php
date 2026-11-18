<?php

namespace WPML\TM\Taxonomy\Job;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\Count;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\Diff;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Ideogram;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Letter;
use WPML\Core\Component\WordsToTranslate\Domain\Calculator\WordsToTranslate;
use WPML\Core\Component\WordsToTranslate\Domain\LastTranslation;
use WPML\Core\Component\WordsToTranslate\Domain\Taxonomy\TaxonomyTerm;
use WPML\Infrastructure\WordPress\Component\WordsToTranslate\Domain\Calculator\PrepareContent\Rules\Shortcode;
use WPML\Legacy\Component\WordsToTranslate\Domain\BareUrl;
use WPML\TM\Dashboard\Taxonomy\TaxonomyDashboardData;
use WPML\TM\OwnedPackages\OwnerJobService;
use WPML\TM\Taxonomy\TranslatableTermMeta;

class TermWords {

	const SENT_FIELDS_META_PREFIX = '_wpml_tax_sent_fields_';

	const DELIVERED_FIELDS_META_PREFIX = '_wpml_tax_delivered_fields_';

	private static $calculator = null;

	public static function textOf( string $name, string $description, array $metaValues ): string {
		$text     = $name . "\n" . $description;
		$metaText = implode( "\n", $metaValues );

		return '' === $metaText ? $text : $text . "\n" . $metaText;
	}

	public static function rememberSent( \WP_Term $term, string $lang ) {
		update_term_meta( (int) $term->term_id, self::SENT_FIELDS_META_PREFIX . $lang, (string) json_encode( self::fields( $term ) ) );
	}

	public static function rememberDelivered( int $termId, string $lang ) {
		$sent = (string) get_term_meta( $termId, self::SENT_FIELDS_META_PREFIX . $lang, true );
		if ( '' !== $sent ) {
			update_term_meta( $termId, self::DELIVERED_FIELDS_META_PREFIX . $lang, $sent );
		}
		delete_term_meta( $termId, self::SENT_FIELDS_META_PREFIX . $lang );
	}

	public static function decodeBaseline( string $deliveredJson, string $oldCopy ) {
		if ( '' !== $deliveredJson ) {
			$fields = json_decode( $deliveredJson, true );
			if ( is_array( $fields ) ) {
				return array_map( 'strval', $fields );
			}
		}

		return $oldCopy;
	}

	public static function baseline( int $termId, string $lang ) {
		return self::decodeBaseline(
			(string) get_term_meta( $termId, self::DELIVERED_FIELDS_META_PREFIX . $lang, true ),
			(string) get_term_meta( $termId, TaxonomyDashboardData::WC_SNAPSHOT_META_PREFIX . $lang, true )
		);
	}

	public static function countChanged( int $termTaxonomyId, string $taxonomy, array $fields, string $text, $baseline, string $lang ): int {
		if ( '' === $baseline || [] === $baseline ) {
			return self::count( $fields, $lang );
		}

		$term = new TaxonomyTerm( $termTaxonomyId, 0, $taxonomy, strtolower( $lang ) );
		$term->setContent( $text );
		$term->setFieldContents( $fields );
		$last = new LastTranslation( '' );
		if ( is_array( $baseline ) ) {
			$last->setOriginalFieldContents( $baseline );
			$last->setOriginalContent( implode( "\n", $baseline ) );
		} else {
			$last->setOriginalContent( $baseline );
		}
		self::calculator()->forLastTranslation( $last, $term );

		return (int) $last->getWordsToTranslate();
	}

	public static function fields( \WP_Term $term ): array {
		return self::fieldsOf(
			(string) $term->name,
			(string) $term->description,
			TranslatableTermMeta::values( $term ),
			(int) $term->term_taxonomy_id
		);
	}

	public static function fieldsOf( string $name, string $description, array $metaValues, int $termTaxonomyId = 0, ?array $ownedStrings = null ): array {
		$fields = [
			'name'        => $name,
			'description' => $description,
		];
		foreach ( $metaValues as $key => $value ) {
			if ( ! BareUrl::matches( (string) $value ) ) {
				$fields[ 'meta:' . $key ] = (string) $value;
			}
		}
		if ( null === $ownedStrings && $termTaxonomyId > 0 ) {
			$ownedStrings = OwnerJobService::ownedStrings( 'term', $termTaxonomyId );
		}
		foreach ( (array) $ownedStrings as $owned ) {
			$fields[ 'string:' . (int) $owned[1]->id ] = (string) $owned[1]->value;
		}

		return $fields;
	}

	public static function count( array $fields, string $lang ): int {
		return self::calculator()->forFieldContents( $fields, strtolower( $lang ) );
	}

	public static function countTerm( \WP_Term $term, ?string $sourceLang = null ): int {
		if ( null === $sourceLang || '' === $sourceLang ) {
			$sourceLang = apply_filters(
				'wpml_element_language_code',
				null,
				[
					'element_id'   => (int) $term->term_taxonomy_id,
					'element_type' => 'tax_' . $term->taxonomy,
				]
			);
			$sourceLang = (string) ( $sourceLang ?: apply_filters( 'wpml_default_language', null ) );
		}

		return self::count( self::fields( $term ), $sourceLang );
	}

	public static function calculator(): WordsToTranslate {
		if ( null === self::$calculator ) {
			$dic = $GLOBALS['wpml_dic'] ?? null;
			if ( is_object( $dic ) && method_exists( $dic, 'make' ) ) {
				self::$calculator = $dic->make( WordsToTranslate::class );
			} else {
				$shortcode        = new Shortcode();
				self::$calculator = new WordsToTranslate(
					new Diff(),
					new Count(),
					new Letter\PrepareContent( new Letter\Rules( $shortcode ), new Letter\Splitter() ),
					new Ideogram\PrepareContent( new Ideogram\Rules( $shortcode ), new Ideogram\Splitter() )
				);
			}
		}

		return self::$calculator;
	}
}
