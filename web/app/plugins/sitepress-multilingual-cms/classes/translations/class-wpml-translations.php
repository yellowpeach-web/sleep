<?php

class WPML_Translations extends WPML_SP_User {

	const ELEMENT_TYPE_POST_PREFIX = 'post_';

	private const TRID_GENERATION_KEY = 'trid_generation_';

	const CACHE_KEY_VERSION = 'v2';

	const MAX_GROUPS_FLUSHED_ONE_BY_ONE = 100;

	public $skip_empty = false;
	public $all_statuses = false;
	public $skip_cache = false;
	public $skip_recursions = false;

	private $duplicated_by              = array();
	private $mark_as_duplicate_meta_key = '_icl_lang_duplicate_of';
	private $wpml_cache;

	public function __construct( SitePress $sitepress, ?WPML_WP_Cache $wpml_cache = null ) {
		parent::__construct( $sitepress );
		$this->wpml_cache = $wpml_cache ? $wpml_cache : new WPML_WP_Cache( WPML_ELEMENT_TRANSLATIONS_CACHE_GROUP );
	}

	private function check_current_user_can( $capability ) {
		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		if ( 0 === $user_id ) {
			return false;
		}

		return current_user_can( $capability );
	}

	public function get_translations( $trid, $wpml_element_type ) {
		list( $edit_cap, $read_private_cap ) = $this->get_post_type_capability_flags( $wpml_element_type );

		$cache_key       = self::build_cache_key( $trid, $wpml_element_type, $this->skip_empty, $this->all_statuses, $this->skip_recursions, $edit_cap, $read_private_cap, $this->visibility_scope( $wpml_element_type ) );
		$trid_generation = $this->get_trid_generation( $trid );
		$cache_found     = false;

		$entry = $this->wpml_cache->get( $cache_key, $cache_found );
		if ( ! $this->skip_cache && $cache_found && $this->is_current_entry( $entry, $trid_generation ) ) {
			return $entry['translations'];
		}

		$translations = array();
		if ( $trid ) {
			$grouped      = $this->fetch_grouped_translations( array( (int) $trid ), $wpml_element_type );
			$translations = isset( $grouped[ (int) $trid ] ) ? $grouped[ (int) $trid ] : array();
		}

		$this->cache_translations( $trid, $wpml_element_type, $translations, $edit_cap, $read_private_cap, $trid_generation );

		return $translations;
	}

	private function fetch_grouped_translations( array $trids, $wpml_element_type ) {
		$sql_parts = array(
			'select'   => array(),
			'join'     => array(),
			'where'    => array(),
			'group_by' => array(),
		);

		if ( $this->wpml_element_type_is_post( $wpml_element_type ) ) {
			$sql_parts = $this->get_sql_parts_for_post( $wpml_element_type, $sql_parts );
		} elseif ( $this->wpml_element_type_is_taxonomy( $wpml_element_type ) ) {
			$sql_parts = $this->get_sql_parts_for_taxonomy( $sql_parts );
		}
		$sql_parts['where'][] = ' AND wpml_translations.trid IN (' . implode( ',', array_map( 'intval', $trids ) ) . ') ';

		$select   = implode( ' ', $sql_parts['select'] );
		$join     = implode( ' ', $sql_parts['join'] );
		$where    = implode( ' ', $sql_parts['where'] );
		$group_by = implode( ' ', $sql_parts['group_by'] );

		$query = "
			SELECT wpml_translations.trid, wpml_translations.translation_id, wpml_translations.language_code, wpml_translations.element_id, wpml_translations.source_language_code, wpml_translations.element_type, NULLIF(wpml_translations.source_language_code, '') IS NULL AS original
			{$select}
			FROM {$this->sitepress->get_wpdb()->prefix}icl_translations wpml_translations
				 {$join}
			WHERE 1 {$where}
			{$group_by}
		";

		$results = $this->sitepress->get_wpdb()->get_results( $query );

		$grouped = array();
		foreach ( (array) $results as $translation ) {
			if ( $this->must_ignore_translation( $translation ) ) {
				continue;
			}

			$grouped[ (int) $translation->trid ][ $translation->language_code ] = $translation;
		}

		if ( $this->applies_visibility_policy( $wpml_element_type ) ) {
			foreach ( $grouped as $group_trid => $group ) {
				$grouped[ $group_trid ] = \WPML\Translation\ElementVisibility::filterReadablePosts( $group );
			}
		}

		return $grouped;
	}

