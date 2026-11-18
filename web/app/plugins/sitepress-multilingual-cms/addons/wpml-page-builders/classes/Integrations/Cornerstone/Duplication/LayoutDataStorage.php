<?php

namespace WPML\PB\Cornerstone\Duplication;

use WPML\PB\Cornerstone\LayoutContent;
use WPML\PB\Cornerstone\Utils;
use WPML\PB\Links\DataStorage;
use WPML\PB\Links\PostMetaDataStorage;

class LayoutDataStorage implements DataStorage {

	private $dataSettings;

	private $postMetaDataStorage;

	public function __construct( \IWPML_Page_Builders_Data_Settings $dataSettings ) {
		$this->dataSettings        = $dataSettings;
		$this->postMetaDataStorage = new PostMetaDataStorage( $dataSettings );
	}

	public function get( $postId, $metaKey ) {
		if ( ! $this->isLayoutData( $postId, $metaKey ) ) {
			return $this->postMetaDataStorage->get( $postId, $metaKey );
		}

		$post = get_post( $postId );

		return $this->dataSettings->convert_data_to_array( $post ? $post->post_content : '' );
	}

	public function save( $postId, $metaKey, $data ) {
		if ( ! $this->isLayoutData( $postId, $metaKey ) ) {
			$this->postMetaDataStorage->save( $postId, $metaKey, $data );

			return;
		}

		LayoutContent::update(
			[
				'ID'           => $postId,
				'post_content' => wp_json_encode( $data ),
			]
		);
	}

	private function isLayoutData( $postId, $metaKey ) {
		return $metaKey === $this->dataSettings->get_meta_field() && Utils::isLayoutPostType( get_post_type( $postId ) );
	}
}
