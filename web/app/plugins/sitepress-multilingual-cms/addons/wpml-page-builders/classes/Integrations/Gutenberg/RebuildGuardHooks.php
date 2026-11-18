<?php

namespace WPML\PB\Gutenberg;

use WPML\EditHistory\InternalWrite;
use WPML\EditHistory\Normaliser;
use WPML\EditHistory\PostUnits;
use WPML\EditHistory\RebuildGuard;
use WPML\EditHistory\Recorder;
use WPML\EditHistory\State;
use WPML\EditHistory\Subject;

class RebuildGuardHooks implements Integration {

	const PRIORITY = PHP_INT_MAX - 10;

	private $guard;

	private $recorder;

	private $units;

	private $normaliser;

	private $overlaid = [];

	public function __construct( RebuildGuard $guard, Recorder $recorder, PostUnits $units, Normaliser $normaliser ) {
		$this->guard      = $guard;
		$this->recorder   = $recorder;
		$this->units      = $units;
		$this->normaliser = $normaliser;
	}

	public function add_hooks() {
		add_filter( 'wpml_pb_gutenberg_translated_content_before_save', [ $this, 'guard' ], self::PRIORITY, 4 );
		add_action( 'wp_after_insert_post', [ $this, 'recordOverlay' ], EditHistoryHooks::PRIORITY, 2 );
	}

	public function guard( $content, $translatedId, $lang, $original ) {
		if ( ! is_string( $content ) || ! $original instanceof \WP_Post ) {
			return $content;
		}

		$translation = get_post( (int) $translatedId );
		if ( ! $translation instanceof \WP_Post ) {
			return $content;
		}

		$state = $this->guard->holds( Subject::post( $translation->ID ), (string) $lang, (int) $original->ID );
		if ( ! $state ) {
			return $content;
		}

		$stored  = (string) $translation->post_content;
		$overlay = $this->guard->overlays() ? $this->overlay( $state, $translation, $original, $content, (string) $lang ) : null;
		if ( null === $overlay || $overlay === $stored ) {
			return $stored;
		}

		$this->overlaid[ $translation->ID ] = [ (string) $lang, $original ];

		return $overlay;
	}

	public function recordOverlay( $postId, $post ) {
		$postId = (int) $postId;
		if ( ! isset( $this->overlaid[ $postId ] ) || ! $post instanceof \WP_Post ) {
			return;
		}

		[ $language, $original ] = $this->overlaid[ $postId ];
		unset( $this->overlaid[ $postId ] );

		$this->recorder->recordTranslation(
			Subject::post( $postId ),
			$language,
			(int) $original->ID,
			$this->units->read( $post, $original ),
			null,
			fn() => $this->units->read( $original, $original ),
			InternalWrite::AUTO_RESAVE,
			null,
			null,
			InternalWrite::AUTO_RESAVE
		);
	}

	private function overlay( State $state, \WP_Post $translation, \WP_Post $original, string $rebuilt, string $lang ) {
		if ( $this->normaliser->translationHash( $this->units->read( $translation, $original ) ) !== $state->headHash() ) {
			return null;
		}

		$deleted = [];
		foreach ( $state->deletedElements() as $element ) {
			if ( ! isset( $element['uid'] ) ) {
				return null;
			}
			$deleted[] = (string) $element['uid'];
		}

		$subject  = Subject::post( $translation->ID );
		$created  = (array) apply_filters( 'wpml_pb_block_uid_timestamps', [], $original->ID );
		$headTime = false;
		$isNew    = function ( string $uid ) use ( $created, $subject, $lang, $state, &$headTime ) {
			if ( false === $headTime ) {
				$headTime = $this->guard->headTime( $subject, $lang, $state );
			}

			return null === $headTime || ! isset( $created[ $uid ]['created'] ) ? null : (int) $created[ $uid ]['created'] > $headTime;
		};

		$ours   = self::roots( (string) $translation->post_content, $translation->ID );
		$blocks = RebuildOverlay::merge( $ours, self::roots( (string) $original->post_content, $original->ID ), self::roots( $rebuilt, null ), $isNew, $deleted );

		return null === $blocks || count( $blocks ) === count( $ours ) ? null : implode( "\n\n", array_map( 'serialize_block', $blocks ) );
	}

	private static function roots( string $content, $postId ): array {
		$uids  = null === $postId ? [] : (array) apply_filters( 'wpml_pb_block_uids', [], $postId, $content );
		$roots = [];
		foreach ( \WPML_Gutenberg_Integration::parse_blocks( $content ) as $position => $block ) {
			$block = (array) $block;
			$name  = (string) ( $block['blockName'] ?? '' );
			if ( '' === $name && '' === trim( (string) ( $block['innerHTML'] ?? '' ) ) && empty( $block['innerBlocks'] ) ) {
				continue;
			}

			$roots[] = [
				'name'  => $name,
				'uid'   => (string) ( $uids[ $position ] ?? '' ),
				'block' => $block,
			];
		}

		return $roots;
	}
}