	public function prime_cache_for_trids( $trids, $wpml_element_type ) {
		$trids = self::normalize_trids( $trids );
		if ( ! $trids ) {
			return;
		}

		list( $edit_cap, $read_private_cap ) = $this->get_post_type_capability_flags( $wpml_element_type );

		$trid_generations = $this->get_trid_generations( $trids );
		$uncached_trids   = $this->skip_cache
			? $trids
			: $this->get_uncached_trids( $trid_generations, $wpml_element_type, $edit_cap, $read_private_cap );
		if ( ! $uncached_trids ) {
			return;
		}

		foreach ( $this->fetch_grouped_translations( $uncached_trids, $wpml_element_type ) as $trid => $translations ) {
			$this->cache_translations( $trid, $wpml_element_type, $translations, $edit_cap, $read_private_cap, $trid_generations[ $trid ] );
		}
	}

	public static function flush_cache_for_trids( $trids, ?WPML_WP_Cache $wpml_cache = null ) {
		$wpml_cache = $wpml_cache ? $wpml_cache : new WPML_WP_Cache( WPML_ELEMENT_TRANSLATIONS_CACHE_GROUP );
		$trids      = self::normalize_trids( $trids );

		if ( count( $trids ) > self::MAX_GROUPS_FLUSHED_ONE_BY_ONE ) {
			$wpml_cache->flush_group_cache();
			SitePress::clear_all_source_language_by_trid_cache();

			return;
		}

		foreach ( $trids as $trid ) {
			$wpml_cache->set( self::TRID_GENERATION_KEY . $trid, self::new_trid_generation(), 0 );
			SitePress::clear_source_language_by_trid_cache( $trid );
		}
	}

	public static function flush_cache_for_all_trids( ?WPML_WP_Cache $wpml_cache = null ) {
		$wpml_cache = $wpml_cache ? $wpml_cache : new WPML_WP_Cache( WPML_ELEMENT_TRANSLATIONS_CACHE_GROUP );
		$wpml_cache->flush_group_cache();
		SitePress::clear_all_source_language_by_trid_cache();
	}

	public static function flush_cache_after_source_repair( $trid, $repaired_rows, $promoted ) {
		$repaired_rows = (int) $repaired_rows;
		if ( $repaired_rows > ( $promoted ? 1 : 0 ) ) {
			self::flush_cache_for_all_trids();
		} elseif ( $repaired_rows > 0 ) {
			self::flush_cache_for_trids( array( $trid ) );
		}
	}

	private function get_uncached_trids( array $trid_generations, $wpml_element_type, $edit_cap, $read_private_cap ) {
		$cache_keys = array();
		foreach ( array_keys( $trid_generations ) as $trid ) {
			$cache_keys[ $trid ] = self::build_cache_key( $trid, $wpml_element_type, $this->skip_empty, $this->all_statuses, $this->skip_recursions, $edit_cap, $read_private_cap, $this->visibility_scope( $wpml_element_type ) );
		}

		$entries        = $this->wpml_cache->get_multiple( array_values( $cache_keys ) );
		$uncached_trids = array();
		foreach ( $cache_keys as $trid => $cache_key ) {
			if ( ! isset( $entries[ $cache_key ] ) || ! $this->is_current_entry( $entries[ $cache_key ], $trid_generations[ $trid ] ) ) {
				$uncached_trids[] = $trid;
			}
		}

		return $uncached_trids;
	}

