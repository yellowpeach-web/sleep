<?php

use WPML\Notices\RootPageNotice;
use WPML\UrlHandling\RootPage\RootPageCommand;

class WPML_Root_Page_Actions {

	const DELETED_ROOT_FLAG = 'wpml_root_page_deleted';

	const ROOT_PAGE_MISSING     = 'missing';
	const ROOT_PAGE_TRASHED     = 'trashed';
	const ROOT_PAGE_UNPUBLISHED = 'unpublished';
	const ROOT_PAGE_USABLE      = 'usable';

	private $sp_settings;

	public function __construct( &$sitepress_settings ) {
		$this->sp_settings = &$sitepress_settings;
	}

	public function delete_root_page_lang() {
		$root_id = $this->get_edited_root_page_id();

		if ( $root_id ) {
			self::delete_page_lang( $root_id );
		}
	}

	public static function delete_page_lang( $page_id ) {
		$update_args = array(
			'element_id'   => (int) $page_id,
			'element_type' => 'post_page',
			'context'      => 'post',
		);

		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'before_delete' ) ) );

		WPML_Translation_Records_Delete::translations_by_columns(
			array( 'element_id' => (int) $page_id, 'element_type' => 'post_page' ),
			array( '%d', '%s' )
		);

		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'after_delete' ) ) );
	}

	public function forget_deleted_root_page( $post_id ) {
		$root_id = $this->get_root_page_id();

		if ( ! $root_id || (int) $root_id !== (int) $post_id ) {
			return;
		}

		$urls                 = isset( $this->sp_settings['urls'] ) ? (array) $this->sp_settings['urls'] : array();
		$urls['root_page']    = 0;
		$urls['show_on_root'] = '';

		icl_set_setting( 'urls', $urls, true );

		update_option(
			self::DELETED_ROOT_FLAG,
			array(
				'id' => (int) $post_id,
				'at' => time(),
			),
			false
		);
	}

	public function forget_missing_root_page() {
		$root_id = (int) $this->get_root_page_id();

		if ( $root_id && ! get_post( $root_id ) ) {
			$this->forget_deleted_root_page( $root_id );
		}
	}

	public function is_url_root_page( $url ) {
		$ret = false;

		if ( $this->get_root_page_id() ) {
			$ret = WPML_Root_Page::is_root_page( $url );
		}

		return $ret;
	}

	public function get_root_page_id() {
		$urls_in_dirs = WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === WPML_URL_Format::of( $this->sp_settings );
		$urls = isset( $this->sp_settings['urls'] ) ? $this->sp_settings['urls'] : array();

		return $urls_in_dirs && isset( $urls['root_page'] )
		       && ! empty( $urls['directory_for_default_language'] )
		       && isset( $urls['show_on_root'] )
		       && $urls['show_on_root'] === 'page'
		       && $urls['root_page']
				? $urls['root_page'] : false;
	}

	public static function pending_root_page_id( $urls ) {
		$urls = (array) $urls;

		return ! empty( $urls[ RootPageCommand::PENDING_KEY ] ) && ! empty( $urls['root_page'] )
			? (int) $urls['root_page'] : 0;
	}

	public function get_edited_root_page_id() {
		$root_id = $this->get_root_page_id();

		if ( $root_id ) {
			return $root_id;
		}

		$pending_id = self::pending_root_page_id( isset( $this->sp_settings['urls'] ) ? $this->sp_settings['urls'] : array() );

		return $pending_id ? $pending_id : false;
	}

	function wpml_home_url_init() {
		global $pagenow, $sitepress;

		if ( $pagenow == 'post.php' || $pagenow == 'post-new.php' ) {

			$root_id = $this->get_edited_root_page_id();
			if ( ! empty( $_GET['wpml_root_page'] ) && ! empty( $root_id ) ) {
				$root_state = self::root_page_state( get_post( $root_id ) );
				if ( self::ROOT_PAGE_USABLE === $root_state || self::ROOT_PAGE_UNPUBLISHED === $root_state ) {
					$edit_link = get_edit_post_link( $root_id, 'no-display' );
					if ( $edit_link ) {
						wp_safe_redirect( $edit_link );
					}
					exit;
				}

				if ( self::ROOT_PAGE_TRASHED === $root_state ) {
					wp_safe_redirect( admin_url( 'admin.php?page=' . RootPageNotice::SETTINGS_PAGE . '&section=' . RootPageNotice::SETTINGS_SECTION ) );
					exit;
				}
			}

			if ( isset( $_GET['wpml_root_page'] ) && $_GET['wpml_root_page'] || ( isset( $_GET['post'] ) && $_GET['post'] == $root_id ) ) {
				remove_action( 'admin_head', array( $sitepress, 'post_edit_language_options' ) );
				add_action( 'admin_head', array( $this, 'wpml_home_url_language_box_setup' ) );
				remove_action( 'page_link', array( $sitepress, 'permalink_filter' ), 1 );
			}
		}
	}

	function wpml_home_url_exclude_root_page_from_menus( $args ) {
		if ( !empty( $args[ 'exclude' ] ) ) {
			$args[ 'exclude' ] .= ',';
		} else {
			$args[ 'exclude' ] = '';
		}
		$args[ 'exclude' ] .= $this->get_root_page_id();

		return $args;
	}

	function exclude_root_page_menu_item( $items ) {
		$root_id = $this->get_root_page_id();
		foreach ( $items as $key => $item ) {
			if ( isset( $item->object_id )
			     && isset( $item->type )
			     && $item->object_id == $root_id
			     && $item->type === 'post_type'
			) {
				unset( $items[ $key ] );
			}
		}

		return $items;
	}

	function wpml_home_url_exclude_root_page( $excludes ) {
		$excludes[ ] = $this->get_root_page_id();

		return $excludes;

	}

	function wpml_home_url_exclude_root_page2( $args ) {
		$args[ 'exclude' ][ ] = $this->get_root_page_id();

		return $args;
	}

	function wpml_home_url_get_pages( $pages ) {
		$root_id = $this->get_root_page_id();
		foreach ( $pages as $k => $page ) {
			if ( $page->ID == $root_id ) {
				unset( $pages[ $k ] );
				$pages = array_values ( $pages );
				break;
			}
		}

		return $pages;
	}

	function wpml_home_url_language_box_setup() {
		if ( WPML_Block_Editor_Helper::is_block_editor_screen() ) {
			return;
		}

		add_meta_box(
			WPML_Meta_Boxes_Post_Edit_HTML::WRAPPER_ID,
			/* translators: Column heading and field label in the WPML admin, for the language of a piece of content, for example on the Updated translations page (the language of the updated translation). Noun, singular. */
			__( 'Language', 'sitepress' ),
			array( $this, 'wpml_home_url_language_box' ),
			'page',
			apply_filters( 'wpml_post_edit_meta_box_context', 'side', WPML_Meta_Boxes_Post_Edit_HTML::WRAPPER_ID ),
			apply_filters( 'wpml_post_edit_meta_box_priority', 'high' )
		);
	}

	function wpml_home_url_language_box( $post ) {
		$root_id = $this->get_edited_root_page_id();
		if ( isset( $_GET[ 'wpml_root_page' ] )
		     || ( !empty( $root_id )
		          && $post->ID == $root_id ) ) {
			esc_html_e( "This page does not have a language since it's the site's root page.", 'sitepress' );
			echo RootPageCommand::fields();
		}
	}

	function wpml_home_url_save_post_actions( $pidd, $post ) {
		if ( ! RootPageCommand::isRequested() ) {
			return;
		}

		if ( isset( $_POST['autosave'] ) || ( isset( $post->post_type ) && 'revision' === $post->post_type ) ) {
			return;
		}

		if ( ! RootPageCommand::policy()->permits() ) {
			return;
		}

		( new RootPageCommand() )->assign( $post );
	}

	public function maybe_store_root_page_from_rest( $post, $request = null ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		$referer = ( is_object( $request ) && method_exists( $request, 'get_header' ) )
			? (string) $request->get_header( 'referer' )
			: (string) wp_get_raw_referer();

		if ( ! self::is_rest_root_page_creation( $post->post_status, $referer ) ) {
			return;
		}

		if ( ! RootPageCommand::restPolicy()->permits() ) {
			return;
		}

		( new RootPageCommand() )->assign( $post );
	}

	public static function is_rest_root_page_creation( $post_status, $referer ) {
		if ( 'auto-draft' === $post_status ) {
			return false;
		}

		$referer = (string) $referer;

		if ( strpos( $referer, 'wpml_root_page=1' ) === false ) {
			return false;
		}

		$path = wp_parse_url( $referer, PHP_URL_PATH );

		return is_string( $path ) && 'post-new.php' === basename( $path );
	}

	function wpml_home_url_setup_root_page() {
		global $sitepress, $wpml_query_filter;

		remove_action( 'template_redirect', 'redirect_canonical' );
		add_action( 'parse_query', array( $this, 'action_wpml_home_url_parse_query' ) );

		remove_filter( 'posts_join', array( $wpml_query_filter, 'posts_join_filter' ), 10 );
		remove_filter( 'posts_where', array( $wpml_query_filter, 'posts_where_filter' ), 10 );
		$root_id = $this->get_root_page_id();
		if ( $this->get_root_page_post( $root_id ) ) {
			$sitepress->ROOT_URL_PAGE_ID = $root_id;
		}
	}

	function wpml_home_url_parse_query( $q, $remove_filter = 'wpml_home_url_parse_query' ) {
		if ( ! $q->is_main_query() ) {
			return $q;
		}
		if ( ! WPML_Root_Page::is_current_request_root() ) {
			return $q;
		} else {
			remove_action( 'parse_query', array( $this, $remove_filter ) );

			$uri_path  = trim( wpml_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
			$uri_parts = explode( '/', $uri_path );
			$potential_pagination_parameter = array_pop( $uri_parts );

			$query_args = array();
			wp_parse_str( wpml_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY ), $query_args );

			foreach ( $query_args as $key => $value ) {
				$query_args[ $key ] = \WPML\API\Sanitize::string( $value);
			}

			if ( is_numeric( $potential_pagination_parameter ) ) {
				$query_args['page'] = $potential_pagination_parameter;
			}

			$q->parse_query( $query_args );
			$root_id = $this->get_root_page_id();
			add_action( 'parse_query', array( $this, $remove_filter ) );

			$root_page = false !== $root_id ? $this->get_root_page_post( $root_id ) : null;

			if ( $root_page ) {
				$q = $this->set_page_query_parameters( $q, $root_page, $query_args );
			} else {
				$front_page = get_option( 'page_on_front' );
				$front_post = $front_page ? $this->get_root_page_post( $front_page ) : null;
				if ( $front_post ) {
					$q = $this->set_page_query_parameters( $q, $front_post, $query_args );
				}
			}
		}

		return $q;
	}

	public static function is_forgotten_root( $urls ) {
		$urls = (array) $urls;

		return ! empty( $urls['directory_for_default_language'] )
			&& empty( $urls['show_on_root'] )
			&& empty( $urls['root_page'] );
	}

	public static function is_usable_root_page( $post ) {
		return is_object( $post ) && isset( $post->post_status ) && 'publish' === $post->post_status;
	}

	public static function root_page_state( $post ) {
		if ( ! is_object( $post ) ) {
			return self::ROOT_PAGE_MISSING;
		}

		if ( self::is_usable_root_page( $post ) ) {
			return self::ROOT_PAGE_USABLE;
		}

		return isset( $post->post_status ) && 'trash' === $post->post_status
			? self::ROOT_PAGE_TRASHED
			: self::ROOT_PAGE_UNPUBLISHED;
	}

	public static function restore_url( $page_id ) {
		$page_id = (int) $page_id;

		return wp_nonce_url( admin_url( 'post.php?post=' . $page_id . '&action=untrash' ), 'untrash-post_' . $page_id );
	}

	public static function settings_root_page_link( $post ) {
		$state = self::root_page_state( $post );

		if ( self::ROOT_PAGE_USABLE === $state || self::ROOT_PAGE_UNPUBLISHED === $state ) {
			return [
				'url'  => (string) get_edit_post_link( $post->ID ),
				'text' => __( 'Edit root page.', 'sitepress' ),
			];
		}

		if ( self::ROOT_PAGE_TRASHED === $state ) {
			if ( ! self::can_restore_root_page( $post ) ) {
				return [
					'url'  => '',
					/* translators: Shown on WPML > Settings > URLs and SEO, under "A page", to a user who may not restore the root page from the Trash. */
					'text' => __( 'The root page is in the Trash. Ask an administrator to restore it.', 'sitepress' ),
				];
			}

			return [
				'url'  => self::restore_url( $post->ID ),
				/* translators: Link on WPML > Settings > URLs and SEO, under "A page". It restores the root page from the Trash as a draft. */
				'text' => __( 'Restore the root page.', 'sitepress' ),
			];
		}

		return [
			'url'  => admin_url( 'post-new.php?post_type=page&wpml_root_page=1' ),
			'text' => __( 'Create root page.', 'sitepress' ),
		];
	}

	private static function can_restore_root_page( $post ) {
		return current_user_can( 'delete_post', $post->ID );
	}

	public static function root_page_save_error( $post ) {
		switch ( self::root_page_state( $post ) ) {
			case self::ROOT_PAGE_USABLE:
				return '';
			case self::ROOT_PAGE_UNPUBLISHED:
				return __( 'The root page is not published. Use the "Edit root page" link above to publish it, then save.', 'sitepress' );
			case self::ROOT_PAGE_TRASHED:
				if ( ! self::can_restore_root_page( $post ) ) {
					/* translators: Error when saving "A page" on WPML > Settings > URLs and SEO while the root page is in the Trash, shown to a user who may not restore it from the Trash. */
					return __( 'The root page is in the Trash. Ask an administrator to restore it, then publish it.', 'sitepress' );
				}

				/* translators: Error when saving "A page" on WPML > Settings > URLs and SEO while the root page is in the Trash. "Restore the root page" is the text of the link above the error. */
				return __( 'The root page is in the Trash. Use the "Restore the root page" link above, publish the page, then save.', 'sitepress' );
			default:
				return __( 'Please create a root page before saving. Use the "Create root page" link above.', 'sitepress' );
		}
	}

	public static function root_page_without_language( $urls, $post_translations ) {
		$urls    = (array) $urls;
		$root_id = isset( $urls['root_page'] ) ? (int) $urls['root_page'] : 0;

		if ( $root_id <= 0 ) {
			return 0;
		}

		$post = get_post( $root_id );
		if ( self::ROOT_PAGE_MISSING === self::root_page_state( $post ) || ! isset( $post->post_type ) || 'page' !== $post->post_type ) {
			return 0;
		}

		return $post_translations->get_element_lang_code( $root_id ) ? 0 : $root_id;
	}

	public function get_root_page_post( $page_id ) {
		$post = $page_id ? get_post( (int) $page_id ) : null;

		if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
			return null;
		}

		return self::is_usable_root_page( $post ) ? $post : null;
	}

	function action_wpml_home_url_parse_query( $q ) {
		$this->wpml_home_url_parse_query( $q, 'action_wpml_home_url_parse_query' );
	}


	private function set_page_query_parameters( $q, $page, $query_args ) {
		$page_id                  = (int) $page->ID;
		$q->query_vars['page_id'] = $page_id;
		$q->query['page_id']      = $page_id;
		$q->is_page               = 1;
		$q->queried_object        = new WP_Post( $page );
		$q->queried_object_id     = $page_id;
		$q->query_vars['error']   = '';
		$q->is_404                = false;
		$q->query['error']        = null;
		$q->is_home               = false;
		$q->is_singular           = true;

		if ( isset( $query_args['post_type'] ) ) {
			$q->query_vars['post_type'] = $query_args['post_type'];
			$q->query['post_type']      = $query_args['post_type'];
		} else {
			$q->query_vars['post_type'] = '';
			unset( $q->query['post_type'] );
		}

		return $q;
	}
}

function wpml_home_url_ls_hide_check() {
	global $sitepress;

	return $sitepress->get_setting( 'language_negotiation_type' ) == 1
	       && (bool) ( $urls = $sitepress->get_setting( 'urls' ) ) === true
	       && ! empty( $urls['directory_for_default_language'] )
	       && isset( $urls['show_on_root'] ) && $urls['show_on_root'] === 'page'
	       && ! empty( $urls['hide_language_switchers'] )
	       && ( $root_page_actions = wpml_get_root_page_actions_obj() )
	       && null !== $root_page_actions->get_root_page_post( $root_page_actions->get_root_page_id() )
	       && WPML_Root_Page::is_current_request_root();
}
