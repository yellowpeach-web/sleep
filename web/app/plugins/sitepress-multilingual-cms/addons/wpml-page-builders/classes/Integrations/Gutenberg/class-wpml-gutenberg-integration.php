<?php

use WPML\FP\Fns;
use WPML\FP\Str;
use WPML\PB\Helper\TranslationContent;

class WPML_Gutenberg_Integration implements \WPML\PB\Gutenberg\Integration {

	const PACKAGE_ID              = 'Gutenberg';
	const GUTENBERG_OPENING_START = '<!-- wp:';
	const GUTENBERG_CLOSING_START = '<!-- /wp:';
	const CLASSIC_BLOCK_NAME      = 'core/classic-block';

	private $strings_in_blocks;

	private $config_option;

	private $strings_registration;

	private $sitepress;

	public function __construct(
		WPML\PB\Gutenberg\StringsInBlock\StringsInBlock $strings_in_block,
		WPML_Gutenberg_Config_Option $config_option,
		WPML_Gutenberg_Strings_Registration $strings_registration,
		SitePress $sitepress
	) {
		$this->strings_in_blocks    = $strings_in_block;
		$this->config_option        = $config_option;
		$this->strings_registration = $strings_registration;
		$this->sitepress            = $sitepress;
	}

	public function add_hooks() {
		add_filter( 'wpml_page_builder_support_required', array( $this, 'page_builder_support_required' ), 10, 1 );
		add_action( 'wpml_page_builder_register_strings', array( $this, 'register_strings' ), 10, 2 );
		add_action( 'wpml_page_builder_string_translated', array( $this, 'string_translated' ), 10, 5 );
		add_filter( 'wpml_config_array', array( $this, 'wpml_config_filter' ) );
		add_filter( 'wpml_pb_should_body_be_translated', array( $this, 'should_body_be_translated_filter' ), PHP_INT_MAX, 3 );
		add_filter( 'wpml_get_translatable_types', array( $this, 'remove_package_strings_type_filter' ), 11 );
		add_action( 'save_post', array( $this, 'clear_translations_cache_on_post_save' ), 10, 2 );
	}

	public function page_builder_support_required( $plugins ) {
		$plugins[] = self::PACKAGE_ID;

		return $plugins;
	}

	public function register_strings( WP_Post $post, $package_data ) {

		if ( self::PACKAGE_ID !== $package_data['kind'] ) {
			return;
		}

		if ( $this->is_gutenberg_post( $post ) || $this->strings_registration->has_registered_strings( $package_data ) ) {
			$this->strings_registration->register_strings( $post, $package_data );
		}
	}

	public function register_strings_from_widget( array $blocks, array $package_data ) {
		$this->strings_registration->register_strings_from_widget( $blocks, $package_data );
	}

	public static function sanitize_block( $block ) {
		if ( ! $block instanceof WP_Block_Parser_Block ) {

			if ( empty( $block['blockName'] ) ) {
				$block['blockName'] = self::CLASSIC_BLOCK_NAME;
			}

			$block = new WP_Block_Parser_Block( $block['blockName'], $block['attrs'], $block['innerBlocks'], $block['innerHTML'], $block['innerContent'] );
		}

		return $block;
	}

	public function string_translated(
		$package_kind,
		$translated_post_id,
		$original_post,
		$string_translations,
		$lang
	) {

		if ( self::PACKAGE_ID === $package_kind ) {
			$content = $this->replace_strings_in_blocks( $original_post->post_content, $string_translations, $lang );

			$content = apply_filters( 'wpml_pb_gutenberg_translated_content_before_save', $content, $translated_post_id, $lang, $original_post );

			TranslationContent::update(
				[
					'ID'           => $translated_post_id,
					'post_content' => $content,
				],
				$lang
			);

			$this->fix_links_after_rebuild( (int) $translated_post_id, (string) $lang );
		}
	}

