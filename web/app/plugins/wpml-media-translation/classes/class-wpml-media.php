<?php

class WPML_Media implements IWPML_Action {
	const SETUP_RUN     = 'setup_run';
	const SETUP_STARTED = 'setup_started';

	private static $settings;
	private static $settings_option_key = '_wpml_media';
	private static $default_settings    = array(
		'version'                  => false,
		'media_files_localization' => array(
			'posts'         => true,
			'custom_fields' => true,
			'strings'       => true,
		),
		self::SETUP_RUN            => false,
	);

	public $languages;
	public $parents;
	public $unattached;
	private $wpdb;

	private $sitepress;

	private $menus_factory;

	private $image_translator;

	public function __construct( SitePress $sitepress, wpdb $wpdb, WPML_Media_Menus_Factory $menus_factory, WPML_Media_Image_Translate $image_translator ) {
		$this->sitepress        = $sitepress;
		$this->wpdb             = $wpdb;
		$this->menus_factory    = $menus_factory;
		$this->image_translator = $image_translator;
	}

	public function add_hooks() {
		add_action( 'wpml_loaded', array( $this, 'loaded' ), 2 );
		add_action( 'init', array( $this, 'plugin_localization' ) );
	}

	public static function has_settings() {
		return get_option( self::$settings_option_key );
	}

	public function loaded() {
		global $sitepress;
		if ( ! isset( $sitepress ) || ! $sitepress->get_setting( 'setup_complete' ) ) {
			return null;
		}


		if ( is_admin() ) {
			add_action( 'admin_init', array( 'WPML_Media_Upgrade', 'run' ) );
		}

		self::init_settings();

		global $sitepress_settings, $pagenow;

		$active_languages = $sitepress->get_active_languages();

		$this->languages = null;

		if ( $this->is_admin_or_xmlrpc() ) {

			add_action( 'wpml_admin_menu_configure', array( $this, 'menu' ) );

			if ( 1 < count( $active_languages ) ) {

				if ( $pagenow == 'media-upload.php' ) {
					add_action( 'pre_get_posts', array( $this, 'filter_media_upload_items' ), 10, 1 );
				}


				\WPML\Request\Adapter\Ajax::listen(
					'find_posts',
					array( $this, 'find_posts_filter' ),
					'WordPress core find_posts (media modal): adds a pre_get_posts language filter only; core verifies its own nonce and edit_posts',
					0
				);
			}
		} else {
			if ( WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === (int) $sitepress_settings['language_negotiation_type'] ) {
				add_filter( 'wp_get_attachment_url', array( $this, 'wp_get_attachment_url' ), 10, 2 );
			}
		}

		add_filter( 'WPML_filter_link', array( $this, 'filter_link' ), 10, 2 );
		add_filter( 'icl_ls_languages', array( $this, 'icl_ls_languages' ), 10, 1 );

		add_filter( 'wpml_media_id', [ $this, 'convertId' ], 10, 2 );
		add_filter( 'wpml_media_url', [ $this, 'convertUrl' ], 10, 2 );

		return null;
	}

	public function convertId( $id, $lang = null ) {
		return $this->sitepress->get_object_id( $id, 'attachment', true, $lang );
	}

	public function convertUrl( $url, $lang = null ) {
		if ( ! $lang ) {
			$lang = $this->sitepress->get_current_language();
		}

		$convertedUrl = $this->image_translator->get_translated_image_by_url( $url, null, $lang );

		return $convertedUrl ?: $url;
	}

	function is_admin_or_xmlrpc() {
		$is_admin  = is_admin();
		$is_xmlrpc = ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST );

