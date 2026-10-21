<?php

class WPML_Single_Url_Cache_Invalidator implements IWPML_Action {

	const MAX_TARGETED_OBJECTS = 500;
	const MAX_SITES_PER_RUN    = 25;

	const AUTHOR_RENAME_CRON_HOOK = 'wpml_single_url_resolution_author_rename';

	private $wpdb;

	private $repository;

	private $generation;

	private $cache;

	private $invalidated_objects = [];

	private $invalidated_trids = [];

	private $generation_bumped = [];

	private $term_before_update = [];

	private $term_before_delete = [];

	private $pending_author_reassignments = [];

	public function __construct(
		wpdb $wpdb,
		WPML_Single_Url_Cache_Repository $repository,
		WPML_Single_Url_Cache_Generation $generation,
		WPML_Single_Url_Resolution_Cache $cache
	) {
		$this->wpdb       = $wpdb;
		$this->repository = $repository;
		$this->generation = $generation;
		$this->cache      = $cache;
	}

	public function add_hooks() {
		add_action( 'post_updated', [ $this, 'post_updated' ], 10, 3 );
		add_action( 'before_delete_post', [ $this, 'post_deleted' ], 10, 2 );
		add_action( 'trashed_post', [ $this, 'post_status_route_changed' ], 10, 1 );
		add_action( 'untrashed_post', [ $this, 'post_status_route_changed' ], 10, 1 );
		add_action( 'set_object_terms', [ $this, 'post_terms_changed' ], 10, 6 );
		add_action( 'profile_update', [ $this, 'author_updated' ], 10, 3 );
		add_action( 'deleted_user', [ $this, 'author_deleted' ], 10, 2 );
		add_action( self::AUTHOR_RENAME_CRON_HOOK, [ $this, 'process_author_rename_batch' ], 10, 1 );
		add_action( 'remove_user_from_blog', [ $this, 'author_removed_from_blog' ], 10, 3 );
		add_action( 'shutdown', [ $this, 'flush_pending_author_reassignments' ], 0, 0 );

		add_action( 'edit_terms', [ $this, 'before_term_updated' ], 10, 3 );
		add_action( 'edited_term', [ $this, 'term_updated' ], 10, 3 );
		add_action( 'pre_delete_term', [ $this, 'term_will_be_deleted' ], 10, 2 );
		add_action( 'delete_term', [ $this, 'term_deleted' ], 10, 4 );

		add_action( 'wpml_translation_update', [ $this, 'translation_updated' ], 10, 1 );
		add_action( 'icl_set_element_language', [ $this, 'element_language_set' ], 10, 4 );
		add_action( 'wpml_translated_slug_updated', [ $this, 'translated_slug_updated' ], 10, 2 );
		add_action( 'wpml_st_add_string_translation', [ $this, 'string_translation_added' ], 10, 4 );
		add_action( 'wpml_st_before_remove_strings', [ $this, 'strings_removed' ], 10, 1 );

		foreach ( $this->global_route_options() as $option ) {
			add_action( 'update_option_' . $option, [ $this, 'route_option_updated' ], 10, 3 );
		}

		add_action(
			'update_option_icl_sitepress_settings',
			[ $this, 'sitepress_settings_updated' ],
			10,
			3
		);
		add_action( 'switch_theme', [ $this, 'theme_switched' ], 10, 0 );
	}

	public function post_updated( $post_id, $post_after, $post_before ) {
		if ( ! $post_after instanceof WP_Post || ! $post_before instanceof WP_Post ) {
			return;
		}

		$route_fields = [ 'post_name', 'post_parent', 'post_status', 'post_type' ];
		foreach ( $route_fields as $field ) {
			if ( $post_before->{$field} !== $post_after->{$field} ) {
				$this->invalidate_post_tree( (int) $post_id, (string) $post_before->post_type );
				if ( $post_before->post_type !== $post_after->post_type ) {
					$this->invalidate_post_tree( (int) $post_id, (string) $post_after->post_type );
				}
				return;
			}
		}

		if (
			$post_before->post_date !== $post_after->post_date
			&& $this->permalink_structure_uses_any_token(
				[ 'year', 'monthnum', 'day', 'hour', 'minute', 'second' ]
			)
		) {
			$this->invalidate_post_tree( (int) $post_id, (string) $post_after->post_type );
			return;
		}

		if (
			(int) $post_before->post_author !== (int) $post_after->post_author
			&& $this->permalink_structure_uses_any_token( [ 'author' ] )
		) {
			$this->invalidate_post_tree( (int) $post_id, (string) $post_after->post_type );
		}
	}

