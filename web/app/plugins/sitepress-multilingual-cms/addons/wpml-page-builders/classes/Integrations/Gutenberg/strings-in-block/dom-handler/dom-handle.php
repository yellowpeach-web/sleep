<?php

namespace WPML\PB\Gutenberg\StringsInBlock\DOMHandler;

use WPML\PB\Gutenberg\StringsInBlock\Base;
use function WPML\FP\pipe;

abstract class DOMHandle {

	const INNER_HTML_PARTIAL = 'partial';
	const INNER_HTML_FULL    = 'full';
	const LINE_BREAK_TAG     = 'br';

	const RAW_BODY_PLACEHOLDER = '<!-- wpml:raw %s -->';

	const RAW_BODY_PATTERNS = [
		'/(<script(?=[\s\/>])[^>]*>)(.+?)(<\/script\s*>)/is',
		'/(<style(?=[\s\/>])[^>]*>)(.+?)(<\/style\s*>)/is',
	];

	const ENTITY_SYNTAX_PATTERN = '/&(?:[a-z][a-z0-9]*|#[0-9]+|#x[0-9a-f]+);/i';

	public function getDomxpath( $html ) {
		$dom = $this->getDom( $html );

		return new \DOMXPath( $dom );
	}

	public function getDom( $html ) {
		return self::loadFragment( self::protectRawBodies( $html ) );
	}

	private static function loadFragment( $html ) {
		$dom = new \DOMDocument();
		\libxml_use_internal_errors( true );
		$html = mb_encode_numericentity( $html, [ 0x80, 0x1FFFFF, 0, 0x1FFFFF ], 'UTF-8' );
		$dom->loadHTML( '<div>' . $html . '</div>' );
		\libxml_clear_errors();

		$dom->removeChild( $dom->doctype );

		$dom->replaceChild( $dom->firstChild->firstChild->firstChild, $dom->firstChild );
		return $dom;
	}

	public static function protectRawBodies( $html ) {
		$protected = preg_replace_callback(
			self::RAW_BODY_PATTERNS,
			function ( array $matches ) {
				return $matches[1] . sprintf( self::RAW_BODY_PLACEHOLDER, base64_encode( $matches[2] ) ) . $matches[3];
			},
			$html
		);

		return null === $protected ? $html : $protected;
	}

	public function getPreviousRegistrationForms( $value ) {
		$value = (string) $value;
		$forms = [ html_entity_decode( $value ) ];

		if ( false !== strpos( $value, '<' ) ) {
			$unprotected = $this->getInnerHTMLFromChildNodes( self::loadFragment( $value )->documentElement, self::INNER_HTML_FULL );
			$unprotected = self::removeCdataFromScriptTag( self::removeCdataFromStyleTag( $unprotected ) );
			$forms[]     = $unprotected;
			$forms[]     = html_entity_decode( $unprotected );
		}

		return array_values(
			array_unique(
				array_filter(
					$forms,
					function ( $form ) use ( $value ) {
						return '' !== $form && $form !== $value;
					}
				)
			)
		);
	}

	public static function restoreRawBodies( $html ) {
		for ( $pass = 0; $pass < 3 && false !== strpos( (string) $html, '<!-- wpml:raw ' ); $pass++ ) {
			$restored = preg_replace_callback(
				'/<!-- wpml:raw ([A-Za-z0-9+\/]*={0,2}) -->/',
				function ( array $matches ) {
					$body = base64_decode( $matches[1], true );

					return false === $body ? $matches[0] : $body;
				},
				$html
			);

			if ( null === $restored || $restored === $html ) {
				break;
			}

			$html = $restored;
		}

		return $html;
	}

