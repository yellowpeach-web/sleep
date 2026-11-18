<?php

namespace WPML\TM\ATE\Handshake\Corrections;

class LocalRows {

	public function enrich( array $rows ) {
		$local = $this->read( array_map( 'intval', array_column( $rows, 'ate_id' ) ) );
		$out   = [];

		foreach ( $rows as $row ) {
			$found = isset( $local[ (int) $row['ate_id'] ] ) ? $local[ (int) $row['ate_id'] ] : null;
			$out[] = array_merge( $row, $this->facts( $row, $found ) );
		}

		return $out;
	}

	public static function pageKey( array $row ) {
		return ! empty( $row['original_id'] ) ? 'p' . (int) $row['original_id'] : 'a' . (int) $row['ate_id'];
	}

	private function facts( array $row, $found ) {
		$code = $found && '' !== (string) $found['language_code'] ? (string) $found['language_code'] : (string) $row['language'];

		$facts = [
			'job_id'        => $found ? (int) $found['job_id'] : 0,
			'local_rid'     => $found ? (int) $found['rid'] : 0,
			'original_id'   => $found ? (int) $found['original_id'] : 0,
			'title'         => '',
			'edit_link'     => '',
			'language_code' => $code,
			'language_name' => self::languageName( $code ),
			'edited'        => false,
			'deleted'       => true,
		];

		if ( ! $found ) {
			return $facts;
		}

		$facts['edited'] = (int) $found['newest_job_id'] > (int) $found['job_id'] && \WPML_TM_Editors::WP === (string) $found['newest_editor'];

		$isPost = 0 === strpos( (string) $found['element_type'], 'post_' );

		if ( $isPost ) {
			$original = (int) $found['original_id'] > 0 ? get_post( (int) $found['original_id'] ) : null;

			if ( ! $original || 'trash' === $original->post_status ) {
				return $facts;
			}

			$facts['title']   = (string) $original->post_title;
			$facts['deleted'] = false;

			$translated = (int) $found['translated_id'] > 0 ? get_post( (int) $found['translated_id'] ) : null;

			if ( $translated && 'trash' !== $translated->post_status ) {
				$facts['edit_link'] = (string) get_edit_post_link( (int) $translated->ID, 'raw' );
			}

			return $facts;
		}

		$facts['title']   = (string) $found['job_title'];
		$facts['deleted'] = (int) $found['original_id'] < 1;

		return $facts;
	}

	private static function languageName( $code ) {
		global $sitepress;

		if ( '' === $code || ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_display_language_name' ) ) {
			return $code;
		}

		$name = (string) $sitepress->get_display_language_name( $code, $sitepress->get_admin_language() );

		return '' !== $name ? $name : $code;
	}

	protected function read( array $ateJobIds ) {
		global $wpdb;

		$ateJobIds = array_values( array_unique( array_filter( $ateJobIds ) ) );

		if ( ! $ateJobIds ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT job.editor_job_id AS ate_id, job.job_id, job.rid, job.title AS job_title,
					target.language_code, target.element_type, target.element_id AS translated_id,
					original.element_id AS original_id,
					newest.job_id AS newest_job_id, newest.editor AS newest_editor
				FROM {$wpdb->prefix}icl_translate_job job
				INNER JOIN {$wpdb->prefix}icl_translation_status status ON status.rid = job.rid
				INNER JOIN {$wpdb->prefix}icl_translations target ON target.translation_id = status.translation_id
				LEFT JOIN {$wpdb->prefix}icl_translations original ON original.trid = target.trid AND original.source_language_code IS NULL
				LEFT JOIN {$wpdb->prefix}icl_translate_job newest ON newest.job_id = (
					SELECT MAX(latest.job_id) FROM {$wpdb->prefix}icl_translate_job latest WHERE latest.rid = job.rid
				)
				WHERE job.editor = %s AND job.editor_job_id IN (" . implode( ',', array_fill( 0, count( $ateJobIds ), '%d' ) ) . ')
				ORDER BY job.job_id ASC',
				array_merge( [ \WPML_TM_Editors::ATE ], $ateJobIds )
			),
			ARRAY_A
		);

		$out = [];

		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['ate_id'] ] = $row;
		}

		return $out;
	}
}
