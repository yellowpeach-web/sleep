<?php

namespace WPML\PB\Gutenberg\StringsInBlock\DOMHandler;

use function WPML\FP\pipe;

class HtmlBlock extends StandardBlock {

	protected function getInnerHTML( \DOMNode $element, $context ) {
		if ( $element instanceof \DOMText ) {
			return [ self::restoreRawBodies( $element->nodeValue ), 'AREA' ];
		}

		$cleanUp = pipe(
			[ $this, 'removeCdataFromStyleTag' ],
			[ $this, 'removeCdataFromScriptTag' ],
			[ $this, 'keepNonBreakingSpacesAsEntities' ],
			[ $this, 'keepWhitespaceAsCharacters' ],
			[ $this, 'restoreRawBodies' ]
		);

		return [
			$cleanUp( $this->getInnerHTMLFromChildNodes( $element, $context ) ),
			'AREA',
		];
	}

	protected function registersAsText( \DOMNode $node ) {
		return $node instanceof \DOMText;
	}

	public static function keepNonBreakingSpacesAsEntities( $html ) {
		return str_replace( "\u{00A0}", '&nbsp;', $html );
	}

	public static function keepWhitespaceAsCharacters( $html ) {
		return str_replace( [ '&#13;', '&#10;', '&#9;' ], [ "\r", "\n", "\t" ], $html );
	}
}
