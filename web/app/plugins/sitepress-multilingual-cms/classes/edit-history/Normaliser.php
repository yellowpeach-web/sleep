<?php

namespace WPML\EditHistory;

class Normaliser {

	const PRESERVES_WHITESPACE = 'pre';

	const PRESERVING_BLOCKS = [ 'core/verse', 'core/preformatted' ];

	private $sticky;

	private $attachmentId;

	private $trid;

	private $autop;

	public function __construct( callable $sticky, callable $attachmentId, callable $trid, callable $autop ) {
		$this->sticky       = $sticky;
		$this->attachmentId = $attachmentId;
		$this->trid         = $trid;
		$this->autop        = $autop;
	}

	public static function create(): self {
		return new self(
			fn( string $html ) => (string) \WPML\Container\make( \AbsoluteLinks::class )->convert_text( $html ),
			fn( string $url ) => (int) \WPML\LIB\WP\Attachment::idFromUrl( \WPML_Media_Attachment_By_URL::normalizeUrl( $url ) ),
			fn( int $postId ) => (int) apply_filters( 'wpml_element_trid', null, $postId, 'post_' . get_post_type( $postId ) ),
			'wpautop'
		);
	}

	public function translationHash( array $units ): string {
		return UnitList::hash( $this->translation( $units ) );
	}

	public function originalHash( array $units ): string {
		return UnitList::hash( $this->original( $units ) );
	}

	public function translation( array $units ): array {
		return array_map(
			function ( array $unit ) {
				$unit['x'] = $this->text( (string) ( $unit['x'] ?? '' ) );

				return $unit;
			},
			$units
		);
	}

	public function original( array $units ): array {
		return array_map(
			function ( array $unit ) {
				$text      = $this->text( (string) ( $unit['x'] ?? '' ) );
				$unit['x'] = empty( $unit[ self::PRESERVES_WHITESPACE ] ) ? self::fold( $text ) : $text;

				return $unit;
			},
			$units
		);
	}

	private function text( string $text ): string {
		$text = str_replace( [ "\r\n", "\r" ], "\n", $text );
		$text = $this->links( $text );
		$text = $this->media( $text );
		$text = self::decode( $text );
		if ( '' !== trim( $text ) ) {
			$text = ( $this->autop )( $text );
		}

		return (string) preg_replace( '/<(br|hr|img|wbr|input|source)\b([^>]*?)\s*\/?>/i', '<$1$2>', $text );
	}

	private function links( string $text ): string {
		if ( false === stripos( $text, 'href' ) ) {
			return $text;
		}

		return (string) preg_replace_callback(
			'/\bhref=(["\'])([^"\']*?[?&](?:p|page_id)=(\d+)[^"\'#]*)(#[^"\']*)?\1/i',
			fn( array $match ) => 'href=' . $match[1] . 'wpml-link:' . $this->post( (int) $match[3] ) . ( $match[4] ?? '' ) . $match[1],
			( $this->sticky )( $text )
		);
	}

	private function media( string $text ): string {
		if ( false === stripos( $text, 'src=' ) && false === strpos( $text, 'wp-image-' ) && false === strpos( $text, 'attachment_' ) ) {
			return $text;
		}

		$text = (string) preg_replace_callback(
			'/(<(?:img|source|video|audio)\b[^>]*?\bsrc=)(["\'])([^"\']+)\2/i',
			function ( array $match ) {
				$id = ( $this->attachmentId )( $match[3] );
				if ( ! $id ) {
					return $match[0];
				}
				$size = preg_match( '/-(\d+x\d+)\.[a-z0-9]+$/i', $match[3], $sizes ) ? ':' . $sizes[1] : '';

				return $match[1] . $match[2] . 'wpml-media:' . $this->post( $id ) . $size . $match[2];
			},
			$text
		);

		return (string) preg_replace_callback(
			'/\b(wp-image-|attachment_)(\d+)\b/',
			fn( array $match ) => $match[1] . $this->post( (int) $match[2] ),
			$text
		);
	}

	private function post( int $postId ): string {
		$trid = ( $this->trid )( $postId );

		return $trid ? 't' . $trid : 'p' . $postId;
	}

	private static function decode( string $text ): string {
		if ( false === strpos( $text, '&' ) ) {
			return $text;
		}

		return (string) preg_replace_callback(
			'/&(?:#\d+|#x[0-9a-f]+|[a-z][a-z0-9]*);/i',
			function ( array $match ) {
				$character = html_entity_decode( $match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				return [ '<' => '&lt;', '>' => '&gt;' ][ $character ] ?? $character;
			},
			$text
		);
	}

	private static function fold( string $text ): string {
		$parts = preg_split( '/(<pre\b.*?<\/pre>)/is', $text, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: [ $text ];
		foreach ( $parts as $index => $part ) {
			if ( 0 === $index % 2 ) {
				$parts[ $index ] = (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $part );
			}
		}

		return trim( implode( '', $parts ), ' ' );
	}
}