	private function fix_links_after_rebuild( $translated_post_id, $lang ) {
		static $running = false;

		/** @var WPML_Pro_Translation|null $pro_translation Set by tm.php only; a Blog licence never defines it (wpmldev-8194). */
		$pro_translation = isset( $GLOBALS['ICL_Pro_Translation'] ) ? $GLOBALS['ICL_Pro_Translation'] : null;

		if ( $running || ! is_object( $pro_translation ) || ! method_exists( $pro_translation, 'fix_links_to_translated_content' ) ) {
			return;
		}

		$running = true;

		try {
			$pro_translation->fix_links_to_translated_content( $translated_post_id, $lang, 'post' );
		} finally {
			$running = false;
		}
	}

	public function replace_strings_in_blocks( $content, $string_translations, $lang ) {
		$blocks = self::parse_blocks( $content );
		$blocks = $this->update_block_translations( $blocks, $string_translations, $lang );

		return Fns::reduce( Str::concat(), '', Fns::map( [ $this, 'render_block' ], $blocks ) );
	}

	private function update_block_translations( $blocks, $string_translations, $lang ) {
		foreach ( $blocks as &$block ) {

			$block               = self::sanitize_block( $block );
			$original_inner_html = $block->innerHTML;
			$block               = $this->strings_in_blocks->update( $block, $string_translations, $lang );
			$block               = self::move_link_copies_in_attributes( $block, $original_inner_html );

			if ( isset( $block->innerBlocks ) ) {
				$block->innerBlocks = $this->update_block_translations(
					$block->innerBlocks,
					$string_translations,
					$lang
				);
			}
		}

		return $blocks;
	}

	private static function move_link_copies_in_attributes( WP_Block_Parser_Block $block, $original_inner_html ) {
		if (
			! is_array( $block->attrs )
			|| ! $block->attrs
			|| ! is_string( $original_inner_html )
			|| ! is_string( $block->innerHTML )
			|| $block->innerHTML === $original_inner_html
		) {
			return $block;
		}

		$moves = self::get_moved_links( $original_inner_html, $block->innerHTML );

		if ( $moves ) {
			$block->attrs = self::move_links_in_value( $block->attrs, $moves );
		}

		return $block;
	}

	private static function get_moved_links( $original_html, $translated_html ) {
		$from = self::get_anchor_hrefs( $original_html );
		$to   = self::get_anchor_hrefs( $translated_html );

		if ( ! $from || count( $from ) !== count( $to ) ) {
			return [];
		}

		$moves     = [];
		$ambiguous = [];
		foreach ( $from as $index => $url ) {
			if ( isset( $moves[ $url ] ) && $moves[ $url ] !== $to[ $index ] ) {
				$ambiguous[ $url ] = true;
			}
			$moves[ $url ] = $to[ $index ];
		}

		foreach ( $moves as $url => $target ) {
			if ( '' === (string) $url || (string) $url === $target || isset( $ambiguous[ $url ] ) ) {
				unset( $moves[ $url ] );
			}
		}

		return $moves;
	}

	private static function get_anchor_hrefs( $html ) {
		if ( ! preg_match_all( '/<a\b[^>]*?\shref\s*=\s*(["\'])(.*?)\1/is', $html, $matches ) ) {
			return [];
		}

		return array_map(
			function ( $href ) {
				return html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			},
			$matches[2]
		);
	}

	private static function move_links_in_value( $value, array $moves ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::move_links_in_value( $item, $moves );
			}

