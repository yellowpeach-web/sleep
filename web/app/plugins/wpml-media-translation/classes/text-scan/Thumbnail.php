<?php

namespace WPML\Media\TextScan;

class Thumbnail {

	const LONG_SIDE = 512;
	const QUALITY   = 80;
	const MIME      = 'image/jpeg';

	private const ALT_META = '_wp_attachment_image_alt';

	public function forClassifier( $id ) {
		$id   = (int) $id;
		$file = get_attached_file( $id );
		if ( ! self::isReadableFile( $file ) ) {
			return null;
		}

		$metadata = wp_get_attachment_metadata( $id );
		$metadata = is_array( $metadata ) ? $metadata : [];

		$source = $this->sourceFile( $id, $file, $metadata );
		if ( null === $source ) {
			return null;
		}

		$image = self::decodeOnWhite( $source );
		if ( null === $image ) {
			return null;
		}

		$image = self::shrinkTo( $image, self::LONG_SIDE );
		if ( null === $image ) {
			return null;
		}

		ob_start();
		$written = imagejpeg( $image, null, self::QUALITY );
		$jpeg    = ob_get_clean();
		imagedestroy( $image );

		if ( ! $written || ! is_string( $jpeg ) || '' === $jpeg ) {
			return null;
		}

		return base64_encode( $jpeg );
	}

	public function classifierItem( $id ) {
		$id    = (int) $id;
		$image = $this->forClassifier( $id );
		if ( null === $image ) {
			return null;
		}

		$file  = get_attached_file( $id );
		$alt   = get_post_meta( $id, self::ALT_META, true );
		$title = get_post_field( 'post_title', $id, 'raw' );

		return [
			'id'        => $id,
			'image'     => $image,
			'mime'      => self::MIME,
			'file_name' => basename( is_string( $file ) ? $file : '' ),
			'alt'       => is_scalar( $alt ) ? (string) $alt : '',
			'title'     => is_scalar( $title ) ? (string) $title : '',
		];
	}

	public static function decodeOnWhite( $file ) {
		if ( ! function_exists( 'imagecreatefromstring' ) || ! self::isReadableFile( $file ) ) {
			return null;
		}

		$bytes = @file_get_contents( $file );
		if ( ! is_string( $bytes ) || '' === $bytes ) {
			return null;
		}

		if ( self::isWebp( $bytes ) && ! self::gdDecodesWebp() ) {
			return null;
		}

		$source = @imagecreatefromstring( $bytes );
		unset( $bytes );
		if ( ! self::isImage( $source ) ) {
			return null;
		}

		$width  = imagesx( $source );
		$height = imagesy( $source );
		$canvas = $width > 0 && $height > 0 ? @imagecreatetruecolor( $width, $height ) : false;
		if ( ! self::isImage( $canvas ) ) {
			imagedestroy( $source );

			return null;
		}

		imagefilledrectangle( $canvas, 0, 0, $width - 1, $height - 1, imagecolorallocate( $canvas, 255, 255, 255 ) );
		imagealphablending( $canvas, true );
		imagecopy( $canvas, $source, 0, 0, 0, 0, $width, $height );
		imagedestroy( $source );

		return $canvas;
	}

	public static function sourceName( $mime, $width, $height, array $metadata ) {
		$width  = (int) $width;
		$height = (int) $height;
		if ( $width <= 0 || $height <= 0 ) {
			return null;
		}

		$sizes    = isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ? $metadata['sizes'] : [];
		$fullLong = max( $width, $height );
		$bestName = null;
		$bestLong = 0;
		foreach ( $sizes as $name => $size ) {
			if ( ! is_array( $size ) || ! isset( $size['file'], $size['width'], $size['height'], $size['mime-type'] ) ) {
				continue;
			}
			if ( ! is_string( $mime ) || $size['mime-type'] !== $mime || ! is_string( $size['file'] ) ) {
				continue;
			}
			$sizeWidth  = (int) $size['width'];
			$sizeHeight = (int) $size['height'];
			if ( $sizeWidth <= 0 || $sizeHeight <= 0 ) {
				continue;
			}
			$long = max( $sizeWidth, $sizeHeight );
			if ( $long < self::LONG_SIDE || $long >= $fullLong ) {
				continue;
			}
			if ( ! ContentKey::sameAspect( $width, $height, $sizeWidth, $sizeHeight ) ) {
				continue;
			}
			if ( null === $bestName || $long < $bestLong ) {
				$bestName = (string) $name;
				$bestLong = $long;
			}
		}

		if ( null !== $bestName ) {
			return $bestName;
		}

		return $width * $height <= ContentKey::MAX_FULL_PIXELS ? ContentKey::SRC_FULL : null;
	}

	private function sourceFile( $id, $file, array $metadata ) {
		$mime = get_post_mime_type( $id );
		list( $width, $height ) = $this->attachedSize( $file, $metadata );

		$name = self::sourceName( is_string( $mime ) ? $mime : '', $width, $height, $metadata );
		if ( null === $name ) {
			return null;
		}
		if ( ContentKey::SRC_FULL === $name ) {
			return $file;
		}

		$sizeFile = dirname( $file ) . '/' . basename( $metadata['sizes'][ $name ]['file'] );
		if ( self::isReadableFile( $sizeFile ) ) {
			return $sizeFile;
		}

		return $width * $height <= ContentKey::MAX_FULL_PIXELS ? $file : null;
	}

	private function attachedSize( $file, array $metadata ) {
		$width  = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		$height = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;

		if ( $width <= 0 || $height <= 0 ) {
			$size   = @getimagesize( $file );
			$width  = is_array( $size ) ? (int) $size[0] : 0;
			$height = is_array( $size ) ? (int) $size[1] : 0;
		}

		return $width > 0 && $height > 0 ? [ $width, $height ] : [ 0, 0 ];
	}

	private static function shrinkTo( $image, $longSide ) {
		$width  = imagesx( $image );
		$height = imagesy( $image );
		$long   = max( $width, $height );
		if ( $long <= $longSide ) {
			return $image;
		}

		$newWidth  = max( 1, (int) round( $width * $longSide / $long ) );
		$newHeight = max( 1, (int) round( $height * $longSide / $long ) );
		$small     = @imagecreatetruecolor( $newWidth, $newHeight );
		if ( ! self::isImage( $small ) ) {
			imagedestroy( $image );

			return null;
		}

		imagecopyresampled( $small, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height );
		imagedestroy( $image );

		return $small;
	}

	private static function isWebp( $bytes ) {
		return strlen( $bytes ) >= 12 && 'RIFF' === substr( $bytes, 0, 4 ) && 'WEBP' === substr( $bytes, 8, 4 );
	}

	private static function gdDecodesWebp() {
		if ( ! function_exists( 'gd_info' ) ) {
			return false;
		}
		$info = gd_info();

		return ! empty( $info['WebP Support'] );
	}

	private static function isImage( $image ) {
		return is_resource( $image ) || ( is_object( $image ) && is_a( $image, 'GdImage' ) );
	}

	private static function isReadableFile( $file ) {
		return is_string( $file ) && '' !== $file && @is_file( $file ) && @is_readable( $file );
	}
}
