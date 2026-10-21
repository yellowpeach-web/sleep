<?php

namespace WPML\Comments\Counts;

class GlobalFallback {

	const CACHE_DOMAIN = 'wpml_comment_counts_global';

	const QUERY_STATUSES = array(
		'approved'     => 'approve',
		'moderated'    => 'hold',
		'spam'         => 'spam',
		'trash'        => 'trash',
		'post-trashed' => 'post-trashed',
	);

	private static $memo = array();

	public static function resetRequestCache() {
		self::$memo = array();
	}

	public function buckets() {
		$blog_id = get_current_blog_id();

		if ( ! isset( self::$memo[ $blog_id ] ) ) {
			$buckets = array();
			foreach ( self::QUERY_STATUSES as $bucket => $status ) {
				$buckets[ $bucket ] = (int) get_comments(
					array(
						'count'                     => true,
						'update_comment_meta_cache' => false,
						'orderby'                   => 'none',
						'status'                    => $status,
						'cache_domain'              => self::CACHE_DOMAIN,
					)
				);
			}
			self::$memo[ $blog_id ] = $buckets;
		}

		return self::$memo[ $blog_id ];
	}

	public function unfilterOwnQueries( $filtered, $post_id = null, $comment_query = null ) {
		if (
			is_object( $comment_query )
			&& isset( $comment_query->query_vars['cache_domain'] )
			&& self::CACHE_DOMAIN === $comment_query->query_vars['cache_domain']
		) {
			return false;
		}

		return $filtered;
	}

	public function forget() {
		unset( self::$memo[ get_current_blog_id() ] );
	}
}
