<?php

namespace WPML\ST\DB\Mappers;

class Update {
	public static function moveStringToDomain( callable $getStringById, $stringId, $domain ) {
		global $wpdb;

		$string = $getStringById( $stringId );
		if ( ! $string ) {
			return false;
		}

		$domainNameContextMd5 = md5( $domain . $string->name . $string->gettext_context );
		$collision            = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_strings WHERE domain_name_context_md5 = %s AND id != %d",
				$domainNameContextMd5,
				$stringId
			)
		);
		if ( $collision ) {
			return false;
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'icl_strings',
			[
				'context'                 => $domain,
				'domain_name_context_md5' => $domainNameContextMd5,
			],
			[ 'id' => $stringId ]
		);
		if ( false === $updated ) {
			return false;
		}

		self::regenerateMOFiles( $string->context, $domain );

		return true;
	}

	public static function moveAllStringsToNewDomain( $oldDomain, $newDomain ) {
		global $wpdb;

		$affected = (int) $wpdb->update(
			$wpdb->prefix . 'icl_strings',
			[ 'context' => $newDomain ],
			[ 'context' => $oldDomain ]
		);

		if ( $affected ) {
			self::regenerateMOFiles( $oldDomain, $newDomain );
		}
	}

	private static function regenerateMOFiles( $oldDomain, $newDomain ) {
		do_action( 'wpml_st_refresh_domain', $oldDomain );
		do_action( 'wpml_st_refresh_domain', $newDomain );
	}
}
