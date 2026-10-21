<?php

namespace ACFML\FieldGroup;

use WPML\FP\Obj;

class AttachedPosts {


	const PROCESS_TIME_PER_POST = 0.5;

	public static function getCount( $fieldGroupId ) {
		$fieldKeys = wpml_collect( acf_get_fields( $fieldGroupId ) )
			->map( Obj::prop( 'name' ) )
			->toArray();

		return self::countByMetaKeys( $fieldKeys ) + AttachedBlockPosts::countForGroup( $fieldGroupId );
	}

	public static function countByMetaKeys( array $metaKeys ) {
		if ( ! $metaKeys ) {
			return 0;
		}

		global $wpdb;

		$translations = $wpdb->prefix . 'icl_translations';

		$count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT(m.post_id))
			FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			LEFT JOIN {$translations} t
				ON t.element_id = p.ID AND t.element_type = CONCAT('post_', p.post_type)
			WHERE m.meta_key IN (" . wpml_prepare_in( $metaKeys ) . ")
				AND p.post_type != 'revision'
				AND p.post_status NOT IN ('auto-draft', 'inherit', 'trash')
				AND ( t.translation_id IS NULL OR t.source_language_code IS NULL )"
		);

		return $count;
	}

	public static function getTranslatableImpactMessage( $postCount ) {
		/* translators: %d is the number of posts using this field group. */
		return sprintf( esc_html__( 'If any field is set to "Translatable", WPML will re-translate the %d posts using this field group.', 'acfml' ), $postCount );
	}

	public static function getProcessConfirmationMessage( $postCount ) {
		$totalTimeInSeconds = $postCount * self::PROCESS_TIME_PER_POST;

		if ( $totalTimeInSeconds <= MINUTE_IN_SECONDS ) {
			/* translators: Warning shown before a field group's translation option is changed, when the update will take under a minute. */
			return esc_html__( 'Some posts using this field group have translations. Once you change the translation option, WPML needs to update the translation status of the posts. This can take up to 1 minute.', 'acfml' );
		} elseif ( $totalTimeInSeconds > 1.5 * HOUR_IN_SECONDS ) {
			/* translators: %d is the number of hours. */
			return sprintf( esc_html__( 'Some posts using this field group have translations. Once you change the translation option, WPML needs to update the translation status of the posts. This can take up to %d hours.', 'acfml' ), ceil( $totalTimeInSeconds / HOUR_IN_SECONDS ) );
		}

		/* translators: %d is the number of minutes. */
		return sprintf( esc_html__( 'Some posts using this field group have translations. Once you change the translation option, WPML needs to update the translation status of the posts. This can take up to %d minutes.', 'acfml' ), ceil( $totalTimeInSeconds / MINUTE_IN_SECONDS ) );
	}
}
