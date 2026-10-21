<?php

namespace WPML\Comments\Counts;

use WPML\QueryFiltering\CommentLanguageMismatchCheck;

class Recount {

	const LOCK_OPTION = 'wpml_comment_counts_lock';

	const LOCK_TIMEOUT = 600;

	private $wpdb;

	private $exclusions;

	private $store;

	private $scheduler;

	private $check;

	public function __construct(
		\wpdb $wpdb,
		?Exclusions $exclusions = null,
		?Store $store = null,
		?Scheduler $scheduler = null,
		?CommentLanguageMismatchCheck $check = null
	) {
		$this->wpdb       = $wpdb;
		$this->exclusions = $exclusions;
		$this->store      = $store;
		$this->scheduler  = $scheduler;
		$this->check      = $check;
	}

	public function runFromCron() {
		if ( ! FeatureSwitch::isOn() || ! $this->isSetupComplete() ) {
			return;
		}

		$this->run();
	}

	public function run() {
		$lock = time();

		if ( ! $this->takeLock( $lock ) ) {
			$this->scheduler()->scheduleAfterInterval();

			return false;
		}

		try {
			$counts_done = 0;

			do {
				++$counts_done;

				$before          = $this->store()->readFresh();
				$generation_read = null === $before['store'] ? null : $before['store']['generation'];

				$counts = $this->compute( $this->check()->siteHasCommentLanguageRows() );

				if ( null === $counts ) {
					$this->store()->writeErrorState();
					$this->scheduler()->scheduleAfterInterval();

					return false;
				}

				$written = $this->store()->writeRecountResult( $counts, $generation_read );

				if ( null === $written ) {
					$this->scheduler()->scheduleAfterInterval();

					return false;
				}
			} while ( 1 === $counts_done && null === $generation_read );

			if ( $written['out_of_date'] > 0 ) {
				$this->scheduler()->ensureScheduled( $written );
			}

			return true;
		} finally {
			$this->releaseLock( $lock );
		}
	}

	public function compute( $with_mismatch_step ) {
		$comment_types = $this->exclusions()->hiddenCommentTypes();
		$post_types    = $this->exclusions()->hiddenPostTypes();

		$rows = $this->countPerPost( $comment_types, $post_types );
		if ( $this->failed( $rows ) ) {
			return null;
		}

		$counts = $this->addRows( array(), $rows, 1 );

		if ( $with_mismatch_step ) {
			$rows = $this->countMismatched( $comment_types, $post_types );
			if ( $this->failed( $rows ) ) {
				return null;
			}

			$counts = $this->addRows( $counts, $rows, -1 );
		}

		foreach ( $counts as $language => $buckets ) {
			foreach ( $buckets as $bucket => $n ) {
				$counts[ $language ][ $bucket ] = max( 0, $n );
			}
		}
		ksort( $counts );

		return $counts;
	}

