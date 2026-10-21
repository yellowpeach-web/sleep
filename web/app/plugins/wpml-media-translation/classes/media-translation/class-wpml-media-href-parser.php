<?php

namespace WPML\Media\Classes;

class WPML_Media_Href_Parser extends WPML_Media_Element_Parser {

	private static $allAnchorsRegex = '/<a\b[^>]*>.*?<\/a>/s';
	private static $hrefElementRegex = '/<a ([^>]+)/s';

	private $anchorsWithoutNestedTags;

	public function getMediaElements() {
		return $this->getFromTags();
	}

	public function getMediaSrcFromAttributes( $attrs ) {
		return $this->attributeOr( $attrs, 'href' );
	}

	protected function getFromTags() {
		$anchorsWithoutTags = $this->getAnchorsWithoutNestedTags();

		return preg_match_all( self::$hrefElementRegex, implode( '', $anchorsWithoutTags ), $matches ) ? $this->getAttachments( $matches ) : [];
	}

	public function validate() {
		return $this->textContains( 'href=', $this->blockText ) && ! empty( $this->getAnchorsWithoutNestedTags() );
	}

	public function getAnchorsWithoutNestedTags() {
		if ( null !== $this->anchorsWithoutNestedTags ) {
			return $this->anchorsWithoutNestedTags;
		}

		preg_match_all( self::$allAnchorsRegex, $this->blockText, $allAnchorTags );

		$anchors = [];
		foreach ( (array) current( $allAnchorTags ) as $anchorTag ) {
			if ( preg_match( '/<(img|picture|video|audio|source|iframe|embed|object)\b/i', $anchorTag ) ) {
				continue;
			}
			if ( $this->textContains( 'wp-block-file', $anchorTag ) ) {
				continue;
			}
			$anchors[] = $anchorTag;
		}

		return $this->anchorsWithoutNestedTags = $anchors;
	}
}
