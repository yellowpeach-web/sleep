<?php

namespace WPML\Media\Classes;

use WPML\FP\Obj;
use WPML\FP\Str;

class WPML_Non_Embedded_Pdf_Parser extends WPML_Media_Element_Parser {

	private static $non_embedded_pdf_expression = '/wp:file.*?class="[^"]*\bwp-block-file\b[^"]*".*?<a[^>]*?(href="[^"]+?\.pdf")[^>]*?>.*?\/wp:file/s';

	public function getMediaElements() {
		return $this->getFromTags();
	}

	public function getMediaSrcFromAttributes( $attrs ) {
		return Obj::propOr( '', 'href', $attrs );
	}

	protected function getFromTags() {
		return preg_match_all( self::$non_embedded_pdf_expression, $this->blockText, $matches ) ?
			$this->getAttachments( $matches ) : [];
	}

	public function validate() {
		return Str::includes( '<!-- wp:file', $this->blockText )
			|| Str::includes( 'pdf', $this->blockText );
	}
}
