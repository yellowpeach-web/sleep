<?php

use WPML\Element\API\TranslationsRepository;
use WPML\Posts\ListScreenRows;

class WPML_Custom_Columns implements IWPML_Action {
	const COLUMN_KEY              = 'icl_translations';
	const CUSTOM_COLUMNS_PRIORITY = 1010;
	const DUPLICATE_OF_META_KEY   = '_icl_lang_duplicate_of';

	private $sitepress;
	public $post_status_display;

	private $duplicate_of_map;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_posts_management_column( $columns ) {
		$new_columns = $columns;

		$flags_column = $this->get_flags_column();

		if ( $flags_column ) {
			$new_columns = [];
			foreach ( $columns as $column_key => $column_content ) {
				$new_columns[ $column_key ] = $column_content;
				if ( ( 'title' === $column_key || 'name' === $column_key ) && ! isset( $new_columns[ self::COLUMN_KEY ] ) ) {
					$new_columns[ self::COLUMN_KEY ] = $flags_column;
				}
			}
		}

		return $new_columns;
	}

	public function get_flags_column() {
		$active_languages = $this->get_filtered_active_languages();
		if ( count( $active_languages ) <= 1 ) {
			return '';
		}

		$current_language = $this->sitepress->get_current_language();
		unset( $active_languages[ $current_language ] );

		if ( ! count( $active_languages ) ) {
			return '';
		}

		/* translators: Name of the Languages screen: in the WPML menu, as the title of that screen, and as a column heading listing the languages of a piece of content. Plural noun. */
		$flags_column = '<span class="screen-reader-text">' . esc_html__( 'Languages', 'sitepress' ) . '</span>';
		foreach ( $active_languages as $language_data ) {
			$flags_column .= $this->get_flag_img( $language_data );
		}

		return $flags_column;
	}

	private function get_flag_img( $language_data ) {
		$url = $this->sitepress->get_flag_url( $language_data['code'] );

		if ( $url !== '' ) {
			return '<img src="' . esc_url( $url ) .
			       '" width="18" height="12" alt="' . esc_attr( $language_data['display_name'] ) . '" title="' .
			       esc_attr( $language_data['display_name'] ) . '" style="margin:2px" />';
		} else {

			return $language_data['code'];
		}
	}

	public function add_content_for_posts_management_column( $column_name, $post_id = null ) {
		global $post;

		if ( ! $post_id ) {
			$post_id = $post->ID;
		}

		if ( self::COLUMN_KEY !== $column_name ) {
			return;
		}

		$active_languages = $this->get_filtered_active_languages();
		if ( null === $this->post_status_display ) {
			$this->post_status_display = new WPML_Post_Status_Display( $active_languages );
		}
		unset( $active_languages[ $this->sitepress->get_current_language() ] );
		foreach ( $active_languages as $language_data ) {
			$icon_html = $this->post_status_display->get_status_html( $post_id, $language_data['code'] );
			echo $icon_html;
		}
	}

	public function show_management_column_content( $post_type ) {
		$user           = get_current_user_id();
		$hidden_columns = get_user_meta( $user, 'manageedit-' . $post_type . 'columnshidden', true );
		if ( '' === $hidden_columns ) {
			$is_visible = (bool) apply_filters( 'wpml_hide_management_column', true, $post_type );
			if ( false === $is_visible ) {
				update_user_meta( $user, 'manageedit-' . $post_type . 'columnshidden', array( self::COLUMN_KEY ) );
			}
			return $is_visible;
		}

		return ! is_array( $hidden_columns ) || ! in_array( self::COLUMN_KEY, $hidden_columns, true );
	}

	private function get_filtered_active_languages() {
		$active_languages = $this->sitepress->get_active_languages();
		return apply_filters( 'wpml_active_languages_access', $active_languages, array( 'action' => 'edit' ) );
	}

	public function add_hooks() {
		add_action(
			'admin_init',
			array(
				$this,
				'add_custom_columns_hooks',
			),
			self::CUSTOM_COLUMNS_PRIORITY
		);
	}

	public function add_custom_columns_hooks() {
		$post_type = isset( $_REQUEST['post_type'] ) ? $_REQUEST['post_type'] : 'post';
		if (
			$post_type && $this->has_custom_columns()
			&& array_key_exists( $post_type, $this->sitepress->get_translatable_documents() )
		) {

			add_filter(
				'manage_' . $post_type . '_posts_columns',
				array(
					$this,
					'add_posts_management_column',
				)
			);

			$show_management_column_content = $this->show_management_column_content( $post_type );
			if ( $show_management_column_content ) {
				if ( is_post_type_hierarchical( $post_type ) ) {
					add_action(
						'manage_pages_custom_column',
						array(
							$this,
							'add_content_for_posts_management_column',
						)
					);
				}
				add_action(
					'manage_posts_custom_column',
					array(
						$this,
						'add_content_for_posts_management_column',
					)
				);

				add_action( 'manage_posts_custom_column', [ $this, 'preloadTranslationData' ], 1, 0 );
				add_action( 'manage_pages_custom_column', [ $this, 'preloadTranslationData' ], 1, 0 );
			}
		}
	}

	public function preloadTranslationData() {
		static $loaded = false;
		if ( $loaded ) {
			return;
		}

		global $wp_query;
		$posts = $wp_query->posts;

		if ( is_array( $posts ) ) {
			$posts = ListScreenRows::of( $posts );
			TranslationsRepository::preloadForPosts( $posts );
			$this->preloadDuplicateOfMeta( $posts );
			$this->preloadStatusData( $posts );
			$loaded = true;
		}
	}