			return $value;
		}

		return is_string( $value ) && array_key_exists( $value, $moves ) ? $moves[ $value ] : $value;
	}

	public static function render_block( $block ) {
		$content = '';

		$block = self::sanitize_block( $block );

		if ( self::CLASSIC_BLOCK_NAME !== $block->blockName ) {
			$block_content = self::render_inner_HTML( $block );
			$block_type    = preg_replace( '/^core\//', '', $block->blockName );

			$block_attributes = '';
			if ( self::has_non_empty_attributes( $block ) ) {
				$block_attributes = ' ' . wp_json_encode( $block->attrs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
			$content .= self::GUTENBERG_OPENING_START . $block_type . $block_attributes . ' ';

			if ( $block_content ) {
				$content .= '-->' . $block_content . self::GUTENBERG_CLOSING_START . $block_type . ' -->';
			} else {
				$content .= '/-->';
			}
		} else {
			$content = wpautop( $block->innerHTML );
		}

		return $content;
	}

	public static function has_non_empty_attributes( WP_Block_Parser_Block $block ) {
		return (bool) ( (array) $block->attrs );
	}

	private static function render_inner_HTML( $block ) {

		if ( isset( $block->innerBlocks ) && count( $block->innerBlocks ) ) {

			if ( isset( $block->innerContent ) ) {
				$content = self::render_inner_HTML_with_innerContent( $block );
			} else {
				$content = self::render_inner_HTML_with_guess_parts( $block );
			}
		} else {
			$content = $block->innerHTML;
		}

		return $content;
	}

	private static function render_inner_HTML_with_innerContent( $block ) {
		$content           = '';
		$inner_block_index = 0;

		foreach ( $block->innerContent as $inner_content ) {
			if ( is_string( $inner_content ) ) {
				$content .= $inner_content;
			} else {
				$content .= self::render_block( $block->innerBlocks[ $inner_block_index ] );
				++$inner_block_index;
			}
		}

		return $content;
	}

	private static function render_inner_HTML_with_guess_parts( $block ) {
		$inner_html_parts = self::guess_inner_HTML_parts( $block );

		$content = $inner_html_parts[0];

		foreach ( $block->innerBlocks as $inner_block ) {
			$content .= self::render_block( $inner_block );
		}

		$content .= $inner_html_parts[1];

		return $content;
	}

	private static function guess_inner_HTML_parts( $block ) {
		$inner_HTML = $block->innerHTML;

		$parts = array( $inner_HTML, '' );

		switch ( $block->blockName ) {
			case 'core/media-text':
				$html_to_find = '<div class="wp-block-media-text__content">';
				$pos          = mb_strpos( $inner_HTML, $html_to_find ) + mb_strlen( $html_to_find );
				$parts        = [
					mb_substr( $inner_HTML, 0, $pos ),
					mb_substr( $inner_HTML, $pos ),
				];
				break;

			default:
				preg_match( '#>\s*</#', $inner_HTML, $matches );

				if ( count( $matches ) === 1 ) {
					$parts = explode( $matches[0], $inner_HTML );
					if ( count( $parts ) === 2 ) {
						$match_mid_point = 1 + ( mb_strlen( $matches[0] ) - 3 ) / 2;

						$parts[0] .= mb_substr( $matches[0], 0, $match_mid_point );
						$parts[1]  = mb_substr( $matches[0], $match_mid_point ) . $parts[1];
					}
				}
				break;
		}

		return $parts;
	}

	public function wpml_config_filter( $config_data ) {
		$this->config_option->update_from_config( $config_data );

		return $config_data;
	}

	public function should_body_be_translated_filter( $translate, WP_Post $post, $context = '' ) {
		if ( 'translate_images_in_post_content' === $context && $this->is_gutenberg_post( $post ) ) {
			$translate = true;
		} elseif ( '' === $context && $this->is_gutenberg_post( $post ) ) {
			$translate = 0;
		}

		return $translate;
	}

	private function is_gutenberg_post( WP_Post $post ) {
		return (bool) preg_match( '/' . self::GUTENBERG_OPENING_START . '/', $post->post_content );
	}

	public static function parse_blocks( $content ) {
		if ( class_exists( \WP_Block_Parser::class ) ) {
			return ( new \WP_Block_Parser() )->parse( $content );
		}

		if ( function_exists( 'parse_blocks' ) ) {
			return parse_blocks( $content );
		}

		return gutenberg_parse_blocks( $content );
	}

	public function remove_package_strings_type_filter( $types ) {

		if ( array_key_exists( 'gutenberg', $types ) ) {
			unset( $types['gutenberg'] );
		}

		return $types;
	}

	public function get_config_option() {
		return $this->config_option;
	}

	public function clear_translations_cache_on_post_save( $post_id, $post ) {
		if ( ! $post instanceof WP_Post || ! $this->is_gutenberg_post( $post ) ) {
			return;
		}

		$trid = $this->sitepress->get_element_trid( $post_id, 'post_' . $post->post_type );

		if ( $trid ) {
			wp_cache_delete( $trid, 'WPML_TM_ICL_Translations--translations' );
		}
	}
}
