<?php

use WPML\PB\Cornerstone\Utils;
use WPML\PB\TranslationJob\Groups;

class WPML_Cornerstone_Register_Strings extends WPML_Page_Builders_Register_Strings {

	const IMAGE_ELEMENT_TYPES = [ 'image', Utils::MODULE_TYPE_PREFIX . 'image' ];

	protected function get_data_to_register( WP_Post $post ) {
		if ( Utils::isLayoutPostType( $post->post_type ) ) {
			return $this->data_settings->convert_data_to_array( $post->post_content );
		}

		return parent::get_data_to_register( $post );
	}

	protected function register_strings_for_modules( array $data_array, array $package ) {
		foreach ( $data_array as $data ) {
			if ( isset( $data['_type'] ) && ! Utils::typeIsLayout( $data['_type'] ) ) {
				$this->register_strings_for_node( Utils::getNodeId( $data ), $data, $package );
				if ( Utils::shouldCheckForSubmodules( $data['_type'] ) ) {
					$this->register_strings_for_modules( $data, $package );
				}
			} elseif ( is_array( $data ) ) {
				if ( isset( $data['_type'] ) ) {
					$this->register_strings_for_node( Utils::getLayoutNodeId( $data ), $data, $package );
				}
				$this->register_strings_for_modules( $data, $package );
			}
		}
	}

	protected function filter_string_to_register( WPML_PB_String $string, $node_id, $element, $package ) {
		$element_type = is_array( $element ) && isset( $element['_type'] ) ? (string) $element['_type'] : '';
		if ( ! $element_type ) {
			return $string;
		}

		if ( ! Groups::isGroupLabel( $string->get_title() ) ) {
			$string->set_title( Groups::buildGroupLabel( [ self::getElementLabel( $element_type ) ], $string->get_title() ) );
		}

		$image_id = self::getImageId( $element_type, $element );
		if ( $image_id ) {
			$string->set_title( Groups::appendImageIdToGroupLabel( $string->get_title(), $image_id ) );
		}

		return $string;
	}

	private static function getElementLabel( $elementType ) {
		$typeWithoutPrefix = preg_replace( '/^' . Utils::MODULE_TYPE_PREFIX . '/', '', $elementType );

		return ucwords( str_replace( [ '-', '_' ], ' ', $typeWithoutPrefix ) );
	}

	private static function getImageId( $elementType, array $element ) {
		if ( ! in_array( $elementType, self::IMAGE_ELEMENT_TYPES, true ) || ! isset( $element['image_src'] ) || ! is_string( $element['image_src'] ) ) {
			return 0;
		}

		list( $attachmentId ) = explode( ':', $element['image_src'], 2 );

		return is_numeric( $attachmentId ) ? (int) $attachmentId : 0;
	}
}
