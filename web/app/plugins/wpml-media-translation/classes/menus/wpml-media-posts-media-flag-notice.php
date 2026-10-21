<?php

class WPML_Media_Posts_Media_Flag_Notice implements IWPML_Action {

	const PREPARE_ACTION = 'wpml-media-has-media-flag-prepare';
	const PROCESS_ACTION = 'wpml-media-has-media-flag';

	const NOTICE_ID    = 'wpml-media-posts-media-flag';
	const NOTICE_GROUP = 'wpml-media';

	const NEW_MEDIA_PAGE = 'tm/menu/main.php';
	const NEW_MEDIA_TAB  = 'media';

	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {

		if ( WPML_Media::has_setup_run() ) {
			return;
		}

		if ( ! $this->sitepress->has_uploaded_media() ) {
			WPML_Media::set_setup_run();
			return;
		}

		$this->exec_add_hooks();
	}

	public function exec_add_hooks() {
		if ( $this->is_wpml_media_screen() ) {
			add_filter( 'wpml_media_menu_overrides', array( $this, 'override_default_menu' ) );
		} else {
			add_action( 'admin_head', array( $this, 'add_top_notice' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_js' ) );
	}

	public function override_default_menu( $menu_elements ) {
		$menu_elements[] = array( $this, 'render_menu' );

		return $menu_elements;
	}

	public function enqueue_js() {
		$wpml_media_url = $this->sitepress->get_wp_api()->constant( 'WPML_MEDIA_URL' );
		wp_enqueue_script( 'wpml-media-setup', $wpml_media_url . '/res/js/wpml-media-posts-media-flag.js', array( 'jquery' ), false, true );
		wp_enqueue_style( 'wpml-media-setup', $wpml_media_url . '/res/css/wpml-media-posts-media-flag.css', array(), false );
	}

	private function is_wpml_media_screen() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		if ( 'wpml-media' === $_GET['page'] ) {
			return true;
		}

		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : '';

		return self::NEW_MEDIA_PAGE === $_GET['page'] && self::NEW_MEDIA_TAB === $tab;
	}

	public function add_top_notice() {

		/* translators: name ot WPML-Media plugin */
		$wpml_media = '<strong>' . __( 'WPML Media Translation', 'wpml-media' ) . '</strong>';

		/* translators: The word that becomes the link inside the notice "The WPML Media Translation setup is almost complete. Click here to finish the setup." Translate it as it should read inside that sentence. */
		$here_text = _x( 'here', 'Used to build a link in the "Click here to finish the setup"', 'wpml-media' );
		$here_url  = admin_url( 'admin.php?page=' . self::NEW_MEDIA_PAGE . '&tab=' . self::NEW_MEDIA_TAB );
		$here_link = '<a href="' . esc_url( $here_url ) . '">' . $here_text . '</a>';

		$text = vsprintf(
			/* translators: Notice on the WPML screens asking the user to finish the media setup. %1$s: the name of the plugin, WPML Media Translation, in bold. %2$s: the word "here", already turned into a link. */
			esc_html__( 'The %1$s setup is almost complete. Click %2$s to finish the setup.', 'wpml-media' ),
			array(
				$wpml_media,
				$here_link
			)
		);

		$notice = new WPML_Notice( self::NOTICE_ID, $text, self::NOTICE_GROUP );
		$notice->set_css_class_types( 'notice-warning' );
		$notice->set_hideable( false );
		$notice->set_dismissible( false );
		$notice->set_collapsable( false );
		$notice->add_exclude_from_page( 'wpml-media' );
		$notice->add_display_callback( array( __CLASS__, 'should_display_top_notice' ) );
		$notice->add_capability_check( array( 'manage_options' ) );
		$wpml_admin_notices = wpml_get_admin_notices();
		$wpml_admin_notices->add_notice( $notice );

	}

	public static function should_display_top_notice() {
		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
		$tab  = isset( $_GET['tab'] ) ? $_GET['tab'] : '';

		return ! ( self::NEW_MEDIA_PAGE === $page && self::NEW_MEDIA_TAB === $tab );
	}

	public function render_menu() {
		?>
		<div class="wrap wpml-media-setup">
			<?php /* translators: Heading of the notice on the Media tab that asks the user to run the one-off media setup. */ ?>
			<h2><?php esc_html_e( 'Setup required', 'wpml-media' ) ?></h2>
			<?php
?>
			<div
				id="wpml-media-posts-media-flag"
				class="notice notice-warning inline"
				style="padding-bottom:8px"

				data-prepare-action="<?php echo esc_attr( self::PREPARE_ACTION ); ?>"
				data-prepare-nonce="<?php echo wp_create_nonce( self::PREPARE_ACTION ); ?>"

				data-process-action="<?php echo esc_attr( self::PROCESS_ACTION ); ?>"
				data-process-nonce="<?php echo wp_create_nonce( self::PROCESS_ACTION ); ?>"

			>
				<p>
					<?php
					echo wpml_bold_names(
						__( 'In order to get WPML Media Translation fully working, you need to run this set up which takes only a few moments depending on the total number of posts in your WordPress install.', 'wpml-media' )
					);
					?>
				</p>
				<div class="wpml-media-setup-actions">
					<button type="button" class="button-primary wpml-button base-btn">
						<span class="spinner"></span>
						<span class="button-text"><?php /* translators: Button label under the "Setup required" heading on the Media tab: it starts the one-off media setup. Verb, imperative. */ esc_html_e( 'Finish setup', 'wpml-media' ); ?></span>
					</button>
					<span class="status description"></span>
				</div>
			</div>
		</div>
		<?php
	}

}
