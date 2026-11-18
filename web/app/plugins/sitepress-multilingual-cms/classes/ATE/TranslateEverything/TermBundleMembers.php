<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Language\WpmlPresence;
use WPML\TM\Upgrade\Commands\CreateTermBatchMembersTable;

class TermBundleMembers {

	const STATE_IN_BUNDLE = 'in_bundle';
	const STATE_APPLIED   = 'applied';
	const STATE_GIVEN_UP  = 'given_up';
	const STATE_DROPPED   = 'dropped';

	const STATES = [ self::STATE_IN_BUNDLE, self::STATE_APPLIED, self::STATE_GIVEN_UP, self::STATE_DROPPED ];

	const TABLE_OPTION   = 'wpml_term_batch_members_table';
	const SCHEMA_VERSION = 2;

	public static $tableEnsured = false;

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
		self::ensureTable( $wpdb );
	}

	public static function ensureTable( \wpdb $wpdb ) {
		self::onWpmlSite( $wpdb );
	}

	private static function onWpmlSite( \wpdb $wpdb ) {
		if ( true === self::$tableEnsured ) {
			return true;
		}
		$ensured = is_array( self::$tableEnsured ) ? self::$tableEnsured : [];
		if ( isset( $ensured[ $wpdb->prefix ] ) ) {
			return $ensured[ $wpdb->prefix ];
		}
		$hasOption = self::SCHEMA_VERSION === (int) get_option( self::TABLE_OPTION );
		$onWpml    = $hasOption || false !== WpmlPresence::languagesTableOnPrefix( $wpdb );

		$ensured[ $wpdb->prefix ] = $onWpml;
		self::$tableEnsured       = $ensured;
		if ( $onWpml && ! $hasOption ) {
			self::createTable( $wpdb );
		}

		return $onWpml;
	}

	private function hasTable() {
		return self::onWpmlSite( $this->wpdb );
	}

	private static function createTable( \wpdb $wpdb ) {
		if ( CreateTermBatchMembersTable::create_table_if_not_exists( $wpdb ) && self::addMissingColumns( $wpdb ) ) {
			update_option( self::TABLE_OPTION, self::SCHEMA_VERSION, true );
		}
	}

	private static function addMissingColumns( \wpdb $wpdb ) {
		$schema = new \WPML_Upgrade_Schema( $wpdb );
		foreach ( CreateTermBatchMembersTable::COLUMNS_SINCE_V2 as $column => $attributes ) {
			if (
				! $schema->does_column_exist( CreateTermBatchMembersTable::TABLE_NAME, $column )
				&& false === $schema->add_column( CreateTermBatchMembersTable::TABLE_NAME, $column, $attributes )
			) {
				return false;
			}
		}

		return true;
	}

	private static function recreateIfMissing( \wpdb $wpdb ) {
		if ( ! preg_match( "/Table .* doesn't exist/i", (string) $wpdb->last_error ) ) {
			return false;
		}
		delete_option( self::TABLE_OPTION );
		self::createTable( $wpdb );

		return true;
	}

	public function add( $batchJobId, $targetLang, array $members, $retry = true ) {
		if ( ! $members || ! $this->hasTable() ) {
			return 0;
		}

		$args = [];
		foreach ( $members as $member ) {
			array_push(
				$args,
				(int) $batchJobId,
				(int) $member['term_taxonomy_id'],
				(int) $member['rid'],
				(string) $targetLang,
				(int) $member['words'],
				self::STATE_IN_BUNDLE,
				(int) ( $member['billed_words'] ?? $member['words'] ),
				empty( $member['billed_in_full'] ) ? 0 : 1
			);
		}

		$wpdb = $this->wpdb;

		$written = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}icl_term_batch_members (batch_job_id, term_taxonomy_id, term_rid, target_lang, words, state, billed_words, billed_in_full) VALUES "
				. implode( ', ', array_fill( 0, count( $members ), '(%d, %d, %d, %s, %d, %s, %d, %d)' ) ),
				$args[0],
				$args[1],
				$args[2],
				$args[3],
				$args[4],
				$args[5],
				$args[6],
				...array_slice( $args, 7 )
			)
		);

		if ( false === $written && $retry && self::recreateIfMissing( $wpdb ) ) {
			return $this->add( $batchJobId, $targetLang, $members, false );
		}

		return $this->rows( $written );
	}

	public function remove( $batchJobId ) {
		if ( ! $this->hasTable() ) {
			return 0;
		}
		$wpdb = $this->wpdb;

		return $this->rows(
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_term_batch_members WHERE batch_job_id = %d", (int) $batchJobId ) )
		);
	}

	public function liveBundleJobOf( $termTaxonomyId, $targetLang ) {
		if ( ! $this->hasTable() ) {
			return null;
		}
		$wpdb = $this->wpdb;
		$row  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT m.batch_job_id, m.term_rid, j.rid, j.editor_job_id FROM {$wpdb->prefix}icl_term_batch_members m"
				. " INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.job_id = m.batch_job_id"
				. ' WHERE m.term_taxonomy_id = %d AND m.target_lang = %s AND m.state = %s ORDER BY m.batch_job_id DESC LIMIT 1',
				(int) $termTaxonomyId,
				(string) $targetLang,
				self::STATE_IN_BUNDLE
			),
			ARRAY_A
		);

		return $row ? [
			'batch_job_id'  => (int) $row['batch_job_id'],
			'term_rid'      => (int) $row['term_rid'],
			'rid'           => (int) $row['rid'],
			'editor_job_id' => (int) $row['editor_job_id'],
		] : null;
	}

	public function appliedBundlesOfTermRid( $termRid ) {
		if ( ! $this->hasTable() ) {
			return [];
		}
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.batch_job_id, j.rid, ts.review_status FROM {$wpdb->prefix}icl_term_batch_members m"
				. " INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.job_id = m.batch_job_id"
				. " INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = j.rid"
				. ' WHERE m.term_rid = %d AND m.state = %s',
				(int) $termRid,
				self::STATE_APPLIED
			)
		);

		$bundles = [];
		foreach ( (array) $rows as $row ) {
			$bundles[] = [
				'batch_job_id'  => (int) $row->batch_job_id,
				'rid'           => (int) $row->rid,
				'review_status' => null === $row->review_status ? null : (string) $row->review_status,
			];
		}

		return $bundles;
	}

	public function membersOf( $batchJobId ) {
		if ( ! $this->hasTable() ) {
			return [];
		}
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_taxonomy_id, term_rid, target_lang, state, billed_words, billed_in_full FROM {$wpdb->prefix}icl_term_batch_members WHERE batch_job_id = %d",
				(int) $batchJobId
			)
		);

		$members = [];
		foreach ( (array) $rows as $row ) {
			$members[] = [
				'term_taxonomy_id' => (int) $row->term_taxonomy_id,
				'term_rid'         => (int) $row->term_rid,
				'target_lang'      => (string) $row->target_lang,
				'state'            => (string) $row->state,
				'billed_words'     => null === $row->billed_words ? null : (int) $row->billed_words,
				'billed_in_full'   => (bool) $row->billed_in_full,
			];
		}

		return $members;
	}

	public function setState( $batchJobId, array $termRids, $state ) {
		if ( ! in_array( $state, self::STATES, true ) ) {
			throw new \InvalidArgumentException( esc_html( 'Unknown term bundle member state: ' . $state ) );
		}
		if ( ! $termRids || ! $this->hasTable() ) {
			return 0;
		}

		$wpdb = $this->wpdb;
		$args = array_merge( [ $state, (int) $batchJobId ], array_map( 'intval', $termRids ) );

		return $this->rows(
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_term_batch_members SET state = %s WHERE batch_job_id = %d AND term_rid IN ("
					. implode( ', ', array_fill( 0, count( $termRids ), '%d' ) ) . ')',
					$args
				)
			)
		);
	}

	public function reopenedMembersOfRid( $rid ) {
		if ( ! $this->hasTable() ) {
			return [];
		}
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.batch_job_id, m.term_rid FROM {$wpdb->prefix}icl_term_batch_members m"
				. " INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.job_id = m.batch_job_id"
				. " INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = m.term_rid"
				. ' WHERE j.rid = %d AND m.state = %s AND ts._prevstate IS NOT NULL',
				(int) $rid,
				self::STATE_IN_BUNDLE
			)
		);

		$members = [];
		foreach ( (array) $rows as $row ) {
			$members[] = [
				'batch_job_id' => (int) $row->batch_job_id,
				'term_rid'     => (int) $row->term_rid,
			];
		}

		return $members;
	}

	public function giveUp( $batchJobId, $termRid, $reason ) {
		if ( ! $this->hasTable() ) {
			return 0;
		}
		$wpdb = $this->wpdb;

		return $this->rows(
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_term_batch_members SET state = %s, reason = %s WHERE batch_job_id = %d AND term_rid = %d",
				self::STATE_GIVEN_UP,
				(string) $reason,
				(int) $batchJobId,
				(int) $termRid
			) )
		);
	}

	public function dropTerm( $termTaxonomyId ) {
		if ( ! $this->hasTable() ) {
			return 0;
		}
		$wpdb = $this->wpdb;

		return $this->rows(
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_term_batch_members SET state = %s WHERE term_taxonomy_id = %d AND state = %s",
				self::STATE_DROPPED,
				(int) $termTaxonomyId,
				self::STATE_IN_BUNDLE
			) )
		);
	}

	public function dropRid( $termRid ) {
		if ( ! $this->hasTable() ) {
			return 0;
		}
		$wpdb = $this->wpdb;

		return $this->rows(
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_term_batch_members SET state = %s WHERE term_rid = %d AND state = %s",
				self::STATE_DROPPED,
				(int) $termRid,
				self::STATE_IN_BUNDLE
			) )
		);
	}

	public function dropTranslation( $translationId ) {
		if ( ! $this->hasTable() ) {
			return 0;
		}
		$wpdb = $this->wpdb;

		return $this->rows(
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_term_batch_members m INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = m.term_rid"
				. ' SET m.state = %s WHERE ts.translation_id = %d AND m.state = %s',
				self::STATE_DROPPED,
				(int) $translationId,
				self::STATE_IN_BUNDLE
			) )
		);
	}

	public function liveMembersOfTaxonomy( $elementType ) {
		if ( ! $this->hasTable() ) {
			return [];
		}
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.batch_job_id, m.term_rid, ts.translation_id FROM {$wpdb->prefix}icl_term_batch_members m"
				. " INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = m.term_rid"
				. " INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id"
				. ' WHERE t.element_type = %s AND m.state = %s',
				(string) $elementType,
				self::STATE_IN_BUNDLE
			)
		);

		$members = [];
		foreach ( (array) $rows as $row ) {
			$members[] = [
				'batch_job_id'   => (int) $row->batch_job_id,
				'term_rid'       => (int) $row->term_rid,
				'translation_id' => (int) $row->translation_id,
			];
		}

		return $members;
	}

	public function dropTaxonomy( $elementType ) {
		if ( ! $this->hasTable() ) {
			return 0;
		}
		$wpdb = $this->wpdb;

		return $this->rows(
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_term_batch_members m"
				. " INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = m.term_rid"
				. " INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id"
				. ' SET m.state = %s WHERE t.element_type = %s AND m.state = %s',
				self::STATE_DROPPED,
				(string) $elementType,
				self::STATE_IN_BUNDLE
			) )
		);
	}

	public function releaseWords( $batchJobId, ?array $termRids = null ) {
		if ( ! $this->hasTable() ) {
			return 0;
		}
		$wpdb = $this->wpdb;

		if ( null === $termRids ) {
			return $this->rows(
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_term_batch_members SET words = 0 WHERE batch_job_id = %d AND state = %s",
					(int) $batchJobId,
					self::STATE_IN_BUNDLE
				) )
			);
		}
		$termRids = array_values( array_unique( array_map( 'intval', $termRids ) ) );
		if ( ! $termRids ) {
			return 0;
		}

		return $this->rows(
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_term_batch_members SET words = 0 WHERE batch_job_id = %d AND term_rid IN ( "
					. implode( ', ', array_fill( 0, count( $termRids ), '%d' ) ) . ' )',
					array_merge( [ (int) $batchJobId ], $termRids )
				)
			)
		);
	}

	private function rows( $changed ) {
		return false === $changed ? 0 : (int) $changed;
	}
}
