<?php

namespace WPML\PB\Gutenberg\StringsInBlock;

use WPML\FP\Str;
use WPML\FP\Obj;
use WPML\PB\Gutenberg\StringsInBlock\DOMHandler\DOMHandle;
use WPML\PB\Gutenberg\StringsInBlock\DOMHandler\HtmlBlock;
use WPML\PB\Gutenberg\StringsInBlock\DOMHandler\StandardBlock;
use WPML\PB\Gutenberg\StringsInBlock\DOMHandler\ListBlock;
use WPML\PB\Gutenberg\StringsInBlock\DOMHandler\ListItemBlock;
use WPML\PB\Gutenberg\XPath;

class HTML extends Base {

	const LIST_BLOCK_NAME      = 'core/list';
	const LIST_ITEM_BLOCK_NAME = 'core/list-item';
	const HTML_BLOCK_NAME      = 'core/html';
	const EMBED_BLOCK_NAME     = 'core/embed';

	const NON_TEXT_ELEMENTS = [ 'style', 'script' ];

	const TRANSLATABLE_ATTRIBUTE_PATTERN = '/\s(?:alt|title|placeholder|aria-label|value|label)\s*=\s*(?:"[^"]*[^\s"][^"]*"|\'[^\']*[^\s\'][^\']*\'|[^\s"\'=<>`]+)/i';

	public function find( \WP_Block_Parser_Block $block ) {
		$strings = array();

		$block_queries = $this->get_block_queries( $block );

		if ( is_array( $block_queries ) && isset( $block->innerHTML ) ) {
			$dom_handle = $this->get_dom_handler( $block );
			$xpath      = $dom_handle->getDomxpath( $block->innerHTML );

			foreach ( $block_queries as $blockQuery ) {
				list( $query, $definedType, $label ) = XPath::parse( $blockQuery );
				$elements                            = $xpath->query( $query );
				foreach ( $elements as $element ) {
					list( $text, $type ) = $dom_handle->getPartialInnerHTML( $element );
					if ( $text ) {
						$string_id = $this->get_string_id( $block->blockName, $text );
						$strings[] = $this->build_string(
							$string_id,
							$label ?: $this->get_block_label( $block ),
							$text,
							$definedType ? $definedType : $type
						);
					}
				}
			}
		} else {

			$segments = isset( $block->blockName, $block->innerHTML ) ? $this->splitAtNonTextElements( $block->innerHTML ) : null;

			if ( null !== $segments ) {
				foreach ( $segments as list( $markup, $isText ) ) {
					if ( $isText ) {
						$strings[] = $this->build_string(
							$this->get_string_id( $block->blockName, $markup ),
							$this->get_block_label( $block ),
							$markup,
							'VISUAL'
						);
					}
				}

				return $strings;
			}

			$string_id = $this->get_block_string_id( $block );
			if ( $string_id && $this->holdsTranslatableMarkup( $block->innerHTML ) ) {
				$strings[] = $this->build_string(
					$string_id,
					$this->get_block_label( $block ),
					$block->innerHTML,
					'VISUAL'
				);
			}
		}

		return $strings;
	}

	private function holdsTranslatableMarkup( $markup ) {
		return $this->showsText( $markup )
			|| (bool) preg_match( self::TRANSLATABLE_ATTRIBUTE_PATTERN, $markup );
	}

	public function update( \WP_Block_Parser_Block $block, array $string_translations, $lang ) {

		$block_queries = $this->get_block_queries( $block );

		if ( $block_queries && isset( $block->innerHTML ) ) {
			$dom_handle = $this->get_dom_handler( $block );
			$dom        = $dom_handle->getDom( $block->innerHTML );
			$xpath      = new \DOMXPath( $dom );

			foreach ( $block_queries as $query ) {
				list( $query, ) = XPath::parse( $query );
				$elements       = $xpath->query( $query );
				foreach ( $elements as $element ) {
					list( $text, ) = $dom_handle->getPartialInnerHTML( $element );
					$translation   = $this->getTranslation( $text, $lang, $block, $string_translations );
					$block         = $this->updateTranslationInBlock(
						$text,
						$this->apply_placeholders_for_html_entities( $translation ),
						$block,
						$element,
						$dom_handle
					);
				}
			}
			$content          = $dom_handle->getFullInnerHTML( $dom->documentElement );
			$block->innerHTML = $this->restore_placeholders_for_html_entities( reset( $content ) );

		} elseif ( isset( $block->blockName, $block->innerHTML ) && $this->holdsTranslatableMarkup( $block->innerHTML ) ) {

			$segments = $this->splitAtNonTextElements( $block->innerHTML );

			if ( null !== $segments && $this->updateTextSegments( $block, $segments, $string_translations, $lang ) ) {
				return $block;
			}

			$translation = $this->getTranslation( $block->innerHTML, $lang, $block, $string_translations );

			if ( $translation ) {
				$block            = $this->get_dom_handler( $block )->applyTranslationToInnerContent( $block, $block->innerHTML, $translation );
				$block->innerHTML = $translation;
			}
		}

		return $block;
	}

