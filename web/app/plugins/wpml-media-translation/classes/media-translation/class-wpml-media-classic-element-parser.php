<?php

namespace WPML\Media\Classes;

abstract class WPML_Media_Classic_Element_Parser extends WPML_Media_Element_Parser {

	abstract protected function extractExtension();

	abstract protected function getMediaElementRegex();

	abstract protected function getMediaExtensionExpression();

	public function getMediaElements() {
		return preg_match_all( $this->getMediaElementRegex(), $this->blockText, $matches )
			? $this->getAttachments( $matches ) : [];
	}

	public function getMediaSrcFromAttributes( $attrs ) {
		$extension = $this->extractExtension();

		return ( $extension && isset( $attrs[ $extension ] ) ) ? $attrs[ $extension ] : '';
	}

	protected function getExtensionMatches() {
		preg_match( $this->getMediaExtensionExpression(), $this->blockText, $matches );

		return $matches;
	}
}
