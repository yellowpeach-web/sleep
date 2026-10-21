<?php

if ( class_exists( 'TranslationProxy_Project' ) ) {
	return;
}

require_once dirname( __FILE__ ) . '/translationproxy-api.class.php';
require_once dirname( __FILE__ ) . '/translationproxy-service.class.php';
require_once dirname( __FILE__ ) . '/translationproxy-batch.class.php';

class TranslationProxy_Project {

	public $id;
	public $access_key;
	public $ts_id;
	public $ts_access_key;

	public $service;

	public $tp_client;

	public $errors = array();

	public function __construct( $service, $delivery, WPML_TP_Client $tp_client ) {
		$this->service   = $service;
		$this->tp_client = $tp_client;

		$icl_translation_projects = TranslationProxy::get_translation_projects();
		$project_index            = self::generate_service_index( $service );

		if ( $project_index && $icl_translation_projects && isset( $icl_translation_projects [ $project_index ] ) ) {
			$project             = $icl_translation_projects[ $project_index ];
			$this->id            = $project['id'];
			$this->access_key    = $project['access_key'];
			$this->ts_id         = $project['ts_id'];
			$this->ts_access_key = $project['ts_access_key'];

			$this->service->delivery_method = $delivery;
		}
	}

	public function service() {

		return $this->service;
	}

	public static function generate_service_index( $service ) {
		$index = false;
		if ( $service ) {
			$service->custom_fields_data = isset( $service->custom_fields_data ) ? $service->custom_fields_data : array();
			if ( isset( $service->id ) ) {
				$index = md5( $service->id . serialize( $service->custom_fields_data ) );
			}
		}

		return $index;
	}

	private function service_language( $language ) {
		return TranslationProxy_Service::get_language( $this->service, $language );
	}


	public function custom_text( $location, $locale = 'en' ) {
		$response = '';
		if ( ! $this->ts_id || ! $this->ts_access_key ) {
			return '';
		}

		$params = array(
			'project_id' => $this->ts_id,
			'accesskey'  => $this->ts_access_key,
			'location'   => $location,
			'lc'         => $locale,
		);

		if ( $this->service->custom_text_url ) {
			try {
				$response = TranslationProxy_Api::service_request(
					$this->service->custom_text_url,
					$params,
					'GET',
					true,
					true,
					true
				);
			} catch ( Exception $e ) {
				throw new RuntimeException(
					'error getting custom text from Translation Service: ' . serialize( $params ) . ' url: ' . $this->service->custom_text_url,
					0,
					$e
				);
			}
		}

		return $response;
	}

	function current_service_name() {

		return TranslationProxy::get_current_service_name();
	}

	function current_service() {
		return TranslationProxy::get_current_service();
	}

	public function select_translator_iframe_url( $source_language, $target_language ) {
		$params['project_id']      = $this->ts_id;
		$params['accesskey']       = $this->ts_access_key;
		$params['source_language'] = $this->service_language( $source_language );
		$params['target_language'] = $this->service_language( $target_language );
		$params['compact']         = 1;

		return $this->_create_iframe_url( $this->service->select_translator_iframe_url, $params );
	}

	public function translator_contact_iframe_url( $translator_id ) {
		$params['project_id']    = $this->ts_id;
		$params['accesskey']     = $this->ts_access_key;
		$params['translator_id'] = $translator_id;
		$params['compact']       = 1;
		if ( $this->service->translator_contact_iframe_url ) {
			return $this->_create_iframe_url( $this->service->translator_contact_iframe_url, $params );
		}

		return false;
	}

	private function _create_iframe_url( $url, $params ) {
		if ( $params ) {
			$url  = TranslationProxy_Api::add_parameters_to_url( $url, $params );
			$url .= '?' . http_build_query( $params );
		}

			return $url;
	}


