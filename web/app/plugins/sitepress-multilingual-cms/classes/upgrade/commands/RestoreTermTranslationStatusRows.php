<?php

namespace WPML\Upgrade\Commands;

class RestoreTermTranslationStatusRows implements \IWPML_Upgrade_Command {

	const POSITION_OPTION = 'wpml_restore_term_status_rows_position';

	const WINDOW_SIZE = 5000;

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public static function resetPosition() {
		delete_option( self::POSITION_OPTION );
	}

	public function run() {
		$wpdb = $this->schema->get_wpdb();

		$position = (int) get_option( self::POSITION_OPTION, 0 );

		$lastTrid = (int) $wpdb->get_var(
			"SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations WHERE element_type LIKE 'tax\\_%'"
		);

		if ( $position >= $lastTrid ) {
			self::resetPosition();
			$this->result = true;

			return true;
		}

		$windowEnd = $this->windowEnd( $wpdb, $position, $lastTrid );

		$this->restoreWindow( $wpdb, $position, $windowEnd );

		$this->result = $windowEnd >= $lastTrid;

		if ( $this->result ) {
			self::resetPosition();
		} else {
			update_option( self::POSITION_OPTION, $windowEnd, false );
		}

		return $this->result;
	}

	private function windowSize() {
		$size = (int) apply_filters( 'wpml_restore_term_status_rows_window', self::WINDOW_SIZE );

		return max( 1, $size );
	}

	private function restoreWindow( \wpdb $wpdb, $afterTrid, $throughTrid ) {
		$candidates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT t.translation_id
				 FROM {$wpdb->prefix}icl_translations t
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = t.element_id
				 LEFT JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = t.translation_id
				 WHERE t.element_type LIKE %s
				   AND t.source_language_code IS NOT NULL
				   AND t.element_id IS NOT NULL
				   AND ts.translation_id IS NULL
				   AND t.trid > %d AND t.trid <= %d",
				$wpdb->esc_like( 'tax_' ) . '%',
				$afterTrid,
				$throughTrid
			)
		);

		$written = 0;

		foreach ( (array) $candidates as $translationId ) {
			$written += (int) $wpdb->insert(
				$wpdb->prefix . 'icl_translation_status',
				[
					'translation_id'      => (int) $translationId,
					'status'              => ICL_TM_COMPLETE,
					'translation_service' => 'local',
					'translator_id'       => 0,
					'batch_id'            => 0,
					'needs_update'        => 0,
					'md5'                 => '',
					'translation_package' => '',
				],
				[ '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s' ]
			);
		}

		return $written;
	}

	private function windowEnd( \wpdb $wpdb, $afterTrid, $lastTrid ) {
		$pageEnd = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(trid) FROM (
					SELECT DISTINCT trid FROM {$wpdb->prefix}icl_translations
					 WHERE element_type LIKE %s AND trid > %d
					 ORDER BY trid LIMIT %d
				 ) AS page",
				$wpdb->esc_like( 'tax_' ) . '%',
				$afterTrid,
				$this->windowSize()
			)
		);

		return $pageEnd > 0 ? min( $pageEnd, $lastTrid ) : $lastTrid;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->result;
	}
}
