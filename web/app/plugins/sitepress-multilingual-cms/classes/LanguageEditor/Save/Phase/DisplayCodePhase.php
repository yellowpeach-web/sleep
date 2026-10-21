<?php

namespace WPML\LanguageEditor\Save\Phase;

class DisplayCodePhase implements PhaseProcessor {

	const ID         = 'display_code';
	const CHUNK_SIZE = 20;

	private $wpdb;

	private $context;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function getId() {
		return self::ID;
	}

	public function applies( array $change ) {
		$old = $this->oldCode( $change );
		$new = $this->newCode( $change );
		return '' !== $old && '' !== $new && $old !== $new;
	}

	public function getTotal( array $change ) {
		if ( ! $this->applies( $change ) ) {
			return 0;
		}
		return max( 1, $this->countCandidates( $change ) );
	}

	private function countCandidates( array $change ) {
		$like = $this->candidateLike( $change );
		if ( null === $like ) {
			return 0;
		}
		$wpdb = $this->wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
				 WHERE p.post_content LIKE %s
				 AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} m
					WHERE m.post_id = p.ID AND m.meta_key = %s
				 )",
				$like,
				$this->scannedMetaKey( $change )
			)
		);
	}

	public function getChunkSize() {
		return self::chunkSize();
	}

	private static function chunkSize() {
		return max( 1, (int) apply_filters( 'wpml_language_editor_save_chunk_size', self::CHUNK_SIZE, self::ID ) );
	}

	public function isSkippable() {
		return false;
	}

	public function processChunk( array $change, $offset ) {
		$wpdb = $this->wpdb;

		$codeError = \WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat::validateCode(
			$this->newCode( $change )
		);
		if ( '' !== $codeError ) {
			return PhaseResult::hardFail( $codeError );
		}


		$like = $this->candidateLike( $change );
		if ( null === $like ) {
			return PhaseResult::ok( 0 );
		}

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 WHERE p.post_content LIKE %s
				 AND NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} m
					WHERE m.post_id = p.ID AND m.meta_key = %s
				 )
				 ORDER BY p.ID ASC LIMIT %d",
				$like,
				$this->scannedMetaKey( $change ),
				self::chunkSize()
			)
		);

		$processed = 0;
		$skipped   = [];
		foreach ( $ids as $postId ) {
			$content = $wpdb->get_var(
				$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $postId )
			);
			if ( null === $content ) {
				$processed++;
				continue;
			}

			$updated = $this->rewrite( $content, $change );

			if ( $updated === $content ) {
				$skipped[] = [ 'item' => $postId, 'reason' => 'no_anchored_url_match' ];
				$this->markScanned( $postId, $change );
				$processed++;
				continue;
			}

			$ok = $wpdb->update( $wpdb->posts, [ 'post_content' => $updated ], [ 'ID' => $postId ] );
			if ( false === $ok ) {
				$skipped[] = [ 'item' => $postId, 'reason' => 'post_content_update_failed' ];
				$this->markScanned( $postId, $change );
				$processed++;
				continue;
			}
			clean_post_cache( $postId );
			$processed++;
		}

		return PhaseResult::ok( $processed, $skipped );
	}

	private function rewrite( $content, array $change ) {
		$ctx = $this->context();
		$old = preg_quote( $this->oldCode( $change ), '#' );

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === $ctx['negotiation'] ) {
			$new = $this->newCode( $change );
			return preg_replace(
				'#([?&]lang=)' . $old . '(?=$|[&"\'<\s>])#',
				'${1}' . $new,
				$content
			);
		}

		$out = $content;
		foreach ( $ctx['homeRegexes'] as $prefixRegex ) {
			$out = preg_replace(
				'#' . $prefixRegex . $old . '(?=/|["\'\s<>)]|$)#',
				'${1}' . $this->newCode( $change ),
				$out
			);
		}
		return $out;
	}

	private function candidateLike( array $change ) {
		$ctx = $this->context();
		$old = $this->oldCode( $change );

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === $ctx['negotiation'] ) {
			return null;
		}
		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === $ctx['negotiation'] ) {
			return '%' . $this->wpdb->esc_like( 'lang=' . $old ) . '%';
		}
		return '%' . $this->wpdb->esc_like( '/' . trim( $old, '/' ) . '/' ) . '%';
	}

	private function context() {
		if ( null !== $this->context ) {
			return $this->context;
		}

		$negotiation = WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY;
		global $sitepress;
		if ( $sitepress && method_exists( $sitepress, 'get_setting' ) ) {
			$negotiation = (int) $sitepress->get_setting( 'language_negotiation_type', WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY );
		}

		$home = rtrim( (string) get_home_url(), '/' );
		$path = (string) wp_parse_url( $home . '/', PHP_URL_PATH );
		$path = '/' . trim( (string) $path, '/' );
		$path = '/' === $path ? '/' : $path . '/';

		$hostNoScheme = preg_replace( '#^https?://#', '', $home );
		$absInner     = 'https?://' . preg_quote( $hostNoScheme, '#' ) . ( '/' === $path ? '/' : preg_quote( $path, '#' ) );
		$absRegex     = '(' . $absInner . ')';

		$relInner = ( '/' === $path ? '/' : preg_quote( $path, '#' ) );
		$relRegex = '(?<=["\'\s(])(' . $relInner . ')';

		$this->context = [
			'negotiation' => $negotiation,
			'homeRegexes' => array_values( array_unique( [ $absRegex, $relRegex ] ) ),
		];
		return $this->context;
	}

	private function oldCode( array $change ) {
		return isset( $change['display_code']['old'] ) ? trim( (string) $change['display_code']['old'], '/' ) : '';
	}

	private function newCode( array $change ) {
		return isset( $change['display_code']['new'] ) ? trim( (string) $change['display_code']['new'], '/' ) : '';
	}

	private function markScanned( $postId, array $change ) {
		add_post_meta( (int) $postId, $this->scannedMetaKey( $change ), 1, true );
	}

	private function scannedMetaKey( array $change ) {
		return '_wpml_dc_scanned_' . md5( $this->oldCode( $change ) . '|' . $this->newCode( $change ) );
	}
}
