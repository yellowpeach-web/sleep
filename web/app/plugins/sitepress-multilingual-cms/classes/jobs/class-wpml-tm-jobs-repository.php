<?php

use \WPML\TM\Jobs\Query\Query;
use WPML\FP\Fns;
use WPML\TM\Jobs\StringBatchTitles;
use WPML\Upgrade\TranslationStatusSchema;

class WPML_TM_Jobs_Repository {
	const TAXONOMY_LABEL_BATCH_NAME_PREFIX = StringBatchTitles::TAXONOMY_LABEL_BATCH_NAME_PREFIX;

	private $wpdb;

	private $query_builder;

	private $elements_repository;

	private $string_batch_titles;

	private $status_columns_checked = false;

	public function __construct(
		wpdb $wpdb,
		Query $query_builder,
		WPML_TM_Job_Elements_Repository $elements_repository
	) {
		$this->wpdb                = $wpdb;
		$this->query_builder       = $query_builder;
		$this->elements_repository = $elements_repository;
		$this->string_batch_titles = new StringBatchTitles( $wpdb );
	}

	public function get( WPML_TM_Jobs_Search_Params $params ) {
		$query = $this->query_builder->get_data_query( $params );
		$wpdb  = $this->wpdb;

		if ( $params->get_columns_to_select() ) {
			return $this->read(
				function () use ( $wpdb, $query ) {
					return $wpdb->get_results( $query );
				}
			);
		}

		$results = $this->read(
			function () use ( $wpdb, $query ) {
				return $wpdb->get_results( $query );
			}
		);
		return is_array( $results )
			? new WPML_TM_Jobs_Collection( array_map(
				array( $this, 'build_job_entity' ),
				$results
			  ) )
			: new WPML_TM_Jobs_Collection( [] );
	}

	public function get_or_fail( WPML_TM_Jobs_Search_Params $params ) {
		$this->wpdb->last_error = '';
		$result                 = $this->get( $params );
		$this->fail_on_statement_error( 'list' );

		return $result;
	}

	public function get_count_or_fail( WPML_TM_Jobs_Search_Params $params ) {
		$this->wpdb->last_error = '';
		$count                  = $this->get_count( $params );
		$this->fail_on_statement_error( 'count' );

		return $count;
	}

	private function read( callable $read ) {
		$result = $read();
		$error  = (string) $this->wpdb->last_error;

		if ( ! $this->status_columns_checked && false !== stripos( $error, 'Unknown column' ) ) {
			$this->status_columns_checked = true;
			$query                        = $this->wpdb->last_query;
			if ( TranslationStatusSchema::healMissingColumns( $this->wpdb ) ) {
				return $read();
			}
			$this->wpdb->last_error = $error;
			$this->wpdb->last_query = $query;
		}

		return $result;
	}

	private function fail_on_statement_error( $statement ) {
		$error = (string) $this->wpdb->last_error;
		if ( '' !== $error ) {
			\WPML\PHP\Logger\error( sprintf( 'The translation jobs %s statement failed: %s', $statement, $error ) );

			throw new \RuntimeException( 'list' === $statement ? 'The translation jobs list statement failed.' : 'The translation jobs count statement failed.' );
		}
	}

	public function get_collection( WPML_TM_Jobs_Search_Params $params ) {
		if ( $params->get_columns_to_select() ) {
			throw new \InvalidArgumentException( 'Not valid with get_columns_to_select().' );
		}

		return $this->get( $params );
	}

