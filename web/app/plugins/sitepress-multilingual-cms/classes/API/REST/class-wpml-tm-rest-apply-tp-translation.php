<?php

use WPML\LIB\WP\User;
use WPML\TM\Jobs\Authorization\JobAuthorization;

class WPML_TM_REST_Apply_TP_Translation extends WPML_REST_Base {
	private $apply_translations;

	public function __construct( WPML_TP_Apply_Translations $apply_translations ) {
		parent::__construct( 'wpml/tm/v1' );

		$this->apply_translations = $apply_translations;
	}

	public function add_hooks() {
		$this->register_routes();
	}

	public function register_routes() {
		parent::register_route(
			'/tp/apply-translations',
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'apply_translations' ),
			)
		);
	}

	public function apply_translations( WP_REST_Request $request ) {
		\WPML\TM\Jobs\JobLog::maybeInitRequest();
		\WPML\TM\Jobs\JobLog::createNewGroup(
			\WPML\TM\Jobs\JobLog::GROUP_ID_DOWNLOAD_JOBS,
			'Applying TP translations'
		);

		try {
			$params = $request->get_json_params();

			if ( ! is_array( $params ) ) {
				return new WP_Error( 400, 'The request body must be a JSON object.', [ 'status' => 400 ] );
			}

			if ( $params ) {
				if ( ! isset( $params['original_element_id'] ) ) {
					$params = array_filter( $params, array( $this, 'validate_job' ) );
					if ( ! $params ) {
						return array();
					}
				}
			}

			if ( ! User::canManageTranslations() ) {
				if ( ! $params || isset( $params['original_element_id'] ) ) {
					return array();
				}

				$jobs_repository = wpml_tm_get_jobs_repository();

				$params = array_values( array_filter( $params, function ( $job ) use ( $jobs_repository ) {
					$jobEntity = $jobs_repository->get_job( $job['id'], $job['type'] );

					return $jobEntity instanceof WPML_TM_Job_Entity
						&& JobAuthorization::currentUserCanTranslateJobEntity( $jobEntity );
				} ) );

				if ( ! $params ) {
					return array();
				}
			}

			$applied = $this->apply_translations->apply( $params )->map( array( $this, 'map_jobs_to_array' ) );
			$failures = (array) $this->apply_translations->getLastFailures();

			\WPML\TM\Jobs\JobLog::add(
				'tp_apply_finished',
				array(
					'applied' => count( $applied ),
					'failed'  => count( $failures ),
				)
			);
			\WPML\TM\Jobs\JobLog::finishCurrentGroup();

			if ( $failures ) {
				return array(
					'applied' => $applied,
					'failed'  => $failures,
				);
			}

			return $applied;
		} catch ( \Throwable $e ) {
			\WPML\TM\Jobs\JobLog::addError( 'tp_apply_request_failed', array( 'message' => $e->getMessage() ) );
			\WPML\TM\Jobs\JobLog::finishCurrentGroup();

			if ( \WPML\WordPress\ClientSafeError::isDeliberateClientMessage( $e ) ) {
				return new WP_Error( 400, $e->getMessage(), [ 'status' => 400 ] );
			}

			return \WPML\WordPress\ClientSafeError::wpError( 'TP apply translation', $e, 400, 400 );
		}
	}

	public function get_allowed_capabilities( WP_REST_Request $request ) {
		return [ User::CAP_ADMINISTRATOR, User::CAP_MANAGE_TRANSLATIONS, User::CAP_TRANSLATE ];
	}

	public function map_jobs_to_array( WPML_TM_Job_Entity $job ) {
		return [
			'id'     => $job->get_id(),
			'type'   => $job->get_type(),
			'status' => $job->get_status(),
		];
	}

	private function validate_job( array $job ) {
		return isset( $job['id'], $job['type'] ) && \WPML_TM_Job_Entity::is_type_valid( $job['type'] );
	}
}
