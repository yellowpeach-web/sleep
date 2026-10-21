<?php

class WPML_Taxonomy_Translation_Help_Notice {

	const NOTICE_GROUP = 'taxonomy-term-help-notices';

	private $wpml_admin_notices;

	private $notice = false;

	private $sitepress;

	public function __construct( WPML_Notices $wpml_admin_notices, SitePress $sitepress ) {
		$this->wpml_admin_notices = $wpml_admin_notices;
		$this->sitepress          = $sitepress;
	}

	public function add_hooks() {
		add_action( 'admin_init', array( $this, 'add_help_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function add_help_notice() {
		$notice = $this->create_and_set_term_translation_help_notice();
		if ( false !== $notice ) {
			$this->add_term_help_notice_to_admin_notices();
		}
	}

	public static function repair_stored_notices( $wpml_admin_notices = null ) {
		if ( ! $wpml_admin_notices instanceof WPML_Notices ) {
			$wpml_admin_notices = function_exists( 'wpml_get_admin_notices' ) ? wpml_get_admin_notices() : null;
		}

		if ( ! $wpml_admin_notices instanceof WPML_Notices ) {
			return;
		}

		$all_notices = $wpml_admin_notices->get_all_notices();

		if ( ! is_array( $all_notices ) || ! isset( $all_notices[ self::NOTICE_GROUP ] ) || ! is_array( $all_notices[ self::NOTICE_GROUP ] ) ) {
			return;
		}

		foreach ( $all_notices[ self::NOTICE_GROUP ] as $stored_notice ) {
			if ( ! $stored_notice instanceof WPML_Notice || $stored_notice->get_restrict_to_screen_ids() ) {
				continue;
			}

			self::restrict_to_term_screens_of_taxonomy( $stored_notice );
			$wpml_admin_notices->add_notice( $stored_notice, true );
		}
	}

	private function get_current_translatable_taxonomy() {
		return self::get_current_translatable_taxonomy_static();
	}

	private function create_and_set_term_translation_help_notice() {
		$taxonomy = $this->get_current_translatable_taxonomy();
		if ( false !== $taxonomy ) {
			$link_to_taxonomy_translation_screen = $this->build_tag_to_taxonomy_translation( $taxonomy );
			/* translators: Notice on the screen where terms are edited. %1$s: the name of the taxonomy, for example Categories, %2$s: a link, already wrapped in its tags, whose text is the name of the taxonomy translation screen. */
			$text = sprintf( esc_html__( 'Translating %1$s? Use the %2$s table for easier translation.', 'sitepress' ), esc_html( $taxonomy->labels->name ), $link_to_taxonomy_translation_screen );
			$this->set_notice( new WPML_Notice( $taxonomy->name, $text, self::NOTICE_GROUP ) );
		}

		return $this->get_notice();
	}

	private function add_term_help_notice_to_admin_notices() {
		$notice = $this->get_notice();
		$notice->set_css_class_types( 'info' );
		self::restrict_to_term_screens_of_taxonomy( $notice );
		/* translators: Button label that closes a notice and keeps it from coming back. Verb, imperative. */
		$action = $this->wpml_admin_notices->get_new_notice_action( esc_html__( 'Dismiss', 'sitepress' ), '#', false, false, false );
		$action->set_js_callback( 'wpml_dismiss_taxonomy_translation_notice' );
		$action->set_group_to_dismiss( $notice->get_group() );
		$notice->add_action( $action );
		$this->wpml_admin_notices->add_notice( $notice );
	}

	public static function validate_display_for_taxonomy( $taxonomy_id ) {
		if ( ! self::is_taxonomy_term_screen_static() ) {
			return false;
		}

		$current_taxonomy = self::get_current_translatable_taxonomy_static();
		if ( $current_taxonomy && $current_taxonomy->name === $taxonomy_id ) {
			return true;
		}

		return false;
	}

	private static function restrict_to_term_screens_of_taxonomy( WPML_Notice $notice ) {
		$taxonomy_id = (string) $notice->get_id();

		$notice->set_restrict_to_screen_ids( array( 'edit-' . $taxonomy_id, 'term' ) );
		$notice->add_display_callback( array( __CLASS__, 'validate_display_for_stored_notice' ) );
	}

	public static function validate_display_for_stored_notice( $notice = null ) {
		if ( ! self::is_taxonomy_term_screen_static() ) {
			return false;
		}

		$screen   = get_current_screen();
		$taxonomy = $screen && ! empty( $screen->taxonomy ) ? $screen->taxonomy : '';

		if ( '' === $taxonomy ) {
			return false;
		}

		if ( $notice instanceof WPML_Notice ) {
			return (string) $notice->get_id() === $taxonomy;
		}

		return self::is_translatable_taxonomy_static( $taxonomy );
	}

	private static function is_taxonomy_term_screen_static() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return in_array( $screen->base, array( 'edit-tags', 'term' ), true );
	}

	private static function get_current_translatable_taxonomy_static() {
		if ( empty( $_GET['taxonomy'] ) ) {
			return false;
		}

		$taxonomy_slug = sanitize_key( $_GET['taxonomy'] );

		if ( ! self::is_translatable_taxonomy_static( $taxonomy_slug ) ) {
			return false;
		}

		return get_taxonomy( $taxonomy_slug );
	}

	private static function is_translatable_taxonomy_static( $taxonomy ) {
		global $sitepress;

		if ( ! $sitepress ) {
			return false;
		}

		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object ) {
			return false;
		}

		$post_type               = isset( $taxonomy_object->object_type[0] ) ? $taxonomy_object->object_type[0] : 'post';
		$translatable_taxonomies = $sitepress->get_translatable_taxonomies( true, $post_type );

		return in_array( $taxonomy, $translatable_taxonomies, true );
	}

	private function build_tag_to_taxonomy_translation( $taxonomy ) {

		$url = add_query_arg(
			array(
				'page'     => WPML_PLUGIN_FOLDER . '/menu/taxonomy-translation.php',
				'taxonomy' => $taxonomy->name,
			),
			admin_url( 'admin.php' )
		);
		$url = apply_filters( 'wpml_taxonomy_term_translation_url', $url, $taxonomy->name );

		/* translators: Text of the link in that notice, following the word "the", as in "the Category translation table". %s: the name of the taxonomy in the singular. Keep the space at the start. */
		$label = sprintf( esc_html__( ' %s translation', 'sitepress' ), esc_html( $taxonomy->labels->singular_name ) );

		return '<a href="' . esc_url( $url ) . '">' . $label . '</a>';
	}

	public function set_notice( WPML_Notice $notice ) {
		$this->notice = $notice;
	}

	public function get_notice() {
		return $this->notice;
	}

	public function enqueue_scripts() {
		$notice = $this->get_notice();
		if ( $notice ) {
			wp_register_script( 'wpml-dismiss-taxonomy-help-notice', ICL_PLUGIN_URL . '/res/js/dismiss-taxonomy-help-notice.js', array( 'jquery' ), ICL_SITEPRESS_SCRIPT_VERSION );
			wp_localize_script(
				'wpml-dismiss-taxonomy-help-notice',
				'wpml_notice_information',
				array(
					'notice_id'    => $notice->get_id(),
					'notice_group' => $notice->get_group(),
				)
			);
			wp_enqueue_script( 'wpml-dismiss-taxonomy-help-notice' );
		}
	}
}