	private function get_batch_job( $source_language = null, $target_languages = null, $tp_batch_info = null ) {

		$batch_data = isset( $tp_batch_info['batchName'] )
			? \WPML\TM\TranslationProxy\TpBatchState::getBatchDataForName( $tp_batch_info['batchName'] )
			: \WPML\TM\TranslationProxy\TpBatchState::getBatchData();

		if ( ! $batch_data ) {
			if ( isset( $tp_batch_info ) ) {

				$prepareTpBatchExtraFields = function ( $extraFields ) {
					$preparedExtraFields = [];

					foreach ( $extraFields as $extraField ) {
						$preparedExtraFields[ $extraField[ 'fieldName' ] ] = $extraField[ 'fieldValue' ];
					}

					return $preparedExtraFields;
				};

				$deadline = false;

				if ( is_string( $tp_batch_info[ 'deadline' ] ) ) {
					$deadline = strtotime( $tp_batch_info[ 'deadline' ] );
				} elseif ( $tp_batch_info[ 'deadline' ] instanceof DateTime ) {
					$deadline = ( $tp_batch_info[ 'deadline' ] )->getTimestamp();
				}

				$basicBatchData = [
					'source_language'  => $source_language,
					'target_languages' => $target_languages,
					'name'             => $tp_batch_info[ 'batchName' ],
					'deadline'         => $deadline
				];

				$batchExtraFields = isset( $tp_batch_info[ 'extraFields' ] )
					? $prepareTpBatchExtraFields( $tp_batch_info[ 'extraFields' ] )
					: false;
			} else {
				return false;
			}

			$batch_data = $this->create_batch_job( $basicBatchData, $batchExtraFields );

			if ( $batch_data ) {
				\WPML\TM\TranslationProxy\TpBatchState::setBatchData( $batch_data );
			}
		}

		return $batch_data;
	}

	function get_batch_job_id( $source_language = null, $target_languages = null, $tp_batch_info = null ) {
		$ret        = false;
		$batch_data = $this->get_batch_job( $source_language, $target_languages, $tp_batch_info );

		if ( $batch_data ) {
			$ret = $batch_data->get_id();
		}

		return $ret;
	}

	public function create_batch_job( $batchData, $extraFields ) {

		if ( ! WPML_TP_Project_History::ensure_send_allowed( $this->service, $this->id, $this->access_key ) ) {
			$this->errors[] = new WP_Error( 'wpml_tp_credentials_lost', 'Translation Proxy project credentials are missing; send blocked to avoid silently creating a new project.' );
			return false;
		}

		if ( ! $batchData[ 'target_languages' ] ) {
			$batchData[ 'target_languages' ] = \WPML\TM\TranslationProxy\TpBatchState::getRemoteTargetLanguages();
		}

		if ( ! $batchData[ 'source_language' ] || ! $batchData[ 'target_languages' ] ) {
			\WPML\TM\Jobs\JobLog::addError(
				'tp_batch_create_failed',
				array(
					'has_source_language'  => (bool) $batchData['source_language'],
					'has_target_languages' => (bool) $batchData['target_languages'],
				)
			);

			return false;
		}

		if ( ! $batchData[ 'name' ] ) {
			$batchData[ 'name' ] = sprintf(
				/* translators: Name WPML gives the project it opens at the translation service. %s: the name of the site. */
				__(
					'%s: WPML Translation Jobs',
					'sitepress'
				),
				get_option( 'blogname' )
			);
		}

		\WPML\TM\TranslationProxy\TpBatchState::setBatchName( $batchData[ 'name' ] );

		return $this->tp_client->batches()->create( $batchData, $extraFields );
	}

	public function send_to_translation_batch_mode(
		$file,
		$title,
		$cms_id,
		$url,
		$source_language,
		$target_language,
		$word_count,
		$translator_id = 0,
		$note = '',
		$uuid = null,
		$tp_batch_info = null
	) {

		if ( ! WPML_TP_Project_History::ensure_send_allowed( $this->service, $this->id, $this->access_key ) ) {
			$this->errors[] = new WP_Error( 'wpml_tp_credentials_lost', 'Translation Proxy project credentials are missing; send blocked to avoid silently creating a new project.' );
			\WPML\TM\Jobs\JobLog::addError( 'tp_credentials_lost', array( 'cms_id' => $cms_id ) );
			return false;
		}

		$batch_id = $this->get_batch_job_id(
			$source_language,
			\WPML\TM\TranslationProxy\TpBatchState::getRemoteTargetLanguages() ?: null,
			$tp_batch_info
		);

		if ( ! $batch_id ) {
			$this->errors[] = new WP_Error(
				'wpml_tp_batch_unavailable',
				'Could not create or reuse the Translation Proxy batch for this send.'
			);
			\WPML\TM\Jobs\JobLog::addError( 'tp_batch_unavailable', array( 'cms_id' => $cms_id ) );

			return false;
		}

		$job_data = array(
			'file'            => $file,
			'word_count'      => $word_count,
			'title'           => $title,
			'cms_id'          => $cms_id,
			'udid'            => $uuid,
			'url'             => $url,
			'translator_id'   => $translator_id,
			'note'            => $note,
			'source_language' => $source_language,
			'target_language' => $target_language,
		);

		$tp_job = $this->tp_client->batches()->add_job( $batch_id, $job_data );

		if ( ! $tp_job ) {
			$exception       = $this->tp_client->batches()->get_exception();
			$error_message   = $exception instanceof Exception
				? $exception->getMessage()
				: 'Translation Proxy did not accept the job.';
			$this->errors[]  = new WP_Error( 'wpml_tp_add_job_failed', $error_message );

			\WPML\TM\Jobs\JobLog::addError(
				'tp_add_job_failed',
				array(
					'cms_id'   => $cms_id,
					'batch_id' => $batch_id,
					'error'    => \WPML\TM\TranslationProxy\SendTuning::sanitizeFailureReason( $error_message ),
				)
			);

			return false;
		}

		return $tp_job->get_id();
	}