	private function countPerPost( array $comment_types, array $post_types ) {
		$wpdb = $this->wpdb;

		if ( $post_types ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT t.language_code AS lang, x.comment_approved AS status, SUM(x.n) AS n
					FROM (SELECT comment_post_ID, comment_approved, COUNT(*) AS n FROM {$wpdb->comments}
						WHERE comment_type NOT IN (" . implode( ',', array_fill( 0, count( $comment_types ), '%s' ) ) . ")
						GROUP BY comment_post_ID, comment_approved) x
					JOIN {$wpdb->posts} p ON p.ID = x.comment_post_ID
						AND p.post_type NOT IN (" . implode( ',', array_fill( 0, count( $post_types ), '%s' ) ) . ")
					JOIN {$wpdb->prefix}icl_translations t ON t.element_id = x.comment_post_ID
						AND t.element_type = CONCAT('post_', p.post_type)
					GROUP BY t.language_code, x.comment_approved",
					...array_merge( $comment_types, $post_types )
				),
				ARRAY_A
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.language_code AS lang, x.comment_approved AS status, SUM(x.n) AS n
				FROM (SELECT comment_post_ID, comment_approved, COUNT(*) AS n FROM {$wpdb->comments}
					WHERE comment_type NOT IN (" . implode( ',', array_fill( 0, count( $comment_types ), '%s' ) ) . ")
					GROUP BY comment_post_ID, comment_approved) x
				JOIN {$wpdb->posts} p ON p.ID = x.comment_post_ID
				JOIN {$wpdb->prefix}icl_translations t ON t.element_id = x.comment_post_ID
					AND t.element_type = CONCAT('post_', p.post_type)
				GROUP BY t.language_code, x.comment_approved",
				...$comment_types
			),
			ARRAY_A
		);
	}

	private function countMismatched( array $comment_types, array $post_types ) {
		$wpdb = $this->wpdb;

		if ( $post_types ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pt.language_code AS lang, c.comment_approved AS status, COUNT(*) AS n
					FROM {$wpdb->prefix}icl_translations ct
					JOIN {$wpdb->comments} c ON c.comment_ID = ct.element_id
					JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
						AND p.post_type NOT IN (" . implode( ',', array_fill( 0, count( $post_types ), '%s' ) ) . ")
					JOIN {$wpdb->prefix}icl_translations pt ON pt.element_id = c.comment_post_ID
						AND pt.element_type = CONCAT('post_', p.post_type)
					WHERE ct.element_type = 'comment' AND ct.language_code <> pt.language_code
						AND c.comment_type NOT IN (" . implode( ',', array_fill( 0, count( $comment_types ), '%s' ) ) . ')
					GROUP BY pt.language_code, c.comment_approved',
					...array_merge( $post_types, $comment_types )
				),
				ARRAY_A
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pt.language_code AS lang, c.comment_approved AS status, COUNT(*) AS n
				FROM {$wpdb->prefix}icl_translations ct
				JOIN {$wpdb->comments} c ON c.comment_ID = ct.element_id
				JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
				JOIN {$wpdb->prefix}icl_translations pt ON pt.element_id = c.comment_post_ID
					AND pt.element_type = CONCAT('post_', p.post_type)
				WHERE ct.element_type = 'comment' AND ct.language_code <> pt.language_code
					AND c.comment_type NOT IN (" . implode( ',', array_fill( 0, count( $comment_types ), '%s' ) ) . ')
				GROUP BY pt.language_code, c.comment_approved',
				...$comment_types
			),
			ARRAY_A
		);
	}

	private function addRows( array $counts, array $rows, $sign ) {
		foreach ( $rows as $row ) {
			$bucket = Exclusions::bucketForCommentApproved( $row['status'] );
			if ( null === $bucket ) {
				continue;
			}

			$language = (string) $row['lang'];
			if ( ! isset( $counts[ $language ] ) ) {
				$counts[ $language ] = Exclusions::emptyBuckets();
			}

			$counts[ $language ][ $bucket ] += $sign * (int) $row['n'];
		}

		return $counts;
	}

	private function failed( $rows ) {
		return ! is_array( $rows ) || '' !== (string) $this->wpdb->last_error;
	}

	private function takeLock( $now ) {
		$wpdb = $this->wpdb;

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
				self::LOCK_OPTION,
				(string) $now
			)
		);
		if ( 1 === $inserted ) {
			return true;
		}

		$held = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::LOCK_OPTION
			)
		);
		if ( null === $held || (int) $held > $now - self::LOCK_TIMEOUT ) {
			return false;
		}

		$taken = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				(string) $now,
				self::LOCK_OPTION,
				(string) $held
			)
		);

		return 1 === $taken;
	}

	private function releaseLock( $taken_at ) {
		$wpdb = $this->wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				self::LOCK_OPTION,
				(string) $taken_at
			)
		);
		wp_cache_delete( self::LOCK_OPTION, 'options' );
	}

	protected function isSetupComplete() {
		global $sitepress;

		return (bool) ( $sitepress
			&& method_exists( $sitepress, 'is_setup_complete' )
			&& $sitepress->is_setup_complete() );
	}

	private function exclusions() {
		if ( ! $this->exclusions ) {
			$this->exclusions = new Exclusions();
		}

		return $this->exclusions;
	}

	private function scheduler() {
		if ( ! $this->scheduler ) {
			$this->scheduler = new Scheduler();
		}

		return $this->scheduler;
	}

	private function store() {
		if ( ! $this->store ) {
			$this->store = new Store( $this->wpdb, $this->scheduler() );
		}

		return $this->store;
	}

	private function check() {
		if ( ! $this->check ) {
			$this->check = new CommentLanguageMismatchCheck( $this->wpdb );
		}

		return $this->check;
	}
}