	public function post_deleted( $post_id, $post = null ) {
		$post_type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
		$this->invalidate_post_tree( (int) $post_id, (string) $post_type );
	}

	public function post_status_route_changed( $post_id ) {
		$this->invalidate_post_tree( (int) $post_id, (string) get_post_type( $post_id ) );
	}

	public function author_updated( $user_id, $old_user_data = null, $userdata = [] ) {
		if (
			! is_object( $old_user_data )
			|| ! isset( $old_user_data->user_nicename )
		) {
			return;
		}

		$new_user = get_userdata( $user_id );
		if (
			! is_object( $new_user )
			|| ! isset( $new_user->user_nicename )
			|| (string) $old_user_data->user_nicename === (string) $new_user->user_nicename
		) {
			return;
		}

		if ( ! is_multisite() ) {
			$this->invalidate_author_routes();
			return;
		}

		$this->schedule_author_rename_walk( 0 );
	}

	public function process_author_rename_batch( $offset = 0 ) {
		$offset = max( 0, (int) $offset );

		if ( ! is_multisite() ) {
			$this->invalidate_author_routes();
			return;
		}

		$sites = get_sites(
			[
				'fields'  => 'ids',
				'number'  => self::MAX_SITES_PER_RUN,
				'offset'  => $offset,
				'deleted' => 0,
				'orderby' => 'id',
				'order'   => 'ASC',
			]
		);
		$sites = is_array( $sites ) ? $sites : [];

		foreach ( $sites as $site ) {
			$blog_id = is_object( $site ) && isset( $site->blog_id ) ? (int) $site->blog_id : (int) $site;
			if ( $blog_id < 1 ) {
				continue;
			}

			$switched = get_current_blog_id() !== $blog_id;
			if ( $switched ) {
				switch_to_blog( $blog_id );
			}

			try {
				$this->invalidate_author_routes();
			} finally {
				if ( $switched ) {
					restore_current_blog();
				}
			}
		}

		if ( count( $sites ) >= self::MAX_SITES_PER_RUN ) {
			$this->schedule_author_rename_walk( $offset + self::MAX_SITES_PER_RUN );
		}
	}

	private function invalidate_author_routes() {
		if (
			null !== get_option( WPML_Single_Url_Cache_Generation::OPTION_KEY, null )
			&& $this->permalink_structure_uses_any_token( [ 'author' ] )
		) {
			$this->invalidate_all( 'author_nicename' );
		}
	}

	private function schedule_author_rename_walk( $offset ) {
		$offset = max( 0, (int) $offset );

		if ( ! wp_next_scheduled( self::AUTHOR_RENAME_CRON_HOOK, [ $offset ] ) ) {
			wp_schedule_single_event( time() + 30, self::AUTHOR_RENAME_CRON_HOOK, [ $offset ] );
		}
	}

	public function author_deleted( $user_id, $reassign = null ) {
		$this->author_posts_will_be_reassigned( $user_id, $reassign, true );
	}

	public function author_removed_from_blog( $user_id, $blog_id, $reassign = 0 ) {
		$reassign = (int) $reassign;
		if ( $reassign < 1 || (int) $user_id === $reassign ) {
			return;
		}

		$blog_id = (int) $blog_id;
		if ( $blog_id < 1 ) {
			$blog_id = get_current_blog_id();
		}

		$this->pending_author_reassignments[ $blog_id ] = [
			'user_id'  => (int) $user_id,
			'reassign' => $reassign,
		];
	}

	public function flush_pending_author_reassignments() {
		$pending                            = $this->pending_author_reassignments;
		$this->pending_author_reassignments = [];

		foreach ( $pending as $blog_id => $reassignment ) {
			$switched = get_current_blog_id() !== (int) $blog_id;
			if ( $switched ) {
				switch_to_blog( (int) $blog_id );
			}

			try {
				$this->author_posts_will_be_reassigned(
					$reassignment['user_id'],
					$reassignment['reassign'],
					true
				);
			} finally {
				if ( $switched ) {
					restore_current_blog();
				}
			}
		}
	}

	public function post_terms_changed( $object_id, $terms, $tt_ids, $taxonomy, $append = false, $old_tt_ids = [] ) {
		if ( ! $this->taxonomy_affects_post_permalinks( $taxonomy ) ) {
			return;
		}

		$this->invalidate_post_tree( (int) $object_id, (string) get_post_type( $object_id ) );
	}

