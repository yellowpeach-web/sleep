<?php

use WPML\EditHistory\InternalWrite;

class WPML_TM_Editor_Save_Ajax_Action extends WPML_TM_Job_Action {

	private $data;

	public function __construct( &$job_action_factory, array $data ) {
		parent::__construct( $job_action_factory );
		$this->data = $data;
	}

	public function run() {
		try {
			$saved = \WPML\TM\Jobs\JobLog::runInGroup(
				\WPML\TM\Jobs\JobLog::GROUP_ID_LOCAL_SAVES,
				'Classic Translation Editor save',
				function () {
					return InternalWrite::run(
						InternalWrite::CLASSIC_EDITOR,
						fn() => $this->job_action_factory->save_action( $this->data )->save_translation()
					);
				}
			);

			return new WPML_Ajax_Response( true, $saved );
		} catch ( Exception $e ) {
			return new WPML_Ajax_Response( false, 0 );
		}
	}
}
