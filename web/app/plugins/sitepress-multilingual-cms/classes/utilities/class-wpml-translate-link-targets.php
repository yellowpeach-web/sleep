<?php

use WPML\Core\Component\Translation\Domain\Links\CollectorInterface;

class WPML_Translate_Link_Targets {

	private $absolute_links;
	private $permalinks_converter;

	public function __construct( AbsoluteLinks $absolute_links, WPML_Absolute_To_Permalinks $permalinks_converter ) {
		$this->absolute_links       = $absolute_links;
		$this->permalinks_converter = $permalinks_converter;
	}


	public function convert_text( $text ) {
		if ( is_string( $text ) ) {
			$text = $this->absolute_links->convert_text( $text );
			$text = $this->permalinks_converter->convert_text( $text );
		}

		return $text;
	}

	public function convert_text_for_display( $text ) {
		if ( is_string( $text ) ) {
			$text = $this->absolute_links->convert_text( $text );
			$text = $this->permalinks_converter->for_display()->convert_text( $text );
		}

		return $text;
	}

	public function is_internal_url( $url ) {
		$absolute_url = $this->absolute_links->convert_url( $url );
		return $url != $absolute_url || $this->absolute_links->is_home( $url );
	}

	public function convert_url( $url ) {
		$link = '<a href="' . $url . '">removeit</a>';
		$link = $this->convert_text( $link );

		return $this->unwrap_url( $link, $url );
	}

	public function convert_url_for_language( $url, $source_language, ?CollectorInterface $collector = null ) {
		$link = '<a href="' . $url . '">removeit</a>';
		$link = $this->absolute_links->convert_text_for_language( $link, $source_language, $collector );
		$link = $this->permalinks_converter->convert_text( $link );

		return $this->unwrap_url( $link, $url );
	}

	public function met_gone_target() {
		return $this->permalinks_converter->met_gone_target();
	}

	private function unwrap_url( $link, $url ) {
		if ( false === strpos( $link, '<a href="' ) || $this->met_gone_target() ) {
			return $url;
		}

		return str_replace( array( '<a href="', '">removeit</a>' ), array( '', '' ), $link );
	}

}
