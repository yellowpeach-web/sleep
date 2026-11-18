<?php

namespace WPML\PB\Links;

class PostMetaDataStorage implements DataStorage {

	private $dataSettings;

	public function __construct( \IWPML_Page_Builders_Data_Settings $dataSettings ) {
		$this->dataSettings = $dataSettings;
	}

	public function get( $postId, $metaKey ) {
		return $this->dataSettings->convert_data_to_array( get_post_meta( $postId, $metaKey, true ) );
	}

	public function save( $postId, $metaKey, $data ) {
		update_post_meta( $postId, $metaKey, $this->dataSettings->prepare_data_for_saving( $data ) );
	}
}
