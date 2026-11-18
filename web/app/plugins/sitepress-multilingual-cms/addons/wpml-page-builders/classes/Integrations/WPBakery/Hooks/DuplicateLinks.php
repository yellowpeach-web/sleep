<?php

namespace WPML\Compatibility\WPBakery\Hooks;

use WPML\PB\Duplication\TargetLanguageLinks;

class DuplicateLinks implements \IWPML_DIC_Action, \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_REST_Action {

	const URL_KEY = 'url';

	private $wpdb;

	private $targetLanguageLinks;

	public function __construct( \wpdb $wpdb, TargetLanguageLinks $targetLanguageLinks ) {
		$this->wpdb                = $wpdb;
		$this->targetLanguageLinks = $targetLanguageLinks;
	}

	public function add_hooks() {
		add_action( 'icl_make_duplicate', [ $this, 'convertLinks' ], 10, 4 );
	}

	public function convertLinks( $masterPostId, $lang, $postArray, $duplicatePostId ) {
		$wpdb = $this->wpdb;
		$content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $duplicatePostId ) );

		if ( ! is_string( $content ) || false === strpos( $content, '="' . self::URL_KEY . ':' ) ) {
			return;
		}

		$converted = $this->targetLanguageLinks->convert(
			$lang,
			function ( \WPML_Translate_Link_Targets $translateLinkTargets ) use ( $content ) {
				return $this->convertContent( $content, $translateLinkTargets );
			}
		);

		if ( $converted !== $content ) {
			$wpdb->update( $wpdb->posts, [ 'post_content' => $converted ], [ 'ID' => $duplicatePostId ] );
		}
	}

	private function convertContent( string $content, \WPML_Translate_Link_Targets $translateLinkTargets ): string {
		$converted = preg_replace_callback(
			'/="(' . self::URL_KEY . ':[^"]*)"/',
			function ( $matches ) use ( $translateLinkTargets ) {
				return '="' . $this->convertVcLink( $matches[1], $translateLinkTargets ) . '"';
			},
			$content
		);

		return is_string( $converted ) ? $converted : $content;
	}

	private function convertVcLink( string $vcLink, \WPML_Translate_Link_Targets $translateLinkTargets ): string {
		$parts = explode( '|', $vcLink );

		foreach ( $parts as $index => $part ) {
			$pair = explode( ':', $part, 2 );
			if ( self::URL_KEY !== $pair[0] || ! isset( $pair[1] ) || '' === $pair[1] ) {
				continue;
			}

			$url       = rawurldecode( $pair[1] );
			$converted = $translateLinkTargets->convert_url( $url );
			if ( is_string( $converted ) && $converted !== $url ) {
				$parts[ $index ] = self::URL_KEY . ':' . rawurlencode( $converted );
			}
		}

		return implode( '|', $parts );
	}
}
