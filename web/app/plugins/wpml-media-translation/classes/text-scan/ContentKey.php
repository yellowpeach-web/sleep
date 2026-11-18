<?php

namespace WPML\Media\TextScan;

class ContentKey {

	const V              = 1;
	const THUMB_SIDE     = 32;
	const CELL_TOLERANCE = 6;
	const ASPECT_TOLERANCE = 0.01;
	const MAX_FULL_PIXELS  = 4000000;
	const MAX_SIZE_SIDE    = 1536;

	const SRC_FULL = 'full';
	const SRC_NONE = 'none';

	private $clock;

	public function __construct( $clock = null ) {
		$this->clock = $clock ?: function () {
			return microtime( true );
		};
	}

	public function compute( $id, $metadata = null ) {
		$id   = (int) $id;
		$file = get_attached_file( $id );
		if ( ! self::isReadableFile( $file ) ) {
			return null;
		}

		$sha1 = sha1_file( $file );
		if ( ! is_string( $sha1 ) ) {
			return null;
		}

		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $id );
		}
		$metadata = is_array( $metadata ) ? $metadata : [];

		list( $width, $height ) = self::dimensions( $file, $metadata );

		list( $src, $source ) = self::thumbSource( $file, $width, $height, $metadata );
		$thumb = null === $source ? null : self::luminanceThumb( $source );
		if ( null === $thumb ) {
			$src = self::SRC_NONE;
		}

		return [
			'v'     => self::V,
			'sha1'  => $sha1,
			'w'     => $width,
			'h'     => $height,
			'thumb' => $thumb,
			'src'   => $src,
			'at'    => (int) floor( call_user_func( $this->clock ) ),
		];
	}

	public function sha1( $id ) {
		$file = get_attached_file( (int) $id );
		if ( ! self::isReadableFile( $file ) ) {
			return null;
		}

		$sha1 = sha1_file( $file );

		return is_string( $sha1 ) ? $sha1 : null;
	}

	public static function changed( $old, $new ) {
		if ( ! is_array( $old ) ) {
			return false;
		}
		if ( ! is_array( $new ) ) {
			return true;
		}

		$sha1Old = isset( $old['sha1'] ) ? $old['sha1'] : null;
		$sha1New = isset( $new['sha1'] ) ? $new['sha1'] : null;
		if ( is_string( $sha1Old ) && '' !== $sha1Old && $sha1Old === $sha1New ) {
			return false;
		}

		$thumbOld = self::thumbBytes( $old );
		$thumbNew = self::thumbBytes( $new );
		if ( null === $thumbOld || null === $thumbNew ) {
			return true;
		}

		$w1 = self::side( $old, 'w' );
		$h1 = self::side( $old, 'h' );
		$w2 = self::side( $new, 'w' );
		$h2 = self::side( $new, 'h' );
		if ( $w1 > 0 && $h1 > 0 && $w2 > 0 && $h2 > 0 ) {
			$aspectOld = $w1 / $h1;
			$aspectNew = $w2 / $h2;
			if ( abs( $aspectOld - $aspectNew ) / $aspectOld > self::ASPECT_TOLERANCE ) {
				return true;
			}
		}

		$cells = self::THUMB_SIDE * self::THUMB_SIDE;
		for ( $i = 0; $i < $cells; $i++ ) {
			if ( abs( ord( $thumbOld[ $i ] ) - ord( $thumbNew[ $i ] ) ) > self::CELL_TOLERANCE ) {
				return true;
			}
		}

		return false;
	}

	public static function luminanceThumb( $file ) {
		$image = Thumbnail::decodeOnWhite( $file );
		if ( null === $image ) {
			return null;
		}

		$side  = self::THUMB_SIDE;
		$small = @imagecreatetruecolor( $side, $side );
		if ( ! ( is_resource( $small ) || ( is_object( $small ) && is_a( $small, 'GdImage' ) ) ) ) {
			imagedestroy( $image );

			return null;
		}

		imagecopyresampled( $small, $image, 0, 0, 0, 0, $side, $side, imagesx( $image ), imagesy( $image ) );
		imagedestroy( $image );

		$bytes = '';
		for ( $y = 0; $y < $side; $y++ ) {
			for ( $x = 0; $x < $side; $x++ ) {
				$rgb    = imagecolorat( $small, $x, $y );
				$luma   = (int) round( 0.299 * ( ( $rgb >> 16 ) & 0xFF ) + 0.587 * ( ( $rgb >> 8 ) & 0xFF ) + 0.114 * ( $rgb & 0xFF ) );
				$bytes .= chr( max( 0, min( 255, $luma ) ) );
			}
		}
		imagedestroy( $small );

		return base64_encode( $bytes );
	}

	private static function dimensions( $file, array $metadata ) {
		$width  = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		$height = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;
		if ( $width > 0 && $height > 0 ) {
			return [ $width, $height ];
		}

		$size = @getimagesize( $file );
		if ( is_array( $size ) && (int) $size[0] > 0 && (int) $size[1] > 0 ) {
			return [ (int) $size[0], (int) $size[1] ];
		}

		return [ 0, 0 ];
	}

	public static function thumbSourceName( $width, $height, array $metadata ) {
		$width  = (int) $width;
		$height = (int) $height;
		if ( $width <= 0 || $height <= 0 ) {
			return self::SRC_NONE;
		}

		$sizes    = isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ? $metadata['sizes'] : [];
		$bestName = null;
		$bestArea = 0;
		foreach ( $sizes as $name => $size ) {
			if ( ! is_array( $size ) || ! isset( $size['file'], $size['width'], $size['height'] ) || ! is_string( $size['file'] ) ) {
				continue;
			}
			$sizeWidth  = (int) $size['width'];
			$sizeHeight = (int) $size['height'];
			if ( $sizeWidth <= 0 || $sizeHeight <= 0 || max( $sizeWidth, $sizeHeight ) > self::MAX_SIZE_SIDE ) {
				continue;
			}
			if ( ! self::sameAspect( $width, $height, $sizeWidth, $sizeHeight ) ) {
				continue;
			}
			$area = $sizeWidth * $sizeHeight;
			if ( $area > $bestArea ) {
				$bestName = (string) $name;
				$bestArea = $area;
			}
		}

		if ( null !== $bestName ) {
			return $bestName;
		}

		return $width * $height <= self::MAX_FULL_PIXELS ? self::SRC_FULL : self::SRC_NONE;
	}

	public static function sameAspect( $width1, $height1, $width2, $height2 ) {
		$aspect1 = $width1 / $height1;
		$aspect2 = $width2 / $height2;

		return abs( $aspect1 - $aspect2 ) / $aspect1 <= self::ASPECT_TOLERANCE;
	}

	private static function thumbSource( $file, $width, $height, array $metadata ) {
		$name = self::thumbSourceName( $width, $height, $metadata );
		if ( self::SRC_NONE === $name ) {
			return [ self::SRC_NONE, null ];
		}
		if ( self::SRC_FULL === $name ) {
			return [ self::SRC_FULL, $file ];
		}

		$sizeFile = dirname( $file ) . '/' . basename( $metadata['sizes'][ $name ]['file'] );
		if ( self::isReadableFile( $sizeFile ) ) {
			return [ $name, $sizeFile ];
		}

		return $width * $height <= self::MAX_FULL_PIXELS ? [ self::SRC_FULL, $file ] : [ self::SRC_NONE, null ];
	}

	private static function thumbBytes( array $key ) {
		if ( ! isset( $key['thumb'] ) || ! is_string( $key['thumb'] ) || '' === $key['thumb'] ) {
			return null;
		}

		$bytes = base64_decode( $key['thumb'], true );

		return is_string( $bytes ) && strlen( $bytes ) === self::THUMB_SIDE * self::THUMB_SIDE ? $bytes : null;
	}

	private static function side( array $key, $name ) {
		return isset( $key[ $name ] ) && is_numeric( $key[ $name ] ) ? (int) $key[ $name ] : 0;
	}

	private static function isReadableFile( $file ) {
		return is_string( $file ) && '' !== $file && @is_file( $file ) && @is_readable( $file );
	}
}
