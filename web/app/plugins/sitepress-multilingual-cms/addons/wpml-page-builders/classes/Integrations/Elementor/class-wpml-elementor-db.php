<?php

use WPML\PB\Helper\TranslationContent;

class WPML_Elementor_DB {

	const DYNAMIC_TAGS_MODE_REMOVE = 'remove';

	private $elementor_db;

	public function __construct( \Elementor\DB $elementor_db ) {
		$this->elementor_db = $elementor_db;
	}

	public function save_plain_text( $post_id ) {
		$this->elementor_db->switch_to_post( $post_id );

		try {
			if ( ! $this->is_plain_text_stored( $post_id ) ) {
				$this->elementor_db->save_plain_text( $post_id );

				return true;
			}
		} catch ( \Throwable $e ) {
		} finally {
			$this->elementor_db->restore_current_post();
		}

		return false;
	}

	private function is_plain_text_stored( $post_id ) {
		if ( ! method_exists( $this->elementor_db, 'get_plain_text' ) ) {
			return false;
		}

		$dynamic_tags = self::get_dynamic_tags();
		$parsing_mode = $dynamic_tags ? $dynamic_tags->get_parsing_mode() : null;

		try {
			if ( $dynamic_tags ) {
				$dynamic_tags->set_parsing_mode( self::DYNAMIC_TAGS_MODE_REMOVE );
			}

			return TranslationContent::isStored( $post_id, $this->elementor_db->get_plain_text( $post_id ) );
		} catch ( \Throwable $e ) {
			return false;
		} finally {
			if ( $dynamic_tags ) {
				$dynamic_tags->set_parsing_mode( $parsing_mode );
			}
		}
	}

	private static function get_dynamic_tags() {
		if ( ! class_exists( '\Elementor\Plugin', false ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return null;
		}

		$dynamic_tags = isset( \Elementor\Plugin::$instance->dynamic_tags ) ? \Elementor\Plugin::$instance->dynamic_tags : null;

		return is_object( $dynamic_tags )
			&& method_exists( $dynamic_tags, 'get_parsing_mode' )
			&& method_exists( $dynamic_tags, 'set_parsing_mode' ) ? $dynamic_tags : null;
	}
}
