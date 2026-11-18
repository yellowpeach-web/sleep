<?php

namespace WPML\PB\Gutenberg\Hooks;

use WPML\FP\Lst;
use WPML\LIB\WP\Hooks;

use function WPML\FP\spreadArgs;

class TranslationJobImages implements \IWPML_REST_Action, \WPML\PB\Gutenberg\Integration {

	const PATTERN = '/(?:image|media-text)-\d+-(\d+)$/';

	private $mediaConfig;

	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_gutenberg_block_attachment_id', 10, 2 )
			->then( spreadArgs( [ $this, 'getAttachmentId' ] ) );
		Hooks::onFilter( 'wpml_pb_image_module_patterns' )
			->then( spreadArgs( Lst::append( self::PATTERN ) ) );
	}

	public function getAttachmentId( $attachmentId, $block ) {
		if ( ! is_a( $block, \WP_Block_Parser_Block::class ) ) {
			return (int) $attachmentId;
		}

		$attrs      = is_array( $block->attrs ) ? $block->attrs : [];
		$candidates = array_merge(
			[ $attrs['id'] ?? null, $attrs['mediaId'] ?? null ],
			\WPML_Page_Builders_Media_Gutenberg::find_media_ids( $this->getMediaConfig(), (string) $block->blockName, $attrs )
		);

		foreach ( $candidates as $candidate ) {
			$id = is_int( $candidate ) || ( is_string( $candidate ) && ctype_digit( $candidate ) ) ? (int) $candidate : 0;
			if ( $id > 0 && 'attachment' === get_post_type( $id ) ) {
				return $id;
			}
		}

		return (int) $attachmentId;
	}

	private function getMediaConfig() {
		if ( null === $this->mediaConfig ) {
			$mediaConfig       = get_option( \WPML_Gutenberg_Config_Option::OPTION_MEDIA_IN_BLOCKS, [] );
			$this->mediaConfig = is_array( $mediaConfig ) ? $mediaConfig : [];
		}

		return $this->mediaConfig;
	}
}
