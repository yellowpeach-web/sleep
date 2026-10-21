<?php

namespace WPML\Comments\Counts;

use WPML\QueryFiltering\CommentLanguageMismatchCheck;

class PostsListColumn {

	const SWAP_PRIORITY = PHP_INT_MAX;

	const CORE_CLASS = 'WP_Posts_List_Table';

	private static $active = null;

	private $wpdb;

	private $check;

	private $exclusions;

	private $sitepress;

	private $mismatched = array();

	public function __construct(
		?\wpdb $wpdb = null,
		?CommentLanguageMismatchCheck $check = null,
		?Exclusions $exclusions = null,
		?\SitePress $sitepress = null
	) {
		$this->wpdb       = $wpdb;
		$this->check      = $check;
		$this->exclusions = $exclusions ?: new Exclusions();
		$this->sitepress  = $sitepress;
	}

	public static function active() {
		return self::$active;
	}

	public static function resetRequestCache() {
		self::$active = null;
	}

	public function maybeSwapClass( $class_name, $args = array() ) {
		if ( self::CORE_CLASS !== $class_name || ! $this->isCoreListTableLoaded() ) {
			return $class_name;
		}

		$post_type = $this->postTypeOf( $args );
		if ( ! $post_type || ! $this->appliesTo( $post_type ) ) {
			return $class_name;
		}

		self::$active = $this;

		return PostsListTable::class;
	}

	public function prime( $posts ) {
		$this->mismatched = array();

		$post_ids = array();
		foreach ( is_array( $posts ) ? $posts : array() as $post ) {
			if ( is_object( $post ) && isset( $post->ID ) ) {
				$post_ids[] = (int) $post->ID;
			} elseif ( is_numeric( $post ) ) {
				$post_ids[] = (int) $post;
			}
		}
		if ( ! $post_ids ) {
			return;
		}

		$with_mismatch = array_keys( array_filter( $this->check()->primeGroupsForPosts( $post_ids ) ) );
		if ( ! $with_mismatch ) {
			return;
		}

		foreach ( $this->countMismatched( $with_mismatch ) as $row ) {
			$post_id = (int) $row['post_id'];
			$bucket  = '1' === (string) $row['status'] ? 'approved' : 'pending';
			if ( ! isset( $this->mismatched[ $post_id ] ) ) {
				$this->mismatched[ $post_id ] = array(
					'approved' => 0,
					'pending'  => 0,
				);
			}
			$this->mismatched[ $post_id ][ $bucket ] += (int) $row['mismatched'];
		}
	}

	public function hasNumbersFor( $post_id ) {
		return isset( $this->mismatched[ (int) $post_id ] );
	}

	public function pendingNumber( $post_id, $core_pending ) {
		return max( 0, (int) $core_pending - $this->mismatchedOf( $post_id, 'pending' ) );
	}

	public function approvedFilter( $post_id ) {
		$post_id  = (int) $post_id;
		$subtract = $this->mismatchedOf( $post_id, 'approved' );
		if ( ! $subtract ) {
			return null;
		}

		return static function ( $number, $filtered_post_id = 0 ) use ( $post_id, $subtract ) {
			return (int) $filtered_post_id === $post_id ? max( 0, (int) $number - $subtract ) : $number;
		};
	}

	protected function isCoreListTableLoaded() {
		return class_exists( self::CORE_CLASS, false );
	}

	private function appliesTo( $post_type ) {
		$sitepress = $this->sitepress();

		return $sitepress
			&& post_type_supports( $post_type, 'comments' )
			&& $sitepress->is_translated_post_type( $post_type )
			&& ! in_array( $post_type, $this->exclusions->hiddenPostTypes(), true )
			&& $this->check()->siteHasCommentLanguageRows();
	}

	private function postTypeOf( $args ) {
		$screen = is_array( $args ) && isset( $args['screen'] ) ? $args['screen'] : null;

		return is_object( $screen ) && isset( $screen->post_type ) && is_string( $screen->post_type ) ? $screen->post_type : '';
	}

	private function mismatchedOf( $post_id, $bucket ) {
		$post_id = (int) $post_id;

		return isset( $this->mismatched[ $post_id ] ) ? $this->mismatched[ $post_id ][ $bucket ] : 0;
	}

	private function countMismatched( array $post_ids ) {
		$wpdb = $this->wpdb();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.comment_post_ID AS post_id, c.comment_approved AS status, COUNT(*) AS mismatched
					FROM {$wpdb->comments} c
					INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
					INNER JOIN {$wpdb->prefix}icl_translations post_row
						ON post_row.element_id = p.ID
						AND post_row.element_type = CONCAT('post_', p.post_type)
					INNER JOIN {$wpdb->prefix}icl_translations comment_row
						ON comment_row.element_id = c.comment_ID
						AND comment_row.element_type = 'comment'
					WHERE c.comment_post_ID IN (" . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ")
						AND c.comment_approved IN ('0', '1')
						AND c.comment_type <> 'note'
						AND comment_row.language_code <> post_row.language_code
					GROUP BY c.comment_post_ID, c.comment_approved",
				...$post_ids
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	private function check() {
		if ( ! $this->check ) {
			$this->check = new CommentLanguageMismatchCheck( $this->wpdb() );
		}

		return $this->check;
	}

	private function wpdb() {
		if ( ! $this->wpdb ) {
			global $wpdb;

			$this->wpdb = $wpdb;
		}

		return $this->wpdb;
	}

	private function sitepress() {
		if ( ! $this->sitepress ) {
			global $sitepress;

			$this->sitepress = $sitepress instanceof \SitePress ? $sitepress : null;
		}

		return $this->sitepress;
	}
}