	public function before_term_updated( $term_id, $taxonomy, $args = [] ) {
		$term = get_term( $term_id, $taxonomy );
		if ( $term instanceof WP_Term ) {
			$this->term_before_update[ get_current_blog_id() ][ $this->term_key( $term_id, $taxonomy ) ] = [
				'slug'   => (string) $term->slug,
				'parent' => (int) $term->parent,
			];
		}
	}

	public function term_updated( $term_id, $term_taxonomy_id, $taxonomy ) {
		$blog_id = get_current_blog_id();
		$key     = $this->term_key( $term_id, $taxonomy );
		$before  = isset( $this->term_before_update[ $blog_id ][ $key ] )
			? $this->term_before_update[ $blog_id ][ $key ]
			: null;

		unset( $this->term_before_update[ $blog_id ][ $key ] );

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		if (
			null === $before
			|| $before['slug'] !== (string) $term->slug
			|| $before['parent'] !== (int) $term->parent
		) {
			$this->invalidate_term_route_change(
				(int) $term_id,
				(string) $taxonomy,
				'taxonomy_term_update'
			);
		}
	}

	public function term_will_be_deleted( $term_id, $taxonomy ) {
		$blog_id = get_current_blog_id();
		$key     = $this->term_key( $term_id, $taxonomy );
		$targets = $this->term_tree_targets( (int) $term_id, (string) $taxonomy );

		$this->term_before_delete[ $blog_id ][ $key ] = $targets;
		foreach ( $targets['ids'] as $target_id ) {
			$this->invalidate_object( 'term', $target_id );
		}
	}

	public function term_deleted( $term_id, $term_taxonomy_id, $taxonomy, $deleted_term = null ) {
		$blog_id  = get_current_blog_id();
		$key      = $this->term_key( $term_id, $taxonomy );
		$captured = isset( $this->term_before_delete[ $blog_id ][ $key ] );
		$targets  = $captured
			? $this->term_before_delete[ $blog_id ][ $key ]
			: $this->term_tree_targets( (int) $term_id, (string) $taxonomy );

		unset( $this->term_before_delete[ $blog_id ][ $key ] );
		if ( ! $captured && $taxonomy && is_taxonomy_hierarchical( $taxonomy ) ) {
			$targets['global'] = true;
		}

		foreach ( $targets['ids'] as $target_id ) {
			$this->invalidate_object( 'term', $target_id, true );
		}

		if ( $targets['global'] || $this->taxonomy_affects_post_permalinks( $taxonomy ) ) {
			$this->invalidate_all( 'taxonomy_term_delete', true );
		}
	}

	public function translation_updated( $args ) {
		if ( ! is_array( $args ) ) {
			return;
		}

		$type = isset( $args['type'] ) ? (string) $args['type'] : '';
		if ( in_array( $type, [ 'reset', 'before_language_delete' ], true ) ) {
			$this->invalidate_all( 'wpml_translation_' . $type );
			return;
		}

		if ( ! empty( $args['trid'] ) ) {
			$this->invalidate_trid( (int) $args['trid'] );
		}

		if ( empty( $args['element_id'] ) || empty( $args['element_type'] ) ) {
			return;
		}

		$element_type = (string) $args['element_type'];
		if ( 0 === strpos( $element_type, 'post_' ) ) {
			$this->invalidate_object( 'post', (int) $args['element_id'] );
		} elseif ( 0 === strpos( $element_type, 'tax_' ) ) {
			$term_id = $this->term_id_from_term_taxonomy_id( (int) $args['element_id'] );
			if ( $term_id ) {
				$this->invalidate_object( 'term', $term_id );
			}
		}
	}

	public function element_language_set( $translation_id, $element_id, $language_code, $trid ) {
		if ( $trid ) {
			$this->invalidate_trid( (int) $trid );
		}
	}

	public function translated_slug_updated( $object_type = '', $name = '' ) {
		$reason = $object_type ? 'translated_slug_' . sanitize_key( $object_type ) : 'translated_slug';
		$this->invalidate_all( $reason );
	}

	public function string_translation_added( $translation_id, $translation_data = [], $language = '', $string_id = 0 ) {
		$this->endpoint_strings_changed( [ $string_id ] );
	}

	public function strings_removed( $string_ids ) {
		if ( is_array( $string_ids ) ) {
			$this->endpoint_strings_changed( $string_ids );
		}
	}

