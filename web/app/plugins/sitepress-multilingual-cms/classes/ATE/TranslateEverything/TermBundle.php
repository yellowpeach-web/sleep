<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\TM\Jobs\FieldId;
use WPML\TM\Taxonomy\TranslatableTermMeta;

class TermBundle {

	const ELEMENT_TYPE = 'term-batch';

	const MAX_TERMS = 200;
	const MAX_WORDS = 10000;

	public static function split( array $members ) {
		$bundles = [];
		$current = [];
		$words   = 0;

		foreach ( $members as $member ) {
			$memberWords = (int) $member['words'];
			if ( $current && ( count( $current ) >= self::MAX_TERMS || $words + $memberWords > self::MAX_WORDS ) ) {
				$bundles[] = $current;
				$current   = [];
				$words     = 0;
			}
			$current[] = $member;
			$words    += $memberWords;
		}
		if ( $current ) {
			$bundles[] = $current;
		}

		return $bundles;
	}

	public static function unitId( $singleTermUnitId, $termTaxonomyId ) {
		$termTaxonomyId = (int) $termTaxonomyId;
		if ( 'term-name' === $singleTermUnitId ) {
			return FieldId::forTerm( $termTaxonomyId );
		}
		if ( 'term-description' === $singleTermUnitId ) {
			return FieldId::forTermDescription( $termTaxonomyId );
		}
		if ( 0 === strpos( $singleTermUnitId, TranslatableTermMeta::UNIT_ID_PREFIX ) ) {
			return FieldId::forTermMeta( $termTaxonomyId, substr( $singleTermUnitId, strlen( TranslatableTermMeta::UNIT_ID_PREFIX ) ) );
		}

		return $singleTermUnitId . '_' . $termTaxonomyId;
	}

	public static function singleTermUnitId( $bundleUnitId, $termTaxonomyId ) {
		$termTaxonomyId = (int) $termTaxonomyId;
		if ( FieldId::forTerm( $termTaxonomyId ) === $bundleUnitId ) {
			return 'term-name';
		}
		if ( FieldId::forTermDescription( $termTaxonomyId ) === $bundleUnitId ) {
			return 'term-description';
		}
		$metaPrefix = FieldId::TERM_META_FIELD_PREFIX;
		$metaSuffix = '-' . $termTaxonomyId;
		if ( 0 === strpos( $bundleUnitId, $metaPrefix ) && substr( $bundleUnitId, -strlen( $metaSuffix ) ) === $metaSuffix ) {
			return TranslatableTermMeta::UNIT_ID_PREFIX . substr( $bundleUnitId, strlen( $metaPrefix ), -strlen( $metaSuffix ) );
		}
		$suffix = '_' . $termTaxonomyId;
		if ( substr( $bundleUnitId, -strlen( $suffix ) ) === $suffix ) {
			return substr( $bundleUnitId, 0, -strlen( $suffix ) );
		}

		return $bundleUnitId;
	}
}