	private function get_block_string_id( \WP_Block_Parser_Block $block ) {
		if ( isset( $block->blockName, $block->innerHTML ) && '' !== trim( $block->innerHTML ) ) {
			return $this->get_string_id( $block->blockName, $block->innerHTML );
		} else {
			return null;
		}
	}

	private function updateTextSegments( \WP_Block_Parser_Block $block, array $segments, array $string_translations, $lang ) {
		$translations = [];

		foreach ( $segments as $index => list( $markup, $isText ) ) {
			$translation = $isText ? $this->getTranslation( $markup, $lang, $block, $string_translations ) : null;
			if ( $translation ) {
				$translations[ $index ] = $translation;
			}
		}

		if ( ! $translations ) {
			return false;
		}

		$dom_handle = $this->get_dom_handler( $block );
		$innerHTML  = '';

		foreach ( $segments as $index => list( $markup, ) ) {
			if ( isset( $translations[ $index ] ) ) {
				$translated = $dom_handle->applyTranslationToInnerContent( $block, $markup, $translations[ $index ] );

				$block->innerContent = $translated->innerContent;
				$innerHTML          .= $translations[ $index ];
			} else {
				$innerHTML .= $markup;
			}
		}

		$block->innerHTML = $innerHTML;

		return true;
	}