	private function endpoint_strings_changed( array $string_ids ) {
		$string_ids = array_values( array_filter( array_map( 'intval', $string_ids ) ) );

		if ( ! $string_ids || isset( $this->generation_bumped[ get_current_blog_id() ] ) ) {
			return;
		}

		$wpdb  = $this->wpdb;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}icl_strings WHERE context = %s AND id IN (" . implode( ',', array_fill( 0, count( $string_ids ), '%d' ) ) . ')',
				array_merge( [ WPML_Endpoints_Support::STRING_CONTEXT ], $string_ids )
			)
		);

		if ( (int) $found > 0 ) {
			$this->invalidate_all( 'endpoint_translation' );
		}
	}

	public function route_option_updated( $old_value, $new_value, $option = '' ) {
		if ( $old_value !== $new_value ) {
			$this->invalidate_all( 'option_' . sanitize_key( $option ) );
		}
	}

	public function sitepress_settings_updated( $old_settings, $new_settings, $option = '' ) {
		if (
			$this->routing_settings_fingerprint( $old_settings )
			!== $this->routing_settings_fingerprint( $new_settings )
		) {
			$this->invalidate_all( 'wpml_routing_settings' );
		}
	}

	public function theme_switched() {
		$this->invalidate_all( 'theme_switch' );
	}

	public function invalidate_all( $reason, $force = false ) {
		$blog_id = get_current_blog_id();
		if ( ! $force && isset( $this->generation_bumped[ $blog_id ] ) ) {
			return;
		}

		$old_generation = $this->generation->get();
		$new_generation = $this->generation->bump();

		$this->generation_bumped[ $blog_id ] = true;

		do_action(
			'wpml_single_url_resolution_cache_generation_changed',
			$old_generation,
			$new_generation,
			(string) $reason
		);
	}

	private function invalidate_post_tree( $post_id, $post_type ) {
		$this->invalidate_object( 'post', $post_id );

		if ( ! $post_type || ! is_post_type_hierarchical( $post_type ) ) {
			return;
		}

		$descendants = $this->post_descendants( $post_id, $post_type );
		if ( count( $descendants ) > self::MAX_TARGETED_OBJECTS ) {
			$this->invalidate_all( 'post_hierarchy' );
			return;
		}

		foreach ( $descendants as $descendant_id ) {
			$this->invalidate_object( 'post', $descendant_id );
		}
	}

	private function invalidate_term_tree( $term_id, $taxonomy ) {
		$targets = $this->term_tree_targets( $term_id, $taxonomy );
		foreach ( $targets['ids'] as $target_id ) {
			$this->invalidate_object( 'term', $target_id );
		}

		if ( $targets['global'] ) {
			$this->invalidate_all( 'term_hierarchy' );
		}
	}

	private function term_tree_targets( $term_id, $taxonomy ) {
		$targets = [
			'ids'    => [ (int) $term_id ],
			'global' => false,
		];

		if ( ! $taxonomy || ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return $targets;
		}

		$descendants = $this->term_descendants( $term_id, $taxonomy );
		if ( count( $descendants ) > self::MAX_TARGETED_OBJECTS ) {
			$targets['global'] = true;
			return $targets;
		}

		$targets['ids'] = array_values(
			array_unique(
				array_merge( $targets['ids'], array_map( 'intval', $descendants ) )
			)
		);

		return $targets;
	}

	private function invalidate_term_route_change( $term_id, $taxonomy, $reason ) {
		$this->invalidate_term_tree( $term_id, $taxonomy );

		if ( $this->taxonomy_affects_post_permalinks( $taxonomy ) ) {
			$this->invalidate_all( $reason );
		}
	}

	private function invalidate_object( $kind, $object_id, $force = false ) {
		if ( $object_id < 1 ) {
			return;
		}

		$blog_id = get_current_blog_id();
		$key     = $kind . ':' . $object_id;
		if ( ! $force && isset( $this->invalidated_objects[ $blog_id ][ $key ] ) ) {
			return;
		}

		$this->invalidated_objects[ $blog_id ][ $key ] = true;
		$cache_keys                                    = $this->repository->invalidate_object( $kind, $object_id );

		if ( count( $cache_keys ) > self::MAX_TARGETED_OBJECTS ) {
			$this->invalidate_all( $kind . '_fanout', $force );
			return;
		}

		$this->cache->delete_object_cache_keys( $cache_keys );
	}

	private function invalidate_trid( $trid ) {
		if ( $trid < 1 ) {
			return;
		}

		$blog_id = get_current_blog_id();
		if ( isset( $this->invalidated_trids[ $blog_id ][ $trid ] ) ) {
			return;
		}

		$this->invalidated_trids[ $blog_id ][ $trid ] = true;
		$cache_keys                                   = $this->repository->invalidate_trid( $trid );

		if ( count( $cache_keys ) > self::MAX_TARGETED_OBJECTS ) {
			$this->invalidate_all( 'trid_fanout' );
			return;
		}

		$this->cache->delete_object_cache_keys( $cache_keys );
	}

	private function post_descendants( $post_id, $post_type ) {
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_parent
				FROM {$wpdb->posts}
				WHERE post_type = %s AND post_parent > 0",
				$post_type
			),
			ARRAY_A
		);

		$children = [];
		foreach ( (array) $rows as $row ) {
			$children[ (int) $row['post_parent'] ][] = (int) $row['ID'];
		}

		return $this->walk_descendants( $post_id, $children );
	}

	private function term_descendants( $term_id, $taxonomy ) {
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id, parent
				FROM {$wpdb->term_taxonomy}
				WHERE taxonomy = %s AND parent > 0",
				$taxonomy
			),
			ARRAY_A
		);

		$children = [];
		foreach ( (array) $rows as $row ) {
			$children[ (int) $row['parent'] ][] = (int) $row['term_id'];
		}

		return $this->walk_descendants( $term_id, $children );
	}

	private function walk_descendants( $root_id, array $children ) {
		$found = [];
		$queue = isset( $children[ $root_id ] ) ? $children[ $root_id ] : [];

		while ( $queue ) {
			$id = (int) array_shift( $queue );
			if ( isset( $found[ $id ] ) ) {
				continue;
			}

			$found[ $id ] = true;
			if ( count( $found ) > self::MAX_TARGETED_OBJECTS ) {
				break;
			}

			if ( isset( $children[ $id ] ) ) {
				$queue = array_merge( $queue, $children[ $id ] );
			}
		}

		return array_keys( $found );
	}

	private function term_id_from_term_taxonomy_id( $term_taxonomy_id ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d",
				$term_taxonomy_id
			)
		);
	}

	private function taxonomy_affects_post_permalinks( $taxonomy ) {
		$structure = (string) get_option( 'permalink_structure', '' );
		if ( '' === $structure ) {
			return false;
		}

		if ( 'category' === $taxonomy && false !== strpos( $structure, '%category%' ) ) {
			return true;
		}

		return false !== strpos( $structure, '%' . $taxonomy . '%' );
	}

	private function permalink_structure_uses_any_token( array $tokens ) {
		$structure = (string) get_option( 'permalink_structure', '' );
		if ( '' === $structure ) {
			return false;
		}

		foreach ( $tokens as $token ) {
			if ( false !== strpos( $structure, '%' . $token . '%' ) ) {
				return true;
			}
		}

		return false;
	}

	private function author_posts_will_be_reassigned( $user_id, $reassign, $force = false ) {
		$reassign = (int) $reassign;
		if (
			$reassign < 1
			|| (int) $user_id === $reassign
			|| null === get_option( WPML_Single_Url_Cache_Generation::OPTION_KEY, null )
			|| ! $this->permalink_structure_uses_any_token( [ 'author' ] )
		) {
			return;
		}

		$this->invalidate_all( 'author_reassignment', $force );
	}

	private function global_route_options() {
		return [
			'home',
			'siteurl',
			'permalink_structure',
			'rewrite_rules',
			'show_on_front',
			'page_on_front',
			'page_for_posts',
			'category_base',
			'tag_base',
		];
	}

	private function routing_settings_fingerprint( $settings ) {
		$settings = is_array( $settings ) ? $settings : [];
		$keys     = [
			'default_language',
			'active_languages',
			'language_negotiation_type',
			'language_domains',
			'urls',
			'custom_posts_sync_option',
			'taxonomies_sync_option',
		];
		$routes   = [];

		foreach ( $keys as $key ) {
			$routes[ $key ] = array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
		}

		return md5( (string) wp_json_encode( $this->normalize_for_fingerprint( $routes ) ) );
	}

	private function normalize_for_fingerprint( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			$value[ $key ] = $this->normalize_for_fingerprint( $item );
		}

		if ( $this->is_associative( $value ) ) {
			ksort( $value );
		}

		return $value;
	}

	private function is_associative( array $value ) {
		return $value && array_keys( $value ) !== range( 0, count( $value ) - 1 );
	}

	private function term_key( $term_id, $taxonomy ) {
		return $taxonomy . ':' . (int) $term_id;
	}
}
