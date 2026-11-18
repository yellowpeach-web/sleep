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
			$text = $this->mark_relative_links( $text );
			$text = $this->absolute_links->convert_text( $text );
			$text = $this->permalinks_converter->convert_text( $text );
			$text = $this->restore_relative_links( $text );
		}

		return $text;
	}

	public function convert_text_for_display( $text ) {
		if ( is_string( $text ) ) {
			$text = $this->mark_relative_links( $text );
			$text = $this->absolute_links->convert_text( $text );
			$text = $this->permalinks_converter->for_display()->convert_text( $text );
			$text = $this->restore_relative_links( $text );
		}

		return $text;
	}

	public function is_internal_url( $url ) {
		$absolute_url = $this->absolute_links->convert_url( $url );
		return $url != $absolute_url || $this->absolute_links->is_home( $url );
	}

	public function is_object_url( $url ) {
		return ! $this->absolute_links->is_home( $url ) && $url !== $this->absolute_links->convert_url( $url );
	}

	public function convert_url( $url ) {
		$link = '<a href="' . $url . '">removeit</a>';
		$link = $this->convert_text( $link );

		return $this->unwrap_url( $link, $url );
	}

	public function convert_url_for_language( $url, $source_language, ?CollectorInterface $collector = null ) {
		$link = '<a href="' . $url . '">removeit</a>';
		$link = $this->mark_relative_links( $link );
		$link = $this->absolute_links->convert_text_for_language( $link, $source_language, $collector );
		$link = $this->permalinks_converter->convert_text( $link );
		$link = $this->restore_relative_links( $link );

		return $this->unwrap_url( $link, $url );
	}

	private function mark_relative_links( $text ) {
		return preg_replace_callback(
			'@<a\b([^>]*?)href=("|\')([^"\']+)\2@i',
			function ( $matches ) {
				if ( 0 !== strpos( $matches[3], '/' ) || 0 === strpos( $matches[3], '//' ) ) {
					return $matches[0];
				}

				return '<a' . $matches[1] . ' data-wpml-relative-link="1" href=' . $matches[2] . $matches[3] . $matches[2];
			},
			$text
		);
	}

	private function restore_relative_links( $text ) {
		return preg_replace_callback(
			'@<a\b[^>]*\bdata-wpml-relative-link="1"[^>]*>@i',
			function ( $matches ) {
				$anchor = (string) preg_replace( '@\s+data-wpml-relative-link="1"@i', '', $matches[0], 1 );

				return (string) preg_replace_callback(
					'@\bhref=("|\')([^"\']+)\1@i',
					function ( $href ) {
						if ( 0 === strpos( $href[2], '/' ) && 0 !== strpos( $href[2], '//' ) ) {
							return $href[0];
						}

						return 'href=' . $href[1] . wp_make_link_relative( $href[2] ) . $href[1];
					},
					$anchor,
					1
				);
			},
			$text
		);
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
