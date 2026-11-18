<?php

class WPML_TM_ATE_Post_Edit_Actions_Factory implements IWPML_Backend_Action_Loader {

	public function create() {
		$endpoints = WPML\Container\make( 'WPML_TM_ATE_AMS_Endpoints' );

		if ( WPML_TM_ATE_Status::can_ate_job_exist() ) {
			return new WPML_TM_ATE_Post_Edit_Actions( $endpoints );
		}

		return null;
	}
}