	public function applyStringTranslations( \WP_Block_Parser_Block $block, \DOMNode $element, $translation, $originalValue = null ) {
		if ( empty( $block->innerContent ) || empty( $element->nodeValue ) ) {
			return $block;
		}

		if ( $element instanceof \DOMAttr ) {
			$search_value = preg_quote( esc_attr( $element->nodeValue ), '/' );
			$search       = '/(")(' . $search_value . ')(")/';
			$translation  = esc_attr( $translation );
		} elseif ( $this->registersAsText( $element ) ) {
			$original = $element instanceof \DOMText || ! $originalValue ? $element->nodeValue : $originalValue;

			return $this->applyTextTranslation( $block, $original, $this->getTranslationAsText( $element, $translation ) );
		} else {
			$replace_full_html_node_content = $element->hasChildNodes() && $originalValue;

			$original = $replace_full_html_node_content ? $originalValue : $element->nodeValue;

			if ( $this->isPlainText( $original ) ) {
				$original    = $this->encodeAmpersands( $original );
				$translation = $this->encodeAmpersands( $translation );
			}

			$search_value = preg_quote( $original, '/' );
			$search_value = str_replace( [ preg_quote( '<br>', '/' ), preg_quote( '<br/>', '/' ) ], '<br\/?>', $search_value );
			$search = '/(>|^)(' . $search_value . ')(<|$)/';
		}

		return $this->replaceInInnerContent( $block, $search, $translation );
	}

	private function replaceInInnerContent( \WP_Block_Parser_Block $block, $search, $translation ) {
		$replace = function ( array $matches ) use ( $translation ) {
			return $matches[1] . $translation . $matches[3];
		};

		foreach ( $block->innerContent as &$inner_content ) {
			if ( $inner_content ) {
				$inner_content = preg_replace_callback( $search, $replace, $inner_content );
			}
		}

		return $block;
	}

	private function applyTextTranslation( \WP_Block_Parser_Block $block, $originalText, $translationText ) {
		foreach ( $this->getTextMarkupPairs( $originalText, $translationText ) as list( $original, $translation ) ) {
			$innerContentBefore = $block->innerContent;
			$block = $this->replaceInInnerContent( $block, '/(>|^)(' . preg_quote( $original, '/' ) . ')(<|$)/', $translation );

			if ( $block->innerContent !== $innerContentBefore ) {
				break;
			}
		}

		return $block;
	}

	protected function registersAsText( \DOMNode $node ) {
		if ( $node instanceof \DOMText ) {
			return true;
		}

		if ( ! $node instanceof \DOMElement ) {
			return false;
		}

		list( , $type ) = $this->getPartialInnerHTML( $node );

		return 'VISUAL' !== $type;
	}