	public function increment_ate_sync_count( array $ateJobIds ) {
		if ( empty( $ateJobIds ) ) {
			return true;
		} else {
			$wpdb      = $this->wpdb;
			$ateJobIds = array_map( 'intval', $ateJobIds );

			return (bool) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translate_job SET ate_sync_count=ate_sync_count+1 WHERE editor_job_id IN( " . implode( ', ', array_fill( 0, count( $ateJobIds ), '%d' ) ) . ' )',
					$ateJobIds
				)
			);
		}
	}

	public function get_count( WPML_TM_Jobs_Search_Params $params ) {
		$query = $this->query_builder->get_count_query( $params );
		$wpdb  = $this->wpdb;

		return (int) $this->read(
			function () use ( $wpdb, $query ) {
				return $wpdb->get_var( $query );
			}
		);
	}

	public function exists( WPML_TM_Jobs_Search_Params $params ) {
		$query = $this->query_builder->get_exists_query( $params );
		$wpdb  = $this->wpdb;

		return (bool) $this->read(
			function () use ( $wpdb, $query ) {
				return $wpdb->get_var( $query );
			}
		);
	}

	public function get_job( $local_job_id, $job_type ) {
		$params = new WPML_TM_Jobs_Search_Params();
		$params->set_local_job_id( $local_job_id );
		$params->set_job_types( $job_type );

		$query = $this->query_builder->get_data_query( $params );
		$wpdb  = $this->wpdb;
		$data  = $this->read(
			function () use ( $wpdb, $query ) {
				return $wpdb->get_row( $query );
			}
		);
		if ( is_object( $data )  ) {
			$data = $this->build_job_entity( $data );
		}

		return $data;
	}

	private function build_job_entity( $raw_data ) {
		$types = [ WPML_TM_Job_Entity::POST_TYPE, WPML_TM_Job_Entity::PACKAGE_TYPE, WPML_TM_Job_Entity::STRING_BATCH, WPML_TM_Job_Entity::TAXONOMY_TYPE ];
		$batch = new WPML_TM_Jobs_Batch( $raw_data->local_batch_id, $raw_data->batch_name, $raw_data->tp_batch_id );

		if ( in_array( $raw_data->type, $types, true ) ) {
			$job = new WPML_TM_Post_Job_Entity(
				$raw_data->id,
				$raw_data->type,
				$raw_data->tp_id,
				$batch,
				(int) $raw_data->status,
				array( $this->elements_repository, 'get_job_elements' )
			);
			$job->set_translate_job_id( $raw_data->translate_job_id );
			$job->set_editor( $raw_data->editor );
			$job->set_completed_date( $raw_data->completed_date ? new DateTime( $raw_data->completed_date ) : null );
			$job->set_editor_job_id( $raw_data->editor_job_id );
			$job->set_automatic( $raw_data->automatic );
			$job->set_created_by_translate_everything( isset( $raw_data->created_by_translate_everything ) ? $raw_data->created_by_translate_everything : false );
			$job->set_review_status( $raw_data->review_status );
			$job->set_trid( $raw_data->trid );
			$job->set_element_type( $raw_data->element_type );
			$job->set_element_id( $raw_data->element_id );
			$job->set_element_type_prefix( $raw_data->element_type );
			$job->set_job_title( $raw_data->job_title );

		} else {
			$job = new WPML_TM_Job_Entity(
				$raw_data->id,
				$raw_data->type,
				$raw_data->tp_id,
				$batch,
				(int) $raw_data->status
			);
		}

		$job->set_original_element_id( $raw_data->original_element_id );

		$job->set_source_language( $raw_data->source_language );
		$job->set_target_language( $raw_data->target_language );
		$job->set_translation_service( $raw_data->translation_service );
		$job->set_sent_date( new DateTime( $raw_data->sent_date ) );
		$job->set_deadline( $this->get_deadline( $raw_data ) );
		$job->set_translator_id( $raw_data->translator_id );
		$job->set_revision( $raw_data->revision );
		$job->set_ts_status( $raw_data->ts_status );
		$job->set_needs_update( $raw_data->needs_update );
		$job->set_has_completed_translation( $raw_data->has_completed_translation );
		$job->set_title( $this->get_job_title( $raw_data ) );

		return $job;
	}

	private function get_job_title( $raw_data ) {
		if ( WPML_TM_Job_Entity::STRING_BATCH === $raw_data->type ) {
			return $this->string_batch_titles->title( (int) $raw_data->original_element_id, (string) $raw_data->title );
		}

		return $raw_data->title;
	}

	private function get_deadline( $raw_data ) {
		if ( WPML_TM_Job_Deadline::is_set( $raw_data->deadline_date ) ) {
			return new DateTime( $raw_data->deadline_date );
		}

		return null;
	}
}
