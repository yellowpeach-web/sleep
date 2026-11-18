<?php

namespace WPML\TM\TranslationProxy;

class SentPairInBatch {

	public static function isAlreadyInBatch( $statusRow, $batchId, $translationService ) {
		if ( ! is_object( $statusRow ) || ! $batchId || 'local' === (string) $translationService ) {
			return false;
		}

		$status = isset( $statusRow->status ) ? (int) $statusRow->status : 0;

		return in_array( $status, [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ], true )
			&& ! empty( $statusRow->tp_id ) && (int) $statusRow->tp_id > 0
			&& isset( $statusRow->batch_id ) && (int) $statusRow->batch_id === (int) $batchId
			&& isset( $statusRow->translation_service ) && (string) $statusRow->translation_service === (string) $translationService;
	}
}
