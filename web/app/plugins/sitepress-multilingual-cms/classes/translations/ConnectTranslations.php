<?php

namespace WPML\Translation;

use WPML\Request\Payload;

class ConnectTranslations {

	const CONFLICT        = 'wpml_connect_translations_conflict';
	const CONFLICT_STATUS = 409;

	private $wpdb;

	private $sitepress;

	public function __construct( \wpdb $wpdb, \SitePress $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function load( $postId, $postType, $newTrid ) {
		$postId      = (int) $postId;
		$newTrid     = (int) $newTrid;
		$elementType = 'post_' . $postType;

		$wpdb = $this->wpdb;
		$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translation_id, trid, language_code, source_language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = %s LIMIT 1",
				[ $postId, $elementType ]
			)
		);

		$ownGroupSize = $post
			? (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND element_type = %s",
					[ (int) $post->trid, $elementType ]
				)
			)
			: 0;

		$target = $newTrid > 0
			? $wpdb->get_results(
				$wpdb->prepare(
					"SELECT translation_id, element_id, element_type, language_code, source_language_code FROM {$wpdb->prefix}icl_translations WHERE trid = %d",
					[ $newTrid ]
				)
			)
			: [];
		$target = is_array( $target ) ? $target : [];

		$targetOriginal = null;
		foreach ( $target as $row ) {
			if ( null === $row->source_language_code ) {
				$targetOriginal = $row;
				break;
			}
		}

		return [
			'postId'         => $postId,
			'postType'       => (string) $postType,
			'realPostType'   => get_post_type( $postId ),
			'elementType'    => $elementType,
			'newTrid'        => $newTrid,
			'post'           => $post,
			'ownGroupSize'   => $ownGroupSize,
			'target'         => $target,
			'targetOriginal' => $targetOriginal,
		];
	}

	public function refuseTarget( array $state ) {
		if ( $state['realPostType'] !== $state['postType'] ) {
			return Payload::invalid( 'post_type', 'mismatch' );
		}

		if ( ! $state['post'] ) {
			return Payload::invalid( 'post_id', 'no_language' );
		}

		if ( $state['newTrid'] <= 0 || ! $state['target'] ) {
			return Payload::invalid( 'new_trid', 'not_found' );
		}

		foreach ( $state['target'] as $row ) {
			if ( $row->element_type !== $state['elementType'] ) {
				return Payload::invalid( 'new_trid', 'wrong_type' );
			}
		}

		return null;
	}

	public function targetOriginalId( array $state ) {
		return $state['targetOriginal'] ? (int) $state['targetOriginal']->element_id : 0;
	}

	public function targetElementIds( array $state ) {
		$ids = [];
		foreach ( $state['target'] as $row ) {
			if ( null !== $row->element_id ) {
				$ids[] = (int) $row->element_id;
			}
		}

		return $ids;
	}

	public function refuseMove( array $state, $setAsSource ) {
		$post = $state['post'];

		if ( (int) $post->trid === $state['newTrid'] ) {
			return Payload::invalid( 'new_trid', 'same_group' );
		}

		if ( $state['ownGroupSize'] > 1 ) {
			return Payload::invalid( 'post_id', 'not_alone' );
		}

		if ( ! $setAsSource && ! $state['targetOriginal'] ) {
			return Payload::invalid( 'new_trid', 'no_original' );
		}

		foreach ( $state['target'] as $row ) {
			if ( $row->language_code === $post->language_code ) {
				return Payload::invalid( 'new_trid', 'language_taken', $this->languageTakenMessage( $post->language_code ) );
			}
		}

		return null;
	}

	public function apply( array $state, $setAsSource ) {
		$wpdb          = $this->wpdb;
		$table         = $wpdb->prefix . 'icl_translations';
		$post          = $state['post'];
		$translationId = (int) $post->translation_id;

		$moved = $this->wpdb->update(
			$table,
			[
				'trid'                 => $state['newTrid'],
				'source_language_code' => $setAsSource ? null : $state['targetOriginal']->language_code,
			],
			[ 'translation_id' => $translationId ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		$trid = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT trid FROM {$wpdb->prefix}icl_translations WHERE translation_id = %d",
				[ $translationId ]
			)
		);
		if ( false === $moved || $trid !== $state['newTrid'] ) {
			return $this->conflict();
		}

		if ( ! $setAsSource ) {
			$this->releaseStatusOfMovedRow( $post, $state['newTrid'] );

			return null;
		}

		$stamped = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translations SET source_language_code = %s WHERE trid = %d AND element_type = %s AND translation_id <> %d",
				[ $post->language_code, $state['newTrid'], $state['elementType'], $translationId ]
			)
		);

		if ( false === $stamped ) {
			$this->wpdb->update(
				$table,
				[
					'trid'                 => (int) $post->trid,
					'source_language_code' => $post->source_language_code,
				],
				[ 'translation_id' => $translationId ],
				[ '%d', '%s' ],
				[ '%d' ]
			);

			return $this->conflict();
		}

		$this->releaseStatusOfMovedRow( $post, $state['newTrid'] );

		return null;
	}

	private function releaseStatusOfMovedRow( $post, $newTrid ) {
		if ( (int) $post->trid !== (int) $newTrid ) {
			\WPML_Translation_Records_Delete::status_by_translation_ids( [ (int) $post->translation_id ] );
		}
	}

	private function languageTakenMessage( $languageCode ) {
		$languageName = $this->sitepress->get_display_language_name( $languageCode );

		return sprintf(
			/* translators: Shown in the "Connect with translations" dialog on the post editing screen when the chosen translation group already has a post in the language of this post, so nothing was changed. %s: the name of a language, for example "English". */
			__( 'This post was not connected. The group you chose already has a post in %s. Choose another post.', 'sitepress' ),
			$languageName ? $languageName : $languageCode
		);
	}

	private function conflict() {
		return new \WP_Error(
			self::CONFLICT,
			/* translators: Shown in the "Connect with translations" dialog on the post editing screen when the translation group changed while the user was choosing, so the post was not connected. */
			__( 'This post was not connected, because the group changed while you were choosing. Reload the page and try again.', 'sitepress' ),
			[
				'status' => self::CONFLICT_STATUS,
				'params' => [ 'new_trid' => 'write_failed' ],
			]
		);
	}
}