	protected function getTranslationAsText( \DOMNode $node, $translation ) {
		if ( $node instanceof \DOMText ) {
			$originalText = $node->nodeValue;
		} else {
			list( $originalText, ) = $this->getPartialInnerHTML( $node );
		}

		if ( preg_match( self::ENTITY_SYNTAX_PATTERN, (string) $originalText ) ) {
			return (string) $translation;
		}

		return html_entity_decode( (string) $translation, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	protected function getTextMarkupPairs( $originalText, $translationText ) {
		$pairs = [];

		foreach ( [ [ false, false ], [ true, false ], [ false, true ], [ true, true ] ] as list( $gt, $nbsp ) ) {
			$original = self::textToMarkup( (string) $originalText, $gt, $nbsp );

			if ( ! isset( $pairs[ $original ] ) ) {
				$pairs[ $original ] = [ $original, self::textToMarkup( (string) $translationText, $gt, $nbsp ) ];
			}
		}

		return array_values( $pairs );
	}

	private static function textToMarkup( $text, $gt, $nbsp ) {
		$markup = str_replace( [ '&', '<' ], [ '&amp;', '&lt;' ], $text );

		if ( $gt ) {
			$markup = str_replace( '>', '&gt;', $markup );
		}

		if ( $nbsp ) {
			$markup = str_replace( "\u{00A0}", '&nbsp;', $markup );
		}

		return $markup;
	}

	public function applyTranslationToInnerContent( \WP_Block_Parser_Block $block, $original, $translation ) {
		if ( empty( $block->innerContent ) || ! in_array( null, $block->innerContent, true ) ) {
			return $block;
		}

		$translatedNodes = $this->getTranslatedNodes(
			$this->getDom( $original )->documentElement,
			$this->getDom( $translation )->documentElement
		);

		if ( null === $translatedNodes || $this->translationsInterfere( $translatedNodes ) ) {
			return $block;
		}

		$untranslatedInnerContent = $block->innerContent;

		foreach ( $this->dropRepeatedNodeValues( $translatedNodes ) as list( $node, $value ) ) {
			$innerContentBeforeNode = $block->innerContent;

			$block = $node instanceof \DOMText
				? $this->applyTextTranslation( $block, $node->nodeValue, $value )
				: $this->applyStringTranslations( $block, $node, $value );

			if ( $block->innerContent === $innerContentBeforeNode ) {
				$block->innerContent = $untranslatedInnerContent;

				return $block;
			}
		}

		return $block;
	}

	private function dropRepeatedNodeValues( array $translatedNodes ) {
		$seen  = [];
		$pairs = [];

		foreach ( $translatedNodes as $pair ) {
			$key = ( $pair[0] instanceof \DOMAttr ? 'attr:' : 'text:' ) . $pair[0]->nodeValue;

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$pairs[]      = $pair;
		}

		return $pairs;
	}

	private function translationsInterfere( array $translatedNodes ) {
		$translationsByOriginal = [];

		foreach ( $translatedNodes as list( $node, $translation ) ) {
			$original = $node->nodeValue;

			if ( isset( $translationsByOriginal[ $original ] ) && $translationsByOriginal[ $original ] !== $translation ) {
				return true;
			}

			$translationsByOriginal[ $original ] = $translation;
		}

		foreach ( $translationsByOriginal as $translation ) {
			if ( isset( $translationsByOriginal[ $translation ] ) ) {
				return true;
			}
		}

		return false;
	}

	private function getTranslatedNodes( \DOMNode $original, \DOMNode $translated ) {
		if ( $original->nodeType !== $translated->nodeType || $original->nodeName !== $translated->nodeName ) {
			return null;
		}

		if ( $original instanceof \DOMText ) {
			return $original->nodeValue === $translated->nodeValue ? [] : [ [ $original, $translated->nodeValue ] ];
		}

		$translatedNodes = $this->getTranslatedAttributes( $original, $translated );

		if ( null === $translatedNodes ) {
			return null;
		}

		$originalChildren   = $this->getContentChildNodes( $original );
		$translatedChildren = $this->getContentChildNodes( $translated );

		if ( count( $originalChildren ) !== count( $translatedChildren ) ) {
			return null;
		}

		foreach ( $originalChildren as $index => $child ) {
			$fromChild = $this->getTranslatedNodes( $child, $translatedChildren[ $index ] );

			if ( null === $fromChild ) {
				return null;
			}

			$translatedNodes = array_merge( $translatedNodes, $fromChild );
		}

		return $translatedNodes;
	}

	private function getContentChildNodes( \DOMNode $node ) {
		$children = [];

		if ( ! $node->hasChildNodes() ) {
			return $children;
		}

		foreach ( $node->childNodes as $child ) {
			if ( $this->isFormattingNode( $child ) ) {
				continue;
			}

			$children[] = $child;
		}

		return $children;
	}

	private function isFormattingNode( \DOMNode $node ) {
		if ( $node instanceof \DOMText ) {
			return '' === trim( $node->nodeValue );
		}

		return self::LINE_BREAK_TAG === $node->nodeName;
	}

	private function getTranslatedAttributes( \DOMNode $original, \DOMNode $translated ) {
		if ( ! $original instanceof \DOMElement || ! $translated instanceof \DOMElement ) {
			return [];
		}

		if ( $original->attributes->length !== $translated->attributes->length ) {
			return null;
		}

		$translatedAttributes = [];

		foreach ( $original->attributes as $attribute ) {
			$translatedAttribute = $translated->attributes->getNamedItem( $attribute->name );

			if ( ! $translatedAttribute ) {
				return null;
			}

			if ( $attribute->nodeValue !== $translatedAttribute->nodeValue ) {
				$translatedAttributes[] = [ $attribute, $translatedAttribute->nodeValue ];
			}
		}

		return $translatedAttributes;
	}

	private function isPlainText( $value ) {
		return false === strpos( $value, '<' );
	}

	private function encodeAmpersands( $value ) {
		return str_replace( '&', '&amp;', $value );
	}

	protected function getInnerHTML( \DOMNode $element, $context ) {
		$innerHTML = $element instanceof \DOMText
			? $element->nodeValue
			: $this->getInnerHTMLFromChildNodes( $element, $context );

		$restoreRawBodies = pipe(
			[ $this, 'removeCdataFromStyleTag' ],
			[ $this, 'removeCdataFromScriptTag' ],
			[ $this, 'restoreRawBodies' ]
		);

		$innerHTML = $restoreRawBodies( $innerHTML );

		$type = Base::get_string_type( $innerHTML );

		if ( 'VISUAL' !== $type ) {
			$innerHTML = html_entity_decode( $innerHTML );
		}

		return [ $innerHTML, $type ];
	}

	abstract protected function getInnerHTMLFromChildNodes( \DOMNode $element, $context );

	public function getPartialInnerHTML( \DOMNode $element ) {
		return $this->getInnerHTML( $element, self::INNER_HTML_PARTIAL );
	}

	public function getFullInnerHTML( \DOMNode $element ) {
		return $this->getInnerHTML( $element, self::INNER_HTML_FULL );
	}

	public function setElementValue( \DOMNode $element, $value ) {
		if ( $element instanceof \DOMAttr ) {
			$element->parentNode->setAttribute( $element->name, $value );
		} elseif ( $element instanceof \DOMText ) {
			$clone            = $this->cloneNodeWithoutChildren( $element );
			$clone->nodeValue = $this->getTranslationAsText( $element, $value );
			$element->parentNode->replaceChild( $clone, $element );
		} elseif ( $this->registersAsText( $element ) ) {
			$clone = $this->cloneNodeWithoutChildren( $element );
			$clone->appendChild( $element->ownerDocument->createTextNode( $this->getTranslationAsText( $element, $value ) ) );

			$this->appendExtraChildNodes( $clone, $element );

			$element->parentNode->replaceChild( $clone, $element );
		} else {
			$clone    = $this->cloneNodeWithoutChildren( $element );
			$fragment = $this->getDom( $value )->firstChild;
			foreach ( $fragment->childNodes as $child ) {
				$clone->appendChild( $element->ownerDocument->importNode( $child, true ) );
			}

			$this->appendExtraChildNodes( $clone, $element );

			$element->parentNode->replaceChild( $clone, $element );
		}
	}

	abstract protected function appendExtraChildNodes( \DOMNode $clonedElement, \DOMNode $element );

	private function cloneNodeWithoutChildren( \DOMNode $element ) {
		return $element->cloneNode( false );
	}

	protected function getAsHTML5( \DOMNode $element ) {
		return str_replace(
			'--/>',
			'-->',
			strtr(
				$element->ownerDocument->saveXML( $element, LIBXML_NOEMPTYTAG ),
				[
					'></area>'   => '/>',
					'></base>'   => '/>',
					'></br>'     => '/>',
					'></col>'    => '/>',
					'></embed>'  => '/>',
					'></hr>'     => '/>',
					'></img>'    => '/>',
					'></input>'  => '/>',
					'></link>'   => '/>',
					'></meta>'   => '/>',
					'></param>'  => '/>',
					'></source>' => '/>',
					'></track>'  => '/>',
					'></wbr>'    => '/>',
				]
			)
		);
	}

	public static function removeCdataFromStyleTag( $innerHTML ) {
		return self::unwrapCdataFromTag( 'style', $innerHTML );
	}

	public static function removeCdataFromScriptTag( $innerHTML ) {
		return self::unwrapCdataFromTag( 'script', $innerHTML );
	}

	private static function unwrapCdataFromTag( $tag, $innerHTML ) {
		$pattern = '/<' . $tag . '(.*?)><!\\[CDATA\\[(.*?)\\]\\]><\\/' . $tag . '>/s';

		return preg_replace_callback(
			$pattern,
			function ( array $matches ) use ( $tag ) {
				return '<' . $tag . $matches[1] . '>'
					. str_replace( ']]]]><![CDATA[>', ']]>', $matches[2] )
					. '</' . $tag . '>';
			},
			$innerHTML
		);
	}
}
