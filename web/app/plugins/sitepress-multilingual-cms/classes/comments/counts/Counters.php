<?php

namespace WPML\Comments\Counts;

class Counters {

	const TRANSITION_BUCKETS = array(
		'approved'     => 'approved',
		'unapproved'   => 'moderated',
		'spam'         => 'spam',
		'trash'        => 'trash',
		'post-trashed' => 'post-trashed',
	);

	const DEFERRED_CAP = 50000;

	const LOOKUP_CHUNK = 500;

	const SAFETY_DELAY = 600;

	const CODE_BUCKETS = array( 'approved', 'moderated', 'spam', 'trash', 'post-trashed' );

	const NO_BUCKET = 7;

	private static $deferred = array();

	private static $kept = 0;

	private static $overCap = false;

	private $scheduler;

	private $wpdb;

	private $store;

	private $exclusions;

	public function __construct( \wpdb $wpdb, Store $store, ?Exclusions $exclusions = null ) {
		$this->wpdb       = $wpdb;
		$this->store      = $store;
		$this->exclusions = $exclusions;
	}

	public static function resetRequestCache() {
		self::$deferred = array();
		self::$kept     = 0;
		self::$overCap  = false;
	}

	public function commentInserted( $comment_id, $comment = null ) {
		if ( ! is_object( $comment ) ) {
			return;
		}

		$bucket = Exclusions::bucketForCommentApproved( $comment->comment_approved );
		if ( null === $bucket ) {
			return;
		}

		$this->apply( $comment, array( $bucket => 1 ) );
	}

	public function statusChanged( $new_status, $old_status, $comment = null ) {
		if ( ! is_object( $comment ) ) {
			return;
		}

		$deltas = array();

		$old = self::bucketForTransitionStatus( $old_status );
		if ( null !== $old ) {
			$deltas[ $old ] = -1;
		}

		$new = self::bucketForTransitionStatus( $new_status );
		if ( null !== $new ) {
			$deltas[ $new ] = ( isset( $deltas[ $new ] ) ? $deltas[ $new ] : 0 ) + 1;
		}

		$deltas = array_filter( $deltas );
		if ( $deltas ) {
			$this->apply( $comment, $deltas );
		}
	}

	public static function bucketForTransitionStatus( $status ) {
		return is_string( $status ) && isset( self::TRANSITION_BUCKETS[ $status ] ) ? self::TRANSITION_BUCKETS[ $status ] : null;
	}

	private function apply( $comment, array $deltas ) {
		if ( in_array( (string) $comment->comment_type, $this->exclusions()->hiddenCommentTypes(), true ) ) {
			return;
		}

		$post_id = (int) $comment->comment_post_ID;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( $this->countingIsDeferred() ) {
			$this->keepDeferred( (int) $comment->comment_ID, $post_id, $deltas );

			return;
		}

		$this->applyDeferred();

		if ( null === $this->store->getForWrite() ) {
			return;
		}

		$row      = $this->lookup( $post_id, (int) $comment->comment_ID );
		$language = $row ? $this->countingLanguage( $row, $row['comment_language'] ) : null;
		if ( null === $language ) {
			return;
		}

		$this->store->applyDeltas( $language, $deltas );
	}

	private function countingIsDeferred() {
		return (bool) wp_defer_comment_counting();
	}

	public function deferralEnded() {
		if ( ! self::$deferred || $this->countingIsDeferred() ) {
			return;
		}

		$this->applyDeferred();
	}

	public function applyBeforeUse( $value ) {
		$this->deferralEnded();

		return $value;
	}

	public function hasPendingChanges() {
		$blog_id = get_current_blog_id();

		return isset( self::$deferred[ $blog_id ] ) && ! empty( self::$deferred[ $blog_id ]['comments'] );
	}

	public function applyDeferred() {
		if ( ! self::$deferred ) {
			return;
		}

		foreach ( array_keys( self::$deferred ) as $blog_id ) {
			$switch = (int) $blog_id !== get_current_blog_id();
			if ( $switch ) {
				switch_to_blog( (int) $blog_id );
			}

			$this->applyDeferredOfThisSite();

			if ( $switch ) {
				restore_current_blog();
			}
		}

		if ( ! self::$deferred ) {
			self::$kept    = 0;
			self::$overCap = false;
		}
	}

