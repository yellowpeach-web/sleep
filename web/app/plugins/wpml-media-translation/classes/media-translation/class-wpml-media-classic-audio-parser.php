<?php

namespace WPML\Media\Classes;

use WPML\FP\Str;

class WPML_Media_Classic_Audio_Parser extends WPML_Media_Classic_Element_Parser {

	const Media_Element_Expression = '/\[audio ([^]]+)\]/s';
	const Media_Extension_Expression = '/\[audio.+?(?=="http)/';

	protected function extractExtension() {
		$matches = $this->getExtensionMatches();

		return ! empty( $matches ) ? substr( $matches[0], - 3 ) : 'mp3';
	}

	protected function getMediaElementRegex() {
		return self::Media_Element_Expression;
	}

	protected function getMediaExtensionExpression() {
		return self::Media_Extension_Expression;
	}

	public function validate() {
		return Str::includes( '[audio', $this->blockText );
	}
}
