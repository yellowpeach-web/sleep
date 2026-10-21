<?php

namespace WPML\Comments\Counts;

class ActivityScan {

	const BATCH_SIZE = 100;

	const MAX_BATCHES = 10;

	const STATUSES = array(
		'all'     => array( '0', '1' ),
		'approve' => array( '1' ),
	);

	private static $memo = array();

	private $wpdb;

	private $exclusions;

	public function __construct( \wpdb $wpdb, ?Exclusions $exclusions = null ) {
		$this->wpdb       = $wpdb;
		$this->exclusions = $exclusions ? $exclusions : new Exclusions();
	}

	public static function resetRequestCache() {
		self::$memo = array();
	}

	public function find( $language, $status, $needed ) {
		$needed = max( 0, (int) $needed );
		if ( ! is_string( $status ) || ! array_key_exists( $status, self::STATUSES ) || '' === (string) $language || 0 === $needed ) {
			return array();
		}

		$key = get_current_blog_id() . '|' . $language . '|' . $status;
		if ( ! isset( self::$memo[ $key ] ) ) {
			self::$memo[ $key ] = array(
				'ids'       => array(),
				'cursor'    => null,
				'batches'   => 0,
				'exhausted' => false,
			);
		}
		$state = &self::$memo[ $key ];

		$found = count( $state['ids'] );
		while ( $found < $needed && ! $state['exhausted'] && $state['batches'] < self::MAX_BATCHES ) {
			$rows = $this->batch( self::STATUSES[ $status ], $state['cursor'] );
			$state['batches']++;

			if ( ! is_array( $rows ) ) {
				$state['exhausted'] = true;
				break;
			}

			foreach ( $rows as $row ) {
				if ( $this->countsIn( $row, $language ) ) {
					$state['ids'][] = (int) $row['comment_ID'];
					$found++;
				}
			}

			if ( $rows ) {
				$last            = end( $rows );
				$state['cursor'] = array(
					'date' => (string) $last['comment_date_gmt'],
					'id'   => (int) $last['comment_ID'],
				);
			}

			if ( count( $rows ) < self::BATCH_SIZE ) {
				$state['exhausted'] = true;
			}
		}

		return array_slice( $state['ids'], 0, $needed );
	}

	private function countsIn( array $row, $language ) {
		return $language === $row['post_language']
			&& ( null === $row['own_language'] || $language === $row['own_language'] )
			&& ! in_array( $row['post_type'], $this->exclusions->hiddenPostTypes(), true );
	}

	private function batch( array $approved, $cursor ) {
		$wpdb  = $this->wpdb;
		$types = $this->exclusions->hiddenCommentTypes();

		if ( null === $cursor ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT c.comment_ID, c.comment_date_gmt, p.post_type, pl.language_code AS post_language, cl.language_code AS own_language
					FROM (SELECT comment_ID, comment_post_ID, comment_date_gmt FROM {$wpdb->comments}
						WHERE comment_approved IN (" . implode( ',', array_fill( 0, count( $approved ), '%s' ) ) . ')
						AND comment_type NOT IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ")
						ORDER BY comment_date_gmt DESC, comment_ID DESC
						LIMIT %d) c
					LEFT JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
					LEFT JOIN {$wpdb->prefix}icl_translations pl ON pl.element_id = c.comment_post_ID
						AND pl.element_type = CONCAT('post_', p.post_type)
					LEFT JOIN {$wpdb->prefix}icl_translations cl ON cl.element_id = c.comment_ID
						AND cl.element_type = 'comment'
					ORDER BY c.comment_date_gmt DESC, c.comment_ID DESC",
					...array_merge( $approved, $types, array( self::BATCH_SIZE ) )
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT c.comment_ID, c.comment_date_gmt, p.post_type, pl.language_code AS post_language, cl.language_code AS own_language
					FROM (SELECT comment_ID, comment_post_ID, comment_date_gmt FROM {$wpdb->comments}
						WHERE comment_approved IN (" . implode( ',', array_fill( 0, count( $approved ), '%s' ) ) . ')
						AND comment_type NOT IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ")
						AND ( comment_date_gmt < %s OR ( comment_date_gmt = %s AND comment_ID < %d ) )
						ORDER BY comment_date_gmt DESC, comment_ID DESC
						LIMIT %d) c
					LEFT JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
					LEFT JOIN {$wpdb->prefix}icl_translations pl ON pl.element_id = c.comment_post_ID
						AND pl.element_type = CONCAT('post_', p.post_type)
					LEFT JOIN {$wpdb->prefix}icl_translations cl ON cl.element_id = c.comment_ID
						AND cl.element_type = 'comment'
					ORDER BY c.comment_date_gmt DESC, c.comment_ID DESC",
					...array_merge( $approved, $types, array( $cursor['date'], $cursor['date'], $cursor['id'], self::BATCH_SIZE ) )
				),
				ARRAY_A
			);
		}

		return $wpdb->last_error ? null : $rows;
	}
}
