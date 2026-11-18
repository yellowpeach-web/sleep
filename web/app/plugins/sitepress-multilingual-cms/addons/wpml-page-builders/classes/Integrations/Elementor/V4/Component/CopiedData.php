<?php

namespace WPML\PB\Elementor\V4\Component;

use WPML\PB\Elementor\DataConvert;

class CopiedData implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	const META_KEY = '_elementor_data';

	private $instanceIds;

	private $cache;

	public function __construct( InstanceIds $instanceIds, Cache $cache ) {
		$this->instanceIds = $instanceIds;
		$this->cache       = $cache;
	}

	public function add_hooks() {
		add_action(
			'wpml_pb_after_page_without_elements_post_content_copy',
			[ $this, 'convertComponentIds' ],
			\WPML_PB_Handle_Custom_Fields::PRIORITY + 1
		);
	}

	public function convertComponentIds( $postId ) {
		$data = DataConvert::unserialize( get_post_meta( $postId, self::META_KEY, true ) );
		if ( ! is_array( $data ) ) {
			return;
		}

		$converted = $this->instanceIds->convertForPost( $data, $postId );

		if ( $converted !== $data ) {
			update_post_meta( $postId, self::META_KEY, DataConvert::serialize( $converted ) );
			$this->cache->flushUsedComponents( $postId );
		}
	}
}
