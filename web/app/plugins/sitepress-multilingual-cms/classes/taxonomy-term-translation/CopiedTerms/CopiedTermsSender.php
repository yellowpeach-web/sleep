<?php

namespace WPML\TaxonomyTermTranslation\CopiedTerms;

use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomyTermBundleRounds;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomyTermPairClaimRepository;
use WPML\TaxonomyTermTranslation\CopiedTermMarker;
use WPML\TM\ATE\TranslateEverything\TaxonomyTermJobDispatcher;

class CopiedTermsSender {

	const BATCH_SIZE = 50;

	private $wpdb;

	private $dispatcher;

	public function __construct( $wpdb, $dispatcher = null ) {
		$this->wpdb       = $wpdb;
		$this->dispatcher = $dispatcher ?: new TaxonomyTermJobDispatcher( $wpdb );
	}

	public function send( array $copies ) {
		$result = [
			'sent'    => [],
			'waiting' => [],
			'failed'  => [],
		];

		$byPair = [];
		foreach ( $copies as $copy ) {
			if ( class_exists( CopiedTermMarker::class ) ) {
				CopiedTermMarker::mark( (int) $copy['copyTtId'], (string) $copy['taxonomy'] );
			}
			$byPair[ (int) $copy['originalTtId'] . '|' . (string) $copy['language'] ] = $copy;
		}
		if ( ! $byPair ) {
			return $result;
		}

		$pairs = [];
		foreach ( $byPair as $copy ) {
			$pairs[] = [ (int) $copy['originalTtId'], (string) $copy['language'] ];
		}
		$owner   = 'copied-terms-' . wp_generate_uuid4();
		$claims  = new TaxonomyTermPairClaimRepository( $this->wpdb );
		$claimed = $claims->claimMany( $pairs, $owner );

		$members = [];
		try {
			foreach ( $byPair as $key => $copy ) {
				if ( ! isset( $claimed[ $key ] ) ) {
					$result['failed'][] = self::failed( $copy, __( 'This term is being sent for translation by another task right now. Run the tool again in a few minutes.', 'sitepress' ) );
					continue;
				}

				$member = $this->dispatcher->prepareTermBundleMember(
					(int) $copy['originalTtId'],
					(string) $copy['taxonomy'],
					(string) $copy['originalLanguage'],
					(string) $copy['language'],
					true
				);
				if ( $member ) {
					$members[] = $member;
				} else {
					$result['failed'][] = self::failed( $copy, self::refusalReason( (string) $this->dispatcher->getLastRefusal() ) );
				}
			}

			if ( $members ) {
				$dispatched = ( new TaxonomyTermBundleRounds( $this->wpdb, $this->dispatcher ) )->dispatch( $members, false );
				foreach ( $dispatched['accepted'] as $member ) {
					$taxonomy                    = $member->getTaxonomy();
					$result['sent'][ $taxonomy ] = ( $result['sent'][ $taxonomy ] ?? 0 ) + 1;
				}
				foreach ( $dispatched['failed'] as $failure ) {
					$member = $failure['member'];
					$copy   = $byPair[ $member->getTermTaxonomyId() . '|' . $member->getTargetLanguage() ] ?? null;
					if ( ! $copy ) {
						continue;
					}
					if ( 'blocked' === $failure['kind'] ) {
						$taxonomy                       = (string) $copy['taxonomy'];
						$result['waiting'][ $taxonomy ] = ( $result['waiting'][ $taxonomy ] ?? 0 ) + 1;
						continue;
					}
					$result['failed'][] = self::failed( $copy, (string) $failure['reason'] );
				}
			}
		} finally {
			$claims->releaseMany( $pairs, $owner );
		}

		return $result;
	}

	private static function failed( array $copy, $reason ) {
		return [
			'copyTtId' => (int) $copy['copyTtId'],
			'taxonomy' => (string) $copy['taxonomy'],
			'language' => (string) $copy['language'],
			'name'     => (string) $copy['name'],
			'reason'   => $reason,
		];
	}

	private static function refusalReason( $refusal ) {
		switch ( $refusal ) {
			case 'being_translated':
				return __( 'This term is already being translated.', 'sitepress' );
			case 'review_open':
				return __( 'A translation of this term is open for review.', 'sitepress' );
			case 'unsupported_pair':
				return __( 'Automatic translation does not support this language pair.', 'sitepress' );
			default:
				return __( 'The term could not be prepared for translation. Run the tool again.', 'sitepress' );
		}
	}
}