	private function keepDeferred( $comment_id, $post_id, array $deltas ) {
		$blog_id = get_current_blog_id();

		if (
			isset( self::$deferred[ $blog_id ] )
			&& ! self::$deferred[ $blog_id ]['marking']
			&& time() - self::$deferred[ $blog_id ]['since'] >= Scheduler::MIN_INTERVAL
		) {
			$this->applyDeferredOfThisSite();
		}

		if ( ! isset( self::$deferred[ $blog_id ] ) ) {
			self::$deferred[ $blog_id ] = $this->newBatch();
		}

		if ( ! self::$deferred[ $blog_id ]['marking'] && self::$kept >= $this->deferredCap() ) {
			$this->dropBatchesForMarks();
		}

		if ( self::$deferred[ $blog_id ]['marking'] ) {
			$this->store->markDeferred();

			return;
		}

		self::$deferred[ $blog_id ]['comments'][] = $comment_id;
		self::$deferred[ $blog_id ]['posts'][]    = $post_id;
		self::$deferred[ $blog_id ]['codes'][]    = self::codeOf( $deltas );
		++self::$kept;
	}

	protected function deferredCap() {
		return self::DEFERRED_CAP;
	}

	private function newBatch() {
		$batch = array(
			'generation' => null,
			'since'      => time(),
			'comments'   => array(),
			'posts'      => array(),
			'codes'      => array(),
			'marking'    => self::$overCap,
			'safety'     => null,
		);

		if ( ! $batch['marking'] ) {
			$store = $this->store->getForWrite();
			if ( null !== $store ) {
				$batch['generation'] = $store['generation'];
				$batch['safety']     = $this->scheduler()->scheduleSafetyRecount( self::SAFETY_DELAY );
			}
		}

		return $batch;
	}

	private function dropBatchesForMarks() {
		self::$overCap = true;

		foreach ( array_keys( self::$deferred ) as $blog_id ) {
			if ( self::$deferred[ $blog_id ]['marking'] ) {
				continue;
			}

			$switch = (int) $blog_id !== get_current_blog_id();
			if ( $switch ) {
				switch_to_blog( (int) $blog_id );
			}

			$batch       = self::$deferred[ $blog_id ];
			self::$kept -= count( $batch['comments'] );
			if ( $batch['safety'] ) {
				$this->scheduler()->unscheduleSafetyRecount( $batch['safety'] );
			}
			self::$deferred[ $blog_id ] = array_merge(
				$batch,
				array(
					'comments' => array(),
					'posts'    => array(),
					'codes'    => array(),
					'marking'  => true,
					'safety'   => null,
				)
			);
			$this->store->markDeferred();

			if ( $switch ) {
				restore_current_blog();
			}
		}
	}

	private function applyDeferredOfThisSite() {
		$blog_id = get_current_blog_id();
		if ( ! isset( self::$deferred[ $blog_id ] ) ) {
			return;
		}
		$batch = self::$deferred[ $blog_id ];
		unset( self::$deferred[ $blog_id ] );
		self::$kept -= count( $batch['comments'] );

		$this->applyBatch( $batch );

		if ( $batch['safety'] ) {
			$this->scheduler()->unscheduleSafetyRecount( $batch['safety'] );
		}
	}

	private function applyBatch( array $batch ) {
		if ( $batch['marking'] || ! $batch['comments'] ) {
			return;
		}

		if ( null === $batch['generation'] ) {
			if ( null !== $this->store->getForWrite() ) {
				$this->store->markOutOfDate();
			}

			return;
		}

		$store = $this->store->get();
		if ( null === $store ) {
			return;
		}
		if ( $store['generation'] !== $batch['generation'] ) {
			$this->store->markOutOfDate();

			return;
		}

		$found = array(
			'post'    => array(),
			'comment' => array(),
		);
		$total = count( $batch['comments'] );
		for ( $offset = 0; $offset < $total; $offset += self::LOOKUP_CHUNK ) {
			$chunk = $this->applyDeferredLookup(
				array_slice( $batch['posts'], $offset, self::LOOKUP_CHUNK ),
				array_slice( $batch['comments'], $offset, self::LOOKUP_CHUNK )
			);
			$found['post']    = $chunk['post'] + $found['post'];
			$found['comment'] = $chunk['comment'] + $found['comment'];
		}

		$changes = array();
		$gone    = false;
		foreach ( $batch['comments'] as $i => $comment_id ) {
			$post_id = $batch['posts'][ $i ];

			if ( ! isset( $found['post'][ $post_id ] ) ) {
				$gone = true;
				continue;
			}

			$language = $this->countingLanguage(
				$found['post'][ $post_id ],
				isset( $found['comment'][ $comment_id ] ) ? $found['comment'][ $comment_id ] : null
			);
			if ( null !== $language ) {
				$changes[] = array( $language, self::deltasOf( $batch['codes'][ $i ] ) );
			}
		}

		if ( $changes ) {
			$this->store->applyDeltaList( $changes, $gone );
		} elseif ( $gone ) {
			$this->store->markOutOfDate();
		}
	}

