<?php

namespace WPML\Upgrade\Commands;

use WPML\Infrastructure\WordPress\Component\Item\Application\Query\TranslationToSendCondition;
use WPML\Infrastructure\WordPress\Port\Persistence\QueryPrepare;
use WPML\TM\Jobs\JobLog;

class RepairJoblessInFlightStatusRows implements \IWPML_Upgrade_Command {

	const PAGE_SIZE = 500;

	private $wpdb;

	private $repaired = 0;

	public function __construct( array $args ) {
		$this->wpdb = $args[0];
	}

	public function run() {
		if ( ! class_exists( JobLog::class ) ) {
			return $this->runPass();
		}

		return JobLog::runInGroup(
			JobLog::GROUP_ID_UPGRADE_COMMANDS,
			'Upgrade: repair translation statuses with no job',
			function () {
				return $this->runPass();
			}
		);
	}

	private function runPass() {
		$wpdb           = $this->wpdb;
		$this->repaired = 0;
		$toSend         = new TranslationToSendCondition( new QueryPrepare( $wpdb ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.rid AS rid, s.status AS status, t.element_id IS NULL AS no_element
				   FROM {$wpdb->prefix}icl_translation_status s
				  INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id
				  WHERE ( s.status = %d OR ( s.status = %d AND t.element_type NOT LIKE %s ) )
				    AND s.timestamp < %s
				    AND NOT EXISTS ( SELECT 1 FROM {$wpdb->prefix}icl_translate_job jobless_job WHERE jobless_job.rid = s.rid )
				  ORDER BY s.rid
				  LIMIT %d",
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS,
				$wpdb->esc_like( 'tax_' ) . '%',
				$toSend->joblessCutoff(),
				self::PAGE_SIZE
			)
		);

		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return false;
		}

		if ( ! $rows ) {
			return true;
		}

		$untranslated = [];
		$translated   = [];
		foreach ( $rows as $row ) {
			if ( (int) $row->no_element ) {
				$untranslated[] = (int) $row->rid;
			} else {
				$translated[] = (int) $row->rid;
			}
		}

		if ( $untranslated ) {
			$written = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translation_status SET status = %d
					  WHERE status IN ( %d, %d ) AND rid IN ( " . implode( ',', array_fill( 0, count( $untranslated ), '%d' ) ) . ' )',
					array_merge( [ ICL_TM_NOT_TRANSLATED, ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ], $untranslated )
				)
			);
			if ( false === $written ) {
				return false;
			}
			$this->repaired += (int) $written;
		}

		if ( $translated ) {
			$written = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translation_status SET status = %d, needs_update = 1
					  WHERE status IN ( %d, %d ) AND rid IN ( " . implode( ',', array_fill( 0, count( $translated ), '%d' ) ) . ' )',
					array_merge( [ ICL_TM_COMPLETE, ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ], $translated )
				)
			);
			if ( false === $written ) {
				return false;
			}
			$this->repaired += (int) $written;
		}

		if ( class_exists( JobLog::class ) ) {
			JobLog::add(
				'jobless_status_rows_repaired',
				[
					'not_translated' => $untranslated,
					'needs_update'   => $translated,
				]
			);
		}

		return count( $rows ) < self::PAGE_SIZE;
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
		return $this->repaired;
	}
}
