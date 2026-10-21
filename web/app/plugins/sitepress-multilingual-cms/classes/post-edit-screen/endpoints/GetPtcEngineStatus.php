<?php

namespace WPML\TM\PostEditScreen\Endpoints;

use WPML\Ajax\Authorization\Authorized;
use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\ATE\Application\Service\PtcEngineStatus;
use WPML\FP\Right;
use WPML\LIB\WP\User;

class GetPtcEngineStatus implements IHandler, Authorized {

	public function authorize( Collection $data ) {
		$postId = (int) $data->get( 'postId' );
		if ( $postId <= 0 ) {
			return false;
		}

		return User::canManageTranslations() || current_user_can( 'edit_post', $postId );
	}

	public function run( Collection $data ) {
		return Right::of( [ 'isPtcEngine' => $this->isPtcEngine() ] );
	}

	private function isPtcEngine() {
		global $wpml_dic;

		if ( ICL_TM_TMETHOD_ATE !== (string) wpml_get_tm_sub_setting( 'doc_translation_method', ICL_TM_TMETHOD_ATE ) ) {
			return false;
		}

		if ( ! $wpml_dic ) {
			return false;
		}

		$status = $wpml_dic->make( PtcEngineStatus::class );

		return $status->isDefaultEngine();
	}
}