	private function applyDeferredLookup( array $post_ids, array $comment_ids ) {
		$post_ids    = array_values( array_unique( $post_ids ) );
		$comment_ids = array_values( array_unique( $comment_ids ) );
		$wpdb        = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 'post' AS kind, p.ID AS id, p.post_type AS post_type, pl.language_code AS language
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}icl_translations pl ON pl.element_id = p.ID
					AND pl.element_type = CONCAT('post_', p.post_type)
				WHERE p.ID IN (" . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ")
				UNION ALL
				SELECT 'comment' AS kind, cl.element_id AS id, '' AS post_type, cl.language_code AS language
				FROM {$wpdb->prefix}icl_translations cl
				WHERE cl.element_type = 'comment'
					AND cl.element_id IN (" . implode( ',', array_fill( 0, count( $comment_ids ), '%d' ) ) . ')',
				...array_merge( $post_ids, $comment_ids )
			),
			ARRAY_A
		);

		$found = array(
			'post'    => array(),
			'comment' => array(),
		);
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( 'post' === $row['kind'] ) {
				$found['post'][ (int) $row['id'] ] = array(
					'post_type'     => (string) $row['post_type'],
					'post_language' => $row['language'],
				);
			} else {
				$found['comment'][ (int) $row['id'] ] = (string) $row['language'];
			}
		}

		return $found;
	}

	private static function codeOf( array $deltas ) {
		$old = self::NO_BUCKET;
		$new = self::NO_BUCKET;
		foreach ( $deltas as $bucket => $delta ) {
			$index = array_search( $bucket, self::CODE_BUCKETS, true );
			if ( false === $index ) {
				continue;
			}
			if ( $delta < 0 ) {
				$old = $index;
			} else {
				$new = $index;
			}
		}

		return $old * 8 + $new;
	}

	private static function deltasOf( $code ) {
		$deltas = array();
		$old    = intdiv( $code, 8 );
		$new    = $code % 8;
		if ( self::NO_BUCKET !== $old ) {
			$deltas[ self::CODE_BUCKETS[ $old ] ] = -1;
		}
		if ( self::NO_BUCKET !== $new ) {
			$deltas[ self::CODE_BUCKETS[ $new ] ] = 1;
		}

		return $deltas;
	}

	private function scheduler() {
		if ( ! $this->scheduler ) {
			$this->scheduler = new Scheduler();
		}

		return $this->scheduler;
	}

	private function countingLanguage( array $post, $comment_language ) {
		if (
			null === $post['post_language']
			|| in_array( (string) $post['post_type'], $this->exclusions()->hiddenPostTypes(), true )
		) {
			return null;
		}

		if ( null !== $comment_language && (string) $comment_language !== (string) $post['post_language'] ) {
			return null;
		}

		return (string) $post['post_language'];
	}

	private function lookup( $post_id, $comment_id ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.post_type AS post_type, pl.language_code AS post_language, cl.language_code AS comment_language
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}icl_translations pl ON pl.element_id = p.ID
					AND pl.element_type = CONCAT('post_', p.post_type)
				LEFT JOIN {$wpdb->prefix}icl_translations cl ON cl.element_id = %d
					AND cl.element_type = 'comment'
				WHERE p.ID = %d
				LIMIT 1",
				$comment_id,
				$post_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	private function exclusions() {
		if ( ! $this->exclusions ) {
			$this->exclusions = new Exclusions();
		}

		return $this->exclusions;
	}
}
