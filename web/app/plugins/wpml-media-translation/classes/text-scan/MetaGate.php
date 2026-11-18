<?php

namespace WPML\Media\TextScan;

class MetaGate {

	const SKIP_FORMAT = 'skip:format';
	const SKIP_SMALL  = 'skip:small';
	const SKIP_PHOTO  = 'skip:photo';
	const PASS        = 'pass';

	const MIMES_FILTER  = 'wpml_media_text_scan_mimes';
	const PHOTOS_FILTER = 'wpml_media_text_scan_skip_photos';

	const MIN_SIDE          = 64;
	const MAX_GIF_SCAN_BYTES = 20971520;

	private const DEFAULT_MIMES = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];
	private const SVG_MIME      = 'image/svg+xml';
	private const GIF_MIME      = 'image/gif';
	private const GIF_FRAME     = '/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s';

	public function verdict( $id, $metadata = null ) {
		try {
			$id = (int) $id;

			if ( $this->isFormatSkip( $id ) ) {
				return self::SKIP_FORMAT;
			}

			if ( null === $metadata ) {
				$metadata = wp_get_attachment_metadata( $id );
			}
			$metadata = is_array( $metadata ) ? $metadata : [];

			if ( $this->isSmall( $id, $metadata ) ) {
				return self::SKIP_SMALL;
			}

			if ( $this->isPhoto( $metadata ) ) {
				return self::SKIP_PHOTO;
			}
		} catch ( \Throwable $e ) {
			return self::PASS;
		}

		return self::PASS;
	}

	public function isAnimatedGif( $file ) {
		if ( ! self::isReadableFile( $file ) ) {
			return false;
		}

		$size = @filesize( $file );
		if ( false === $size ) {
			return false;
		}
		if ( $size > self::MAX_GIF_SCAN_BYTES ) {
			return true;
		}

		$bytes = @file_get_contents( $file );
		if ( ! is_string( $bytes ) ) {
			return false;
		}

		return (int) preg_match_all( self::GIF_FRAME, $bytes ) > 1;
	}

	public static function reasonOf( $verdict ) {
		$reasons = [
			self::SKIP_FORMAT => 'format',
			self::SKIP_SMALL  => 'small',
			self::SKIP_PHOTO  => 'photo',
		];

		return is_string( $verdict ) && isset( $reasons[ $verdict ] ) ? $reasons[ $verdict ] : null;
	}

	private function isFormatSkip( $id ) {
		$mime = get_post_mime_type( $id );
		$mime = is_string( $mime ) ? $mime : '';

		$mimes = apply_filters( self::MIMES_FILTER, self::DEFAULT_MIMES );
		$mimes = is_array( $mimes ) ? $mimes : self::DEFAULT_MIMES;

		if ( ! in_array( $mime, $mimes, true ) ) {
			return true;
		}

		if ( self::SVG_MIME === $mime ) {
			return true;
		}

		if ( self::GIF_MIME === $mime ) {
			$file = get_attached_file( $id );

			return is_string( $file ) && $this->isAnimatedGif( $file );
		}

		return false;
	}

	private function isSmall( $id, array $metadata ) {
		$width  = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		$height = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;

		if ( $width <= 0 || $height <= 0 ) {
			list( $width, $height ) = $this->fileDimensions( $id );
		}

		if ( $width <= 0 || $height <= 0 ) {
			return false;
		}

		return min( $width, $height ) < self::MIN_SIDE;
	}

	private function isPhoto( array $metadata ) {
		$camera = isset( $metadata['image_meta']['camera'] ) ? $metadata['image_meta']['camera'] : '';

		if ( ! is_string( $camera ) || '' === trim( $camera ) ) {
			return false;
		}

		return (bool) apply_filters( self::PHOTOS_FILTER, true );
	}

	private function fileDimensions( $id ) {
		$file = get_attached_file( $id );
		if ( ! self::isReadableFile( $file ) ) {
			return [ 0, 0 ];
		}

		$size = @getimagesize( $file );

		return is_array( $size ) ? [ (int) $size[0], (int) $size[1] ] : [ 0, 0 ];
	}

	private static function isReadableFile( $file ) {
		return is_string( $file ) && '' !== $file && @is_file( $file ) && @is_readable( $file );
	}
}
