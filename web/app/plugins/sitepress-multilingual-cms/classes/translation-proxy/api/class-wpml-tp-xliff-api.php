<?php

class WPML_TP_XLIFF_API extends WPML_TP_API {
	private $xliff_parser;

	public function __construct(
		WPML_TP_API_Client $client,
		WPML_TP_Project $project,
		WPML_TP_API_Log_Interface $logger,
		WPML_TP_Xliff_Parser $xliff_parser
	) {
		parent::__construct( $client, $project, $logger );
		$this->xliff_parser = $xliff_parser;
	}

	public function get_remote_translations( $tp_job_id, $parse = true ) {
		$request = new WPML_TP_API_Request( '/jobs/{job_id}/xliff.json' );
		$request->set_timeout( \WPML\TM\TranslationProxy\SendTuning::receiveCallTimeoutSeconds() );
		$request->set_params(
			array(
				'job_id'    => $tp_job_id,
				'accesskey' => $this->project->get_access_key(),
			)
		);

		$result = $this->client->send_request( $request );
		if ( empty( $result ) || false === strpos( $result, 'xliff' ) ) {
			\WPML\TM\Jobs\JobLog::addError( 'tp_xliff_fetch_failed', array( 'tp_job_id' => $tp_job_id ) );

			throw new WPML_TP_API_Exception( 'XLIFF file could not be fetched for tp_job: ' . $tp_job_id, $request );
		}

		\WPML\TM\Jobs\JobLog::add(
			'tp_xliff_downloaded',
			array(
				'tp_job_id' => $tp_job_id,
				'bytes'     => strlen( $result ),
			)
		);

		$result = apply_filters( 'wpml_tm_data_from_pro_translation', $result );
		if ( ! $parse ) {
			return $result;
		}

		$xliff = @simplexml_load_string( $result );
		if ( ! $xliff ) {
			\WPML\TM\Jobs\JobLog::addError( 'tp_xliff_parse_failed', array( 'tp_job_id' => $tp_job_id ) );

			throw new WPML_TP_API_Exception( 'XLIFF file could not be parsed.' );
		}

		return $this->xliff_parser->parse( $xliff );
	}
}