	private function get_trid_generation( $trid ) {
		$trid = (int) $trid;
		if ( ! $trid ) {
			return '';
		}

		$found      = false;
		$generation = $this->wpml_cache->get( self::TRID_GENERATION_KEY . $trid, $found );
		if ( $found && is_string( $generation ) ) {
			return $generation;
		}

		return $this->start_trid_generation( $trid );
	}

	private function get_trid_generations( array $trids ) {
		$keys = array();
		foreach ( $trids as $trid ) {
			$keys[ $trid ] = self::TRID_GENERATION_KEY . $trid;
		}

		$stored      = $this->wpml_cache->get_multiple( array_values( $keys ) );
		$generations = array();
		foreach ( $keys as $trid => $key ) {
			$generations[ $trid ] = isset( $stored[ $key ] ) && is_string( $stored[ $key ] )
				? $stored[ $key ]
				: $this->start_trid_generation( $trid );
		}

		return $generations;
	}

	private function start_trid_generation( $trid ) {
		$generation = self::new_trid_generation();
		if ( $this->wpml_cache->add( self::TRID_GENERATION_KEY . $trid, $generation, 0 ) ) {
			return $generation;
		}

		$found   = false;
		$current = $this->wpml_cache->get( self::TRID_GENERATION_KEY . $trid, $found );

		return $found && is_string( $current ) ? $current : $generation;
	}

	private static function new_trid_generation() {
		return uniqid( '', true );
	}

	private static function normalize_trids( $trids ) {
		return array_values( array_unique( array_filter( array_map( 'intval', (array) $trids ) ) ) );
	}

	private function is_current_entry( $entry, $trid_generation ) {
		return is_array( $entry )
			&& isset( $entry['trid_generation'] )
			&& array_key_exists( 'translations', $entry )
			&& $entry['trid_generation'] === $trid_generation;
	}

	private function cache_translations( $trid, $wpml_element_type, array $translations, $edit_cap, $read_private_cap, $trid_generation ) {
		if ( ! $translations ) {
			return;
		}

		$cache_key = self::build_cache_key( (int) $trid, $wpml_element_type, $this->skip_empty, $this->all_statuses, $this->skip_recursions, $edit_cap, $read_private_cap, $this->visibility_scope( $wpml_element_type ) );
		$this->wpml_cache->set(
			$cache_key,
			array(
				'trid_generation' => $trid_generation,
				'translations'    => $translations,
			)
		);
	}

	private function applies_visibility_policy( $wpml_element_type ) {
		return $this->wpml_element_type_is_post( $wpml_element_type )
			&& ! $this->all_statuses
			&& 'post_attachment' !== $wpml_element_type
			&& ! $this->is_admin_request_of_a_user()
			&& ! \WPML\Core\Security\ExecutionContext\ExecutionContextHolder::isTrusted();
	}

	private function is_admin_request_of_a_user() {
		if ( ! is_admin() ) {
			return false;
		}

		if ( ! did_action( 'init' ) || ! function_exists( 'get_current_user_id' ) ) {
			return true;
		}

		return 0 !== (int) get_current_user_id();
	}

	private function visibility_scope( $wpml_element_type ) {
		if ( ! $this->wpml_element_type_is_post( $wpml_element_type ) ) {
			return '';
		}

		if ( \WPML\Core\Security\ExecutionContext\ExecutionContextHolder::isTrusted() ) {
			return 'trusted';
		}

		if ( $this->applies_visibility_policy( $wpml_element_type ) ) {
			$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

			return $user_id ? 'user:' . $user_id : 'anonymous';
		}

		return 'unfiltered';
	}

	private function get_post_type_capability_flags( $wpml_element_type ) {
		$edit_cap         = false;
		$read_private_cap = false;

		if ( 0 === strpos( $wpml_element_type, self::ELEMENT_TYPE_POST_PREFIX ) ) {
			$post_type = substr( $wpml_element_type, strlen( self::ELEMENT_TYPE_POST_PREFIX ) );
			if ( $post_type ) {
				$post_type_plural = $post_type . 's';
				$post_type_plural = apply_filters( 'wpml_translations_post_type_plural_capability', $post_type_plural, $post_type );

				$edit_cap         = $this->check_current_user_can( sprintf( 'edit_%s', $post_type_plural ) );
				$read_private_cap = $this->check_current_user_can( sprintf( 'read_private_%s', $post_type_plural ) );
			}
		}

		return array( $edit_cap, $read_private_cap );
	}