		return $is_admin || $is_xmlrpc;
	}

	public function plugin_localization() {
		load_plugin_textdomain( 'wpml-media', false, WPML_MEDIA_FOLDER . '/locale' );
	}

	public static function init_settings() {
		if ( ! self::$settings ) {
			self::$settings = get_option( self::$settings_option_key, array() );
		}

		self::$settings = array_merge( self::$default_settings, self::$settings );
	}

	public static function has_setup_run() {
		return self::get_setting( self::SETUP_RUN );
	}

	public static function set_setup_run( $value = 1 ) {
		return self::update_setting( self::SETUP_RUN, $value );
	}

	public static function has_setup_started() {
		return self::get_setting( self::SETUP_STARTED );
	}

	public static function set_setup_started( $value = 1 ) {
		return self::update_setting( self::SETUP_STARTED, $value );
	}

	public static function get_setting( $name, $default = false ) {
		self::init_settings();
		if ( ! isset( self::$settings[ $name ] ) || ! self::$settings[ $name ] ) {
			return $default;
		}

		return self::$settings[ $name ];
	}

	public static function update_setting( $name, $value ) {
		self::init_settings();
		self::$settings[ $name ] = $value;

		return update_option( self::$settings_option_key, self::$settings );
	}

	static function is_valid_post_type( $post_type ) {
		global $wp_post_types;

		$post_types = array_keys( (array) $wp_post_types );

		return in_array( $post_type, $post_types );
	}

	function find_posts_filter() {
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
	}

	function pre_get_posts( $query ) {
		$query->query['suppress_filters']      = 0;
		$query->query_vars['suppress_filters'] = 0;
	}

	function updated_postmeta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( in_array( $meta_key, array( '_wpml_media_duplicate', '_wpml_media_featured' ) ) ) {
			global $sitepress;
			$el_type      = 'post_' . get_post_type( $object_id );
			$trid         = $sitepress->get_element_trid( $object_id, $el_type );
			$translations = $sitepress->get_element_translations( $trid, $el_type, true, true );
			foreach ( $translations as $translation ) {
				if ( $translation->element_id != $object_id ) {
					$t_meta_value = get_post_meta( $translation->element_id, $meta_key, true );
					if ( $t_meta_value != $meta_value ) {
						update_post_meta( $translation->element_id, $meta_key, $meta_value );
					}
				}
			}
		}
	}

	function filter_link( $url, $lang_info ) {
		return $url;
	}

	function wp_get_attachment_url( $url, $post_id ) {
		global $sitepress;

		return $sitepress->convert_url( $url );
	}

	function icl_ls_languages( $w_active_languages ) {
		static $doing_it = false;

		if ( is_attachment() && ! $doing_it ) {
			$doing_it = true;
			$w_active_languages = icl_get_languages( 'skip_missing=0' );
			$doing_it           = false;
		}

		return $w_active_languages;
	}

	function get_post_metadata( $value, $object_id, $meta_key, $single ) {
		if ( $meta_key == '_thumbnail_id' ) {

			global $wpdb;

			$thumbnail = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
					array(
						$object_id,
						$meta_key,
					)
				)
			);

			if ( $thumbnail == null ) {

				$post_type = $wpdb->get_var(
					$wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", array( $object_id ) )
				);
				$trid      = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT trid, source_language_code FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type = %s",
						array(
							$object_id,
							'post_' . $post_type,
						)
					)
				);
				if ( $trid ) {

					global $sitepress;

					$translations = $sitepress->get_element_translations( $trid->trid, 'post_' . $post_type );
					if ( isset( $translations[ $trid->source_language_code ] ) ) {
						$translation = $translations[ $trid->source_language_code ];
						$thumbnail = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
								array(
									$translation->element_id,
									$meta_key,
								)
							)
						);
						if ( $thumbnail ) {
							$value = $thumbnail;
						}
					}
				}
			} else {
				$value = $thumbnail;
			}
		}

		return $value;
	}

	public function menu( $menu_id ) {
		if ( 'WPML' !== $menu_id ) {
			return;
		}

		/* translators: Name of the Media Translation page in the WPML menu, and the heading at the top of that page. */
		$menu_label         = __( 'Media Translation', 'wpml-media' );
		$menu               = array();
		$menu['order']      = 600;
		$menu['page_title'] = $menu_label;
		$menu['menu_title'] = $menu_label;
		$menu['capability'] = WPML_Media::has_setup_run() ? 'edit_others_posts' : 'manage_options';
		$menu['menu_slug']  = 'wpml-media';
		$menu['function']   = array( $this, 'menu_content' );

		do_action( 'wpml_admin_menu_register_item', $menu );
	}

	public function menu_content() {
		$menus = $this->menus_factory->create();
		$menus->display();
	}

	public function translate_attachment_ids( $ids, $target_language ) {
		global $sitepress;
		$return_string = false;
		if ( ! is_array( $ids ) ) {
			$attachment_ids = explode( ',', $ids );
			$return_string  = true;
		}

		$translated_ids = array();
		if ( ! empty( $attachment_ids ) ) {
			foreach ( $attachment_ids as $attachment_id ) {
				$translated_id = $attachment_id;

				$trid = $sitepress->get_element_trid( $attachment_id, 'post_attachment' );
				if ( $trid ) {
					$id_translations = $sitepress->get_element_translations( $trid, 'post_attachment', false, true );
					foreach ( $id_translations as $language_code => $id_translation ) {
						if ( $language_code == $target_language ) {
							$translated_id = $id_translation->element_id;
							break;
						}
					}
				}

				$translated_ids[] = $translated_id;
			}
		}

		if ( $return_string ) {
			return implode( ',', $translated_ids );
		}

		return $translated_ids;

	}

	public function filter_media_upload_items( $query ) {
		$current_lang = $this->sitepress->get_current_language();
		$ids          = icl_cache_get( '_media_upload_attachments' . $current_lang );

		if ( false === $ids ) {
			$wpdb = $this->wpdb;
			$posts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT posts.ID
					FROM {$wpdb->posts} as posts, {$wpdb->prefix}icl_translations as icl_translations
					WHERE posts.post_type = 'attachment'
					AND icl_translations.element_id = posts.ID
					AND icl_translations.element_type = 'post_attachment'
					AND icl_translations.language_code = %s",
					$current_lang
				)
			);
			$ids   = array();
			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$ids[] = absint( $post->ID );
				}
			}

			icl_cache_set( '_media_upload_attachments' . $current_lang, $ids );
		}

		$query->set( 'post__in', $ids );
	}

}
