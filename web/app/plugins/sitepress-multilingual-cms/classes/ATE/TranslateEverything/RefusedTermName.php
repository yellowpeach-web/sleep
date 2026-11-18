<?php

namespace WPML\TM\ATE\TranslateEverything;

class RefusedTermName {

	const NAME_MD5_META_PREFIX = '_wpml_tax_refused_name_md5_';

	const REASON_META_PREFIX = '_wpml_tax_refused_reason_';

	const ALIAS = 'refused_name';

	public static function record( $sourceTermId, $sourceName, $targetLang, $reason ) {
		if ( ! function_exists( 'update_term_meta' ) || ! $sourceTermId || '' === (string) $targetLang ) {
			return;
		}

		update_term_meta( (int) $sourceTermId, self::NAME_MD5_META_PREFIX . $targetLang, md5( (string) $sourceName ) );
		update_term_meta( (int) $sourceTermId, self::REASON_META_PREFIX . $targetLang, (string) $reason );
	}

	public static function clear( $sourceTermId, $targetLang ) {
		if ( ! function_exists( 'delete_term_meta' ) || ! $sourceTermId || '' === (string) $targetLang ) {
			return;
		}

		delete_term_meta( (int) $sourceTermId, self::NAME_MD5_META_PREFIX . $targetLang );
		delete_term_meta( (int) $sourceTermId, self::REASON_META_PREFIX . $targetLang );
	}

	public static function joinSql( string $prefix, string $originalAlias, string $languagesAlias ): string {
		$alias = self::ALIAS;

		return "
			LEFT JOIN {$prefix}term_taxonomy {$alias}_tt
				ON {$alias}_tt.term_taxonomy_id = {$originalAlias}.element_id
			LEFT JOIN {$prefix}terms {$alias}_term
				ON {$alias}_term.term_id = {$alias}_tt.term_id
			LEFT JOIN {$prefix}termmeta {$alias}
				ON {$alias}.term_id = {$alias}_tt.term_id
				AND {$alias}.meta_key = CONCAT( '" . self::NAME_MD5_META_PREFIX . "', {$languagesAlias}.code )
		";
	}

	public static function whereSql(): string {
		$alias = self::ALIAS;

		return "AND ( {$alias}.meta_id IS NULL OR {$alias}.meta_value <> MD5( {$alias}_term.name ) )";
	}
}