	private function splitAtNonTextElements( $html ) {
		$element = '#<(' . implode( '|', self::NON_TEXT_ELEMENTS ) . ')(?=[\s/>])[^>]*>.*?</\1\s*>#is';

		if ( ! preg_match_all( $element, $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		$segments = [];
		$offset   = 0;

		foreach ( $matches[0] as list( $element, $start ) ) {
			$segments   = array_merge( $segments, $this->splitTextSegment( (string) substr( $html, $offset, $start - $offset ) ) );
			$segments[] = [ $element, false ];
			$offset     = $start + strlen( $element );
		}

		return array_merge( $segments, $this->splitTextSegment( (string) substr( $html, $offset ) ) );
	}

	private function splitTextSegment( $markup ) {
		if ( '' === $markup ) {
			return [];
		}

		if ( ! $this->showsText( $markup ) ) {
			return [ [ $markup, false ] ];
		}

		list( $start, $end ) = $this->getTextBounds( $markup );

		return array_values(
			array_filter(
				[
					[ (string) substr( $markup, 0, $start ), false ],
					[ (string) substr( $markup, $start, $end - $start ), true ],
					[ (string) substr( $markup, $end ), false ],
				],
				function ( $segment ) {
					return '' !== $segment[0];
				}
			)
		);
	}

	private function showsText( $markup ) {
		$text    = html_entity_decode( strip_tags( $markup ), ENT_QUOTES, 'UTF-8' );
		$visible = preg_replace( '/[\s\x{00A0}]+/u', '', $text );

		return '' !== ( null === $visible ? trim( $text ) : $visible );
	}

	private function getTextBounds( $markup ) {
		preg_match_all( '#<!--.*?-->|<(/?)([a-zA-Z][a-zA-Z0-9:-]*)(?:[\s/][^>]*)?>#s', $markup, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

		$void      = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr' ];
		$peelable  = [];
		$openStack = [];

		foreach ( $tags as $index => $tag ) {
			$isComment = ! isset( $tag[2] ) || '' === $tag[2][0];
			$name      = $isComment ? '' : strtolower( $tag[2][0] );

			if ( $isComment ) {
				$peelable[ $index ] = true;
			} elseif ( '/' === $tag[1][0] ) {
				$opener = null;
				for ( $i = count( $openStack ) - 1; $i >= 0; $i-- ) {
					if ( $openStack[ $i ][1] === $name ) {
						$opener = $i;
						break;
					}
				}
				if ( null === $opener ) {
					$peelable[ $index ] = true;
				} else {
					$openStack = array_slice( $openStack, 0, $opener );
				}
			} elseif ( ! in_array( $name, $void, true ) && '/>' !== substr( $tag[0][0], -2 ) ) {
				$openStack[] = [ $index, $name ];
			}
		}

		foreach ( $openStack as list( $index, ) ) {
			$peelable[ $index ] = true;
		}

		$bounds = [];
		foreach ( $tags as $index => $tag ) {
			$bounds[ $index ] = [ $tag[0][1], $tag[0][1] + strlen( $tag[0][0] ) ];
		}

		$start = 0;
		$first = 0;
		while ( true ) {
			$start += strspn( $markup, " \t\n\r\0\x0B", $start );
			if ( isset( $bounds[ $first ], $peelable[ $first ] ) && $bounds[ $first ][0] === $start ) {
				$start = $bounds[ $first ][1];
				$first++;
			} else {
				break;
			}
		}

		$end  = strlen( $markup );
		$last = count( $tags ) - 1;
		while ( $end > $start ) {
			$end = strlen( rtrim( substr( $markup, 0, $end ), " \t\n\r\0\x0B" ) );
			if ( $last >= $first && isset( $peelable[ $last ] ) && $bounds[ $last ][1] === $end ) {
				$end = $bounds[ $last ][0];
				$last--;
			} else {
				break;
			}
		}

		return [ $start, $end ];
	}

	private function get_block_queries( \WP_Block_Parser_Block $block ) {
		return $this->get_block_config( $block, 'xpath' );
	}

	private function get_dom_handler( \WP_Block_Parser_Block $block ) {
		$class = wpml_collect(
			[
				self::LIST_BLOCK_NAME      => ListBlock::class,
				self::HTML_BLOCK_NAME      => HtmlBlock::class,
				self::LIST_ITEM_BLOCK_NAME => ListItemBlock::class,
			]
		)->get( $block->blockName, StandardBlock::class );

		return new $class();
	}

	private function updateTranslationInBlock( $text, $translation, \WP_Block_Parser_Block $block, $element, $dom_handle ) {
		if ( $translation ) {
			$innerContentBeforeUpdate = $block->innerContent;

			$block = $dom_handle->applyStringTranslations( $block, $element, $translation, $text );

			$elementSpansInnerBlock = $block->innerContent === $innerContentBeforeUpdate;

			if ( $elementSpansInnerBlock ) {
				$block = $dom_handle->applyTranslationToInnerContent( $block, $text, $translation );
			}

			$dom_handle->setElementValue( $element, $translation );
		}

		return $block;
	}

	private function getTranslation( $text, $lang, \WP_Block_Parser_Block $block, array $string_translations ) {
		$translationFromPageBuilder = apply_filters( 'wpml_pb_update_translations_in_content', $text, $lang );
		if ( $translationFromPageBuilder === $text ) {
			$string_id = $this->get_string_id( $block->blockName, $text );
			if ( (int) Obj::path( [ $string_id, $lang, 'status' ], $string_translations ) === ICL_TM_COMPLETE ) {
				$translation = $string_translations[ $string_id ][ $lang ]['value'];
				$translation = $this->encodeStandaloneLessThan( $translation );
				return self::preserveEmbedUrlLines( $block, self::preserveNewLines( $text, $translation ) );
			} else {
				return null;
			}
		} else {
			return $translationFromPageBuilder;
		}
	}

	private function encodeStandaloneLessThan( $text ) {
		return preg_replace( '/<(?![a-zA-Z][a-zA-Z0-9-:]*[\s\/>]|\/[a-zA-Z]|!)/', '&lt;', $text );
	}

	private static function preserveNewLines( $original, $translation ) {
		if ( Str::startsWith( "\n", $original ) && ! Str::startsWith( "\n", $translation ) ) {
			$translation = "\n" . $translation;
		}

		if ( Str::endsWith( "\n", $original ) && ! Str::endsWith( "\n", $translation ) ) {
			$translation .= "\n";
		}

		return $translation;
	}

	private static function preserveEmbedUrlLines( \WP_Block_Parser_Block $block, string $translation ): string {
		if ( self::EMBED_BLOCK_NAME !== $block->blockName ) {
			return $translation;
		}

		return preg_replace(
			'#(<div[^>]*\bclass="[^"]*\bwp-block-embed__wrapper\b[^"]*"[^>]*>)\s*(https?://[^\s<>"]+)\s*(</div>)#i',
			"$1\n$2\n$3",
			$translation
		) ?? $translation;
	}

	const HTML_ENTITY_PLACEHOLDERS = [
		'&apos;' => 'WPML_PLACEHOLDER_APOS',
		'&quot;' => 'WPML_PLACEHOLDER_QUOT',
	];

	private function apply_placeholders_for_html_entities( $content ) {
		if ( empty( $content ) ) {
			return $content;
		}

		return str_replace(
			array_keys( self::HTML_ENTITY_PLACEHOLDERS ),
			array_values( self::HTML_ENTITY_PLACEHOLDERS ),
			$content
		);
	}

	private function restore_placeholders_for_html_entities( $content ) {
		if ( empty( $content ) ) {
			return $content;
		}

		return str_replace(
			array_values( self::HTML_ENTITY_PLACEHOLDERS ),
			array_keys( self::HTML_ENTITY_PLACEHOLDERS ),
			$content
		);
	}
}
