<?php

namespace WPML\PB\BeaverBuilder;

use WPML_Beaver_Builder_Data_Settings;

trait KeepsUnpublishedDraft {

	public function save( $post_id, $original_post_id, $converted_data ) {
		$unpublishedDraft = $this->getUnpublishedDraft( $post_id );

		parent::save( $post_id, $original_post_id, $converted_data );

		if ( null !== $unpublishedDraft ) {
			update_post_meta(
				$post_id,
				WPML_Beaver_Builder_Data_Settings::META_FIELD_DRAFT_KEY,
				$this->data_settings->prepare_data_for_saving( $unpublishedDraft )
			);
		}
	}

	private function getUnpublishedDraft( $postId ) {
		$draft     = get_post_meta( $postId, WPML_Beaver_Builder_Data_Settings::META_FIELD_DRAFT_KEY, true );
		$published = get_post_meta( $postId, WPML_Beaver_Builder_Data_Settings::META_FIELD_KEY, true );

		$isPendingDraft = is_array( $draft ) && $draft && maybe_serialize( $draft ) !== maybe_serialize( $published );

		return $isPendingDraft ? $draft : null;
	}
}
