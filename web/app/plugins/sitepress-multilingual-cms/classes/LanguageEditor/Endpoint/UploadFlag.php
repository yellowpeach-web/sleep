<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;

class UploadFlag implements IHandler {

	const MAX_BYTES = 100000;

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden', 'message' => __( 'You are not allowed to upload a flag.', 'sitepress' ) ) );
		}

		$filename = sanitize_file_name( (string) $data->get( 'filename', '' ) );
		$content  = (string) $data->get( 'content', '' );

		if ( '' === trim( $content ) ) {
			return Either::left( array( 'error' => 'empty', 'message' => __( 'The uploaded file is empty.', 'sitepress' ) ) );
		}

		if ( strlen( $content ) > self::MAX_BYTES ) {
			return Either::left(
				array(
					'error'   => 'too_big',
					// translators: %s is a human-readable file size, e.g. "100 KB".
					'message' => sprintf( __( 'The flag is too large. Maximum size is %s.', 'sitepress' ), size_format( self::MAX_BYTES ) ),
				)
			);
		}

		$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( '' !== $filename && 'svg' !== $ext ) {
			return Either::left( array( 'error' => 'not_allowed', 'message' => __( 'Only SVG flags can be uploaded.', 'sitepress' ) ) );
		}

		$clean = self::sanitizeSvg( $content );
		if ( null === $clean ) {
			return Either::left( array( 'error' => 'corrupt', 'message' => __( 'The file is not a valid SVG image.', 'sitepress' ) ) );
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return Either::left( array( 'error' => 'no_upload_dir', 'message' => __( 'The uploads directory is not available.', 'sitepress' ) ) );
		}

		$dir = trailingslashit( $upload['basedir'] ) . 'flags';
		if ( ! wp_mkdir_p( $dir ) || ! is_writable( $dir ) ) {
			return Either::left( array( 'error' => 'not_writable', 'message' => __( 'The flags folder is not writable.', 'sitepress' ) ) );
		}

		$base = preg_replace( '/\.[^.]+$/', '', $filename );
		$base = '' === $base ? 'flag' : $base;
		$name = wp_unique_filename( $dir, $base . '.svg' );
		$path = $dir . '/' . $name;

		if ( false === file_put_contents( $path, $clean ) ) {
			return Either::left( array( 'error' => 'write_failed', 'message' => __( 'The flag could not be saved. Please try again.', 'sitepress' ) ) );
		}

		return Either::right(
			array(
				'flag' => $name,
				'url'  => trailingslashit( $upload['baseurl'] ) . 'flags/' . $name,
			)
		);
	}

	private static function sanitizeSvg( $content ) {
		if ( preg_match( '/<!DOCTYPE/i', $content ) ) {
			return null;
		}

		$dom        = new \DOMDocument();
		$prevErrors = libxml_use_internal_errors( true );
		$prevLoader = null;
		if ( PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			$prevLoader = libxml_disable_entity_loader( true );
		}

		$ok = $dom->loadXML( $content, LIBXML_NONET );

		libxml_clear_errors();
		libxml_use_internal_errors( $prevErrors );
		if ( null !== $prevLoader ) {
			libxml_disable_entity_loader( $prevLoader );
		}

		if ( ! $ok || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return null;
		}

		foreach ( iterator_to_array( $dom->getElementsByTagName( '*' ) ) as $el ) {
			$tag = strtolower( $el->localName );
			if ( in_array( $tag, array( 'script', 'foreignobject' ), true ) ) {
				if ( $el->parentNode ) {
					$el->parentNode->removeChild( $el );
				}
				continue;
			}
			if ( ! $el->hasAttributes() ) {
				continue;
			}
			foreach ( iterator_to_array( $el->attributes ) as $attr ) {
				$name  = strtolower( $attr->nodeName );
				$value = (string) $attr->nodeValue;
				$isEvent      = 0 === strpos( $name, 'on' );
				$isScriptHref = in_array( $name, array( 'href', 'xlink:href' ), true )
					&& preg_match( '/^\s*(javascript|data)\s*:/i', $value );
				if ( $isEvent || $isScriptHref ) {
					$el->removeAttribute( $attr->nodeName );
				}
			}
		}

		return $dom->saveXML();
	}
}
