<?php

namespace WPML\EditHistory;

use WPML\Request\Adapter\AdminPost;
use WPML\Request\Policy\Authenticity;
use WPML\Request\Policy\Policy;
use WPML\SuperGlobals\Request;

class PanelHooks implements \IWPML_Backend_Action {

	const PAGE   = 'wpml-translation-updates';
	const ACTION = 'wpml_edit_history_revert';
	const RESULT = 'wpml_revert';

	const MENU_ORDER = 450;

	const CAPABILITY = 'edit_posts';

	private $items;

	private $revert;

	private $page;

	public function __construct( PanelItems $items, Revert $revert, PanelPage $page ) {
		$this->items  = $items;
		$this->revert = $revert;
		$this->page   = $page;
	}

	public function add_hooks() {
		add_action( 'wpml_admin_menu_configure', [ $this, 'registerMenu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueStyles' ] );
		AdminPost::register( self::ACTION, self::policy(), [ $this, 'onRevert' ] );
	}

	public function registerMenu( $menuId ) {
		if ( 'WPML' !== $menuId ) {
			return;
		}

		/* translators: Menu item in the WPML menu, and the heading of its screen: the history of the updates applied to translations the client had edited. */
		$title = __( 'Translation updates', 'sitepress' );

		do_action(
			'wpml_admin_menu_register_item',
			[
				'order'      => self::MENU_ORDER,
				'page_title' => $title,
				'menu_title' => $title,
				'capability' => self::CAPABILITY,
				'menu_slug'  => self::PAGE,
				'function'   => [ $this, 'render' ],
			]
		);
	}

	public function enqueueStyles() {
		if ( self::PAGE === Request::page() ) {
			wp_enqueue_style( 'wpml-tm-styles', WPML_TM_URL . '/res/css/style.css', [], ICL_SITEPRESS_SCRIPT_VERSION );
		}
	}

	public function render() {
		$this->page->render( $this->items->forCurrentUser(), Request::param( self::RESULT ) );
	}

	public function onRevert() {
		wp_safe_redirect( $this->revert() );
		exit;
	}

	public function revert(): string {
		check_admin_referer( self::ACTION );
		$translationId = isset( $_POST['translation'] ) ? absint( wp_unslash( $_POST['translation'] ) ) : 0;
		$version       = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';

		if ( ! $translationId || ! current_user_can( 'edit_post', $translationId ) ) {
			/* translators: Error page shown when a user tries to revert a translation they may not edit. */
			wp_die( esc_html__( 'You are not allowed to change this translation.', 'sitepress' ), 403 );
		}

		return add_query_arg(
			[
				'page'       => self::PAGE,
				self::RESULT => $this->revert->run( $translationId, $version ),
			],
			admin_url( 'admin.php' )
		);
	}

	public static function policy(): Policy {
		return Policy::capability( [ self::CAPABILITY ], Authenticity::actionNonce( self::ACTION, '_wpnonce' ) );
	}
}