	public static function build_cache_key( $trid, $wpml_element_type, $skip_empty, $all_statuses, $skip_recursions, $edit_cap = false, $read_private_cap = false, $visibility_scope = '' ) {
		$caps_apply = 0 === strpos( $wpml_element_type, 'post_' ) && substr( $wpml_element_type, 5 );

		$cache_key_args = [
			'trid'             => (int) $trid,
			'element_type'     => (string) $wpml_element_type,
			'skip_empty'       => (bool) $skip_empty,
			'all_statuses'     => (bool) $all_statuses,
			'skip_recursions'  => (bool) $skip_recursions,
			'edit_cap'         => $caps_apply && (bool) $edit_cap,
			'read_private_cap' => $caps_apply && (bool) $read_private_cap,
			'visibility_scope' => $caps_apply ? (string) $visibility_scope : '',
		];

		return self::CACHE_KEY_VERSION . ':' . md5( (string) wp_json_encode( $cache_key_args ) );
	}

	public function link_elements( WPML_Translation_Element $source_translation_element, WPML_Translation_Element $target_translation_element, $target_language = null ) {
		if ( null !== $target_language ) {
			$this->set_language_code( $target_translation_element, $target_language );
		}
		$this->set_source_element( $target_translation_element, $source_translation_element );
	}

	public function set_source_element( WPML_Translation_Element $element, WPML_Translation_Element $source_element ) {
		$this->elements_type_matches( $element, $source_element );

		$this->sitepress->set_element_language_details( $element->get_element_id(), $element->get_wpml_element_type(), $source_element->get_trid(), $element->get_language_code(), $source_element->get_language_code() );

		$element->flush_cache();
	}

	private function elements_type_matches( $element1, $element2 ) {
		if ( get_class( $element1 ) !== get_class( $element2 ) ) {
			throw new UnexpectedValueException( '$source_element is not an instance of ' . get_class( $element1 ) . ': instance of ' . get_class( $element2 ) . ' received instead.' );
		}
	}

	public function set_language_code( WPML_Translation_Element $element, $language_code ) {
		$element_id        = $element->get_element_id();
		$wpml_element_type = $element->get_wpml_element_type();
		$trid              = $element->get_trid();
		$this->sitepress->set_element_language_details( $element_id, $wpml_element_type, $trid, $language_code );
		$element->flush_cache();
	}

	public function set_trid( WPML_Translation_Element $element, $trid ) {
		if ( ! $element->get_language_code() ) {
			throw new UnexpectedValueException( 'Element has no language information.' );
		}
		$this->sitepress->set_element_language_details( $element->get_element_id(), $element->get_wpml_element_type(), $trid, $element->get_language_code() );
		$element->flush_cache();
	}

	public function make_duplicate_of( WPML_Translation_Element $duplicate, WPML_Translation_Element $original ) {
		$this->validate_duplicable_element( $duplicate );
		$this->validate_duplicable_element( $original, 'source' );
		$this->set_source_element( $duplicate, $original );
		update_post_meta( $duplicate->get_id(), $this->mark_as_duplicate_meta_key, $original->get_id() );
		$duplicate->flush_cache();
		$this->duplicated_by[ $duplicate->get_id() ] = array();
	}

	public function is_a_duplicate_of( WPML_Translation_Element $element ) {
		$this->validate_duplicable_element( $element );
		$duplicate_of = get_post_meta( $element->get_id(), $this->mark_as_duplicate_meta_key, true );
		if ( $duplicate_of ) {
			return new WPML_Post_Element( $duplicate_of, $this->sitepress );
		}

		return null;
	}