	function commit_batch_job( $tp_batch_id = false, $cleanBasketNameAndBatch = false ) {
		$tp_batch_id = $tp_batch_id ? $tp_batch_id : $this->get_batch_job_id();

		if ( ! $tp_batch_id ) {
			return true;
		}

		$params = array(
			'api_version' => TranslationProxy_Api::API_VERSION,
			'project_id'  => $this->id,
			'accesskey'   => $this->access_key,
			'batch_id'    => $tp_batch_id,
		);

		$response    = TranslationProxy_Api::proxy_request( '/batches/{batch_id}/commit.json', $params, 'PUT', false );
		$basket_name = \WPML\TM\TranslationProxy\TpBatchState::getBatchName();
		if ( $basket_name ) {
			global $wpdb;

			$batch_id          = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}icl_translation_batches WHERE batch_name=%s",
				array( $basket_name )
			) );

			$batch_data = array(
				'batch_name'  => $basket_name,
				'tp_id'       => $tp_batch_id,
				'last_update' => date( 'Y-m-d H:i:s' ),
			);
			if ( isset( $response ) && $response ) {
				$batch_data['ts_url'] = serialize( $response );
			}

			if ( ! $batch_id ) {
				$wpdb->insert(
					$wpdb->prefix . 'icl_translation_batches',
					$batch_data
				);
			} else {
				$wpdb->update(
					$wpdb->prefix . 'icl_translation_batches',
					$batch_data,
					array( 'id' => $batch_id )
				);
			}
		}

		if ( $cleanBasketNameAndBatch ) {
			\WPML\TM\TranslationProxy\TpBatchState::clear();
		}

		return isset( $response ) ? $response : false;
	}

	public function jobs() {

		return $this->get_jobs( 'any' );
	}

	public function finished_jobs() {

		return $this->get_jobs( 'translation_ready' );
	}

	public function set_delivery_method( $method ) {
		$params = array(
			'project_id' => $this->id,
			'accesskey'  => $this->access_key,
			'project'    => array( 'delivery_method' => $method ),
		);
		TranslationProxy_Api::proxy_request( '/projects.json', $params, 'put' );

		return true;
	}

	public function fetch_translation( $job_id ) {
		$params = array(
			'project_id' => $this->id,
			'accesskey'  => $this->access_key,
			'job_id'     => $job_id,
		);

		return TranslationProxy_Api::proxy_download(
			'/jobs/{job_id}/xliff.json',
			$params
		);
	}

	public function update_job( $job_id, $url = null, $state = 'delivered' ) {
		$params = array(
			'job_id'     => $job_id,
			'project_id' => $this->id,
			'accesskey'  => $this->access_key,
			'job'        => array(
				'state' => $state,
			),
		);
		if ( $url ) {
			$params['job']['url'] = $url;
		}

		TranslationProxy_Api::proxy_request(
			'/jobs/{job_id}.json',
			$params,
			'PUT'
		);
	}

	private function get_jobs( $state = 'any' ) {
		$batch = \WPML\TM\TranslationProxy\TpBatchState::getBatchData();

		$params = array(
			'project_id' => $this->id,
			'accesskey'  => $this->access_key,
			'state'      => $state,
		);

		if ( $batch ) {
			$params['batch_id'] = $batch ? $batch->get_id() : false;

			return TranslationProxy_Api::proxy_request(
				'/batches/{batch_id}/jobs.json',
				$params
			);
		} else {
			$params['project_id'] = $this->id;
		}

		return TranslationProxy_Api::proxy_request( '/jobs.json', $params );
	}
}
