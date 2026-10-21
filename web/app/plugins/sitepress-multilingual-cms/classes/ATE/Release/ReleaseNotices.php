<?php

namespace WPML\TM\ATE\Release;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseSummary;
use function WPML\Container\make;

class ReleaseNotices {

	const KIND_UPDATE = 'update';

	const KIND_TRASH = 'trash';

	const KIND_MANUAL_SAVE = 'manual-save';

	const TTL = 300;

	const LEDGER_BASE_URL = 'https://app.wpml.org/account/words';


	public static function ledgerUrl() {
		return (string) apply_filters( 'wpml_release_ledger_url', self::buildLedgerUrl() );
	}


	public static function fillLedgerUrl( $url ) {
		return is_string( $url ) && '' !== $url ? $url : self::buildLedgerUrl();
	}


	private static function buildLedgerUrl() {
		$args = [ 'tab' => 'ledger' ];
		$uuid = self::siteUuid();

		if ( '' !== $uuid ) {
			$args['site_uuid'] = $uuid;
		}

		$url = (string) add_query_arg( $args, self::LEDGER_BASE_URL );

		if ( class_exists( '\WPML\OutboundLinks\OutboundLinks' ) ) {
			return \WPML\OutboundLinks\OutboundLinks::to(
				$url,
				[
					'medium'   => 'notice',
					'campaign' => 'automatic-translation',
					'content'  => 'word-ledger',
				]
			);
		}

		return $url;
	}


	private static function siteUuid() {
		$uuid = make( \WPML_TM_ATE_Authentication::class )->get_site_id();

		return is_string( $uuid ) ? $uuid : '';
	}


	public static function ledgerLink() {
		return '<a href="' . esc_url( self::ledgerUrl() ) . '">'
			/* translators: Link text inside a sentence about automatic translation; it opens the page that lists how many words were used. It starts in lower case because it sits inside the sentence. */
			. esc_html__( 'usage ledger', 'sitepress' )
			. '</a>';
	}


	public static function updateNoticeText() {
		return __(
			'Your update replaced a translation in progress. You are not charged for the replaced one; the new translation is on its way.',
			'sitepress'
		);
	}


	public static function manualSaveNoticeText() {
		/* translators: Notice in the post editor after a translation was saved by hand while WPML's automatic translation of it was still running; that automatic translation was stopped. */
		return __(
			'Your translation replaced a translation in progress. The translation in progress was cancelled. You are not charged for it.',
			'sitepress'
		);
	}


	public static function consumeUpdatePayload( $postId ) {
		$postId = (int) $postId;

		foreach ( [ self::KIND_UPDATE, self::KIND_MANUAL_SAVE ] as $kind ) {
			$entries = self::consume( $kind, $postId );

			if ( empty( $entries[ $postId ] ) ) {
				continue;
			}

			return [
				'id'         => self::noticeId( $kind, $postId, $entries[ $postId ] ),
				'text'       => self::KIND_MANUAL_SAVE === $kind ? self::manualSaveNoticeText() : self::updateNoticeText(),
				'ledger_url' => self::ledgerUrl(),
			];
		}

		return null;
	}


	private static function noticeId( $kind, $postId, array $entry ) {
		$token = isset( $entry['token'] ) ? (string) $entry['token'] : '0';

		return 'wpml-release-' . $kind . '-' . $postId . '-' . $token;
	}


	public static function queueUpdateNotice( $postId, ReleaseSummary $summary ) {
		if ( $summary->isEmpty() ) {
			return;
		}

		self::queue( self::KIND_UPDATE, (int) $postId, $summary->getJobsForCopy(), $summary->getReleasedWords() );
	}


	public static function queueManualSaveNotice( $translationPostId ) {
		if ( (int) $translationPostId <= 0 ) {
			return;
		}

		self::queue( self::KIND_MANUAL_SAVE, (int) $translationPostId, 1, 0 );
	}


	public static function queueTrashNotice( $postId, $jobs ) {
		if ( (int) $jobs <= 0 ) {
			return;
		}

		self::queue( self::KIND_TRASH, (int) $postId, (int) $jobs, 0 );
	}


	public static function consume( $kind, $postId = null ) {
		$all = self::read();

		if ( empty( $all[ $kind ] ) ) {
			return [];
		}

		if ( null === $postId ) {
			$taken          = $all[ $kind ];
			$all[ $kind ]   = [];
			self::write( $all );

			return $taken;
		}

		$postId = (int) $postId;

		if ( ! isset( $all[ $kind ][ $postId ] ) ) {
			return [];
		}

		$taken = [ $postId => $all[ $kind ][ $postId ] ];
		unset( $all[ $kind ][ $postId ] );
		self::write( $all );

		return $taken;
	}


	private static function queue( $kind, $postId, $jobs, $words ) {
		$all = self::read();

		if ( ! isset( $all[ $kind ] ) ) {
			$all[ $kind ] = [];
		}

		$existing = isset( $all[ $kind ][ $postId ] )
			? $all[ $kind ][ $postId ]
			: [ 'jobs' => 0, 'words' => 0 ];

		$all[ $kind ][ $postId ] = [
			'jobs'  => (int) $existing['jobs'] + (int) $jobs,
			'words' => (int) $existing['words'] + (int) $words,
			'token' => isset( $existing['token'] ) ? $existing['token'] : uniqid(),
		];

		self::write( $all );
	}


	private static function read() {
		$stored = get_transient( self::transientKey() );

		return is_array( $stored ) ? $stored : [];
	}


	private static function write( array $all ) {
		$empty = true;
		foreach ( $all as $entries ) {
			if ( ! empty( $entries ) ) {
				$empty = false;
				break;
			}
		}

		if ( $empty ) {
			delete_transient( self::transientKey() );

			return;
		}

		set_transient( self::transientKey(), $all, self::TTL );
	}


	private static function transientKey() {
		return 'wpml_release_notices_' . get_current_user_id();
	}

}