	public function is_duplicated_by( WPML_Translation_Element $element ) {
		$this->validate_duplicable_element( $element );

		$this->init_cache_for_element( $element );

		if ( ! $this->duplicated_by[ $element->get_id() ] ) {
			$this->duplicated_by[ $element->get_id() ] = array();

			$args = array(
				'post_type'  => $element->get_wp_element_type(),
				'meta_query' => array(
					array(
						'key'     => $this->mark_as_duplicate_meta_key,
						'value'   => $element->get_id(),
						'compare' => '=',
					),
				),
			);

			$query = new WP_Query( $args );

			$results = $query->get_posts();
			foreach ( $results as $post ) {
				$this->duplicated_by[ $element->get_id() ][] = new WPML_Post_Element( $post->ID, $this->sitepress );
			}
		}

		return $this->duplicated_by[ $element->get_id() ];
	}

	private function validate_duplicable_element( WPML_Translation_Element $element, $argument_name = 'element' ) {
		if ( ! ( $element instanceof WPML_Duplicable_Element ) ) {
			throw new UnexpectedValueException( sprintf( 'Argument %s does not implement `WPML_Duplicable_Element`.', $argument_name ) );
		}
	}

	private function init_cache_for_element( WPML_Translation_Element $element ) {
		if ( ! array_key_exists( $element->get_id(), $this->duplicated_by ) ) {
			$this->duplicated_by[ $element->get_id() ] = array();
		}
	}

	private function get_sql_parts_for_post( $element_type, $sql_parts ) {
		$sql_parts['select'][] = ', p.post_title, p.post_status';
		$sql_parts['join'][]   = " LEFT JOIN {$this->sitepress->get_wpdb()->posts} p ON wpml_translations.element_id=p.ID";

		if ( ! $this->all_statuses && 'post_attachment' !== $element_type && ! $this->is_admin_request_of_a_user() ) {
			$sql_parts['where'][] = ' AND (p.post_status IN (' . $this->get_public_statuses() . ", 'draft', 'private', 'pending', 'future'))";
		}

		return $sql_parts;
	}

	private function get_public_statuses() {
		return wpml_prepare_in( get_post_stati( [ 'public' => true ] ) );
	}

	private function get_sql_parts_for_taxonomy( $sql_parts ) {
		$sql_parts['select'][]   = ', tm.name, tm.term_id, COUNT(tr.object_id) AS instances';
		$sql_parts['join'][]     = " LEFT JOIN {$this->sitepress->get_wpdb()->term_taxonomy} tt ON wpml_translations.element_id=tt.term_taxonomy_id
							  LEFT JOIN {$this->sitepress->get_wpdb()->terms} tm ON tt.term_id = tm.term_id
							  LEFT JOIN {$this->sitepress->get_wpdb()->term_relationships} tr ON tr.term_taxonomy_id=tt.term_taxonomy_id
							  ";
		$sql_parts['group_by'][] = 'GROUP BY tm.term_id';

		return $sql_parts;
	}

	private function must_ignore_translation( stdClass $translation ) {
		return $this->skip_empty
			   && (
					! $translation->element_id
					|| $this->must_ignore_translation_for_taxonomy( $translation )
			   );
	}

	private function must_ignore_translation_for_taxonomy( stdClass $translation ) {
		if ( ! isset( $translation->instances ) ) {
			return false;
		}

		return $this->wpml_element_type_is_taxonomy( $translation->element_type )
			   && $translation->instances === 0
			   && ( ! $this->skip_recursions && ! _icl_tax_has_objects_recursive( $translation->element_id ) );
	}

	private function wpml_element_type_is_taxonomy( $wpml_element_type ) {
		return preg_match( '#^tax_(.+)$#', $wpml_element_type );
	}

	private function wpml_element_type_is_post( $wpml_element_type ) {
		return 0 === strpos( $wpml_element_type, self::ELEMENT_TYPE_POST_PREFIX );
	}
}