	private function preloadStatusData( array $posts ) {
		global $wpml_post_translations, $wpdb;

		$post_ids = array_filter( array_map( 'absint', wp_list_pluck( $posts, 'ID' ) ) );
		if ( empty( $post_ids ) || ! $wpml_post_translations ) {
			return;
		}

		$wpml_post_translations->prefetch_ids( $post_ids );

		$this->primeSiblingPostCache( $post_ids );

		$trids = $wpml_post_translations->get_trids();
		if ( ! $trids ) {
			return;
		}

		// Not gated on the TM licence - the status filter every cell goes through reads
		if ( function_exists( 'wpml_tm_load_element_translations' ) ) {
			wpml_tm_load_element_translations()->init_jobs( $trids );
		}

		foreach ( $this->listedPostTypes( $posts ) as $post_type ) {
			$translations = new WPML_Translations( $this->sitepress );
			$translations->prime_cache_for_trids( $trids, 'post_' . $post_type );
		}

		if ( class_exists( 'WPML_TM_ICL_Translations' ) ) {
			WPML_TM_ICL_Translations::prime_translations_cache( $wpdb, $trids );
		}

		if ( function_exists( 'wpml_load_core_tm' ) ) {
			$translation_management = wpml_load_core_tm();
			if ( $translation_management && method_exists( $translation_management, 'prime_translation_job_info' ) ) {
				$translation_management->prime_translation_job_info( $trids );
			}
		}
	}

	private function primeSiblingPostCache( array $post_ids ) {
		global $wpml_post_translations;

		$group_ids = array();
		foreach ( $post_ids as $post_id ) {
			foreach ( (array) $wpml_post_translations->get_element_translations( $post_id ) as $element_id ) {
				$group_ids[ (int) $element_id ] = true;
			}
		}

		$siblings = array_values( array_diff( array_keys( $group_ids ), $post_ids ) );

		if ( $siblings ) {
			_prime_post_caches( $siblings, false, false );
		}
	}

	private function preloadDuplicateOfMeta( array $posts ) {
		global $wpdb;

		$post_ids = array_filter( array_map( 'absint', wp_list_pluck( $posts, 'ID' ) ) );
		if ( empty( $post_ids ) ) {
			return;
		}

		$this->duplicate_of_map = [];
		$post_element_type_like = 'post\_%';

		$trids  = array_filter(
			array_map(
				'absint',
				$wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT trid FROM {$wpdb->prefix}icl_translations
						 WHERE element_type LIKE %s AND element_id IN (" . implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) ) . ')',
						array_merge( [ $post_element_type_like ], $post_ids )
					)
				)
			)
		);

		if ( ! empty( $trids ) ) {
			$siblings = array_filter(
				array_map(
					'absint',
					$wpdb->get_col(
						$wpdb->prepare(
							"SELECT element_id FROM {$wpdb->prefix}icl_translations
							 WHERE element_type LIKE %s AND trid IN (" . implode( ', ', array_fill( 0, count( $trids ), '%d' ) ) . ')',
							array_merge( [ $post_element_type_like ], $trids )
						)
					)
				)
			);

			if ( ! empty( $siblings ) ) {
				$this->duplicate_of_map = array_fill_keys( $siblings, '' );

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT post_id, meta_value FROM {$wpdb->postmeta}
						 WHERE meta_key = %s AND post_id IN (" . implode( ', ', array_fill( 0, count( $siblings ), '%d' ) ) . ')',
						array_merge( [ self::DUPLICATE_OF_META_KEY ], $siblings )
					)
				);
				foreach ( $rows as $row ) {
					$this->duplicate_of_map[ (int) $row->post_id ] = (string) $row->meta_value;
				}
			}
		}

		add_filter( 'get_post_metadata', [ $this, 'answerDuplicateOfFromPreload' ], 10, 4 );
		add_action( 'added_post_meta', [ $this, 'forgetPreloadedDuplicateOf' ], 10, 3 );
		add_action( 'updated_post_meta', [ $this, 'forgetPreloadedDuplicateOf' ], 10, 3 );
		add_action( 'deleted_post_meta', [ $this, 'forgetPreloadedDuplicateOf' ], 10, 3 );
	}

	private function listedPostTypes( array $posts ) {
		$types = array();

		foreach ( $posts as $post ) {
			if ( isset( $post->post_type ) && $post->post_type ) {
				$types[ (string) $post->post_type ] = true;
			}
		}

		return array_keys( $types );
	}

	public function answerDuplicateOfFromPreload( $check, $object_id, $meta_key, $single ) {
		if (
			null !== $check
			|| self::DUPLICATE_OF_META_KEY !== $meta_key
			|| ! $single
			|| ! isset( $this->duplicate_of_map[ (int) $object_id ] )
		) {
			return $check;
		}

		return [ $this->duplicate_of_map[ (int) $object_id ] ];
	}

	public function forgetPreloadedDuplicateOf( $meta_ids, $object_id, $meta_key ) {
		if ( self::DUPLICATE_OF_META_KEY === $meta_key ) {
			unset( $this->duplicate_of_map[ (int) $object_id ] );
		}
	}

	private function has_custom_columns() {
		global $pagenow;
		if ( 'edit.php' === $pagenow
			 || 'edit-pages.php' === $pagenow
			 || (
				 'admin-ajax.php' === $pagenow
				 && (
					 ( array_key_exists( 'action', $_POST ) && 'inline-save' === filter_var( $_POST['action'] ) )
					 || ( array_key_exists( 'action', $_GET ) && 'fetch-list' === filter_var( $_GET['action'] )
					 )
				 )
			 )
		) {
			return true;
		}

		return false;
	}
}
