<?php

namespace WPML\Setup;

use WPML\OutboundLinks\OutboundLinks;
use WPML\SuperGlobals\Request;

class PreSetupPages implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const SETUP_PAGE = WPML_PLUGIN_FOLDER . '/menu/setup.php';

	const GETTING_STARTED_URL = 'https://wpml.org/documentation/getting-started-guide/';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'admin_menu', array( $this, 'register' ), 999 );
	}

	public function pages() {
		return array(
			'tm/menu/main.php' => array(
				/* translators: Name of the screen where content is sent for translation and translations are managed: in the WPML menu, as the title of that screen, and as the text of links that open it. Plural noun. */
				'title'      => __( 'Translations', 'sitepress' ),
				'capability' => 'translate',
			),
			'tm/menu/settings' => array(
				/* translators: Title of the settings screen, and the item in the WPML menu that opens it. */
				'title'      => __( 'Settings', 'sitepress' ),
				'capability' => 'manage_translations',
			),
		);
	}

	public function register() {
		if ( $this->sitepress->is_setup_complete() ) {
			return;
		}

		foreach ( $this->pages() as $slug => $page ) {
			if ( $this->is_registered( $slug ) ) {
				continue;
			}

			$hook = add_submenu_page(
				\WPML_Hide_Legacy_Top_Level_Menu::HIDDEN_PARENT_SLUG,
				$page['title'],
				$page['title'],
				$page['capability'],
				$slug,
				array( $this, 'render' )
			);

			if ( $hook ) {
				add_action( 'load-' . $hook, array( $this, 'drop_duplicate_offer' ) );
			}
		}
	}

	public function drop_duplicate_offer() {
		remove_action( 'admin_notices', array( $this->sitepress, 'help_admin_notice' ) );
	}

	public function render() {
		$pages = $this->pages();
		$page  = Request::page();
		/* translators: The name of the plugin, used as the title of its screens and of its menu. It is a product name and stays as it is. */
		$title = isset( $pages[ $page ] ) ? $pages[ $page ]['title'] : __( 'WPML', 'sitepress' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<p><?php esc_html_e( 'You need to configure WPML before you can start translating.', 'sitepress' ); ?></p>
			<p>
				<a class="button-primary configure-wpml" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::SETUP_PAGE ) ); ?>">
					<?php /* translators: Heading of the screen where WPML is set up, and the text of the link that opens it. Verb phrase, imperative. */ esc_html_e( 'Configure WPML', 'sitepress' ); ?>
				</a>
				&nbsp;
				<a href="<?php echo esc_url( $this->getting_started_url() ); ?>" target="_blank">
					<?php esc_html_e( 'Getting started guide', 'sitepress' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	private function is_registered( $slug ) {
		foreach ( array( 'menu', 'submenu' ) as $global ) {
			if ( ! isset( $GLOBALS[ $global ] ) || ! is_array( $GLOBALS[ $global ] ) ) {
				continue;
			}

			$groups = 'menu' === $global ? array( $GLOBALS[ $global ] ) : $GLOBALS[ $global ];

			foreach ( (array) $groups as $items ) {
				foreach ( (array) $items as $item ) {
					if ( is_array( $item ) && isset( $item[2] ) && $slug === $item[2] ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	private function getting_started_url() {
		return OutboundLinks::to(
			self::GETTING_STARTED_URL,
			array(
				'medium'   => 'support',
				'campaign' => 'getting-started',
			)
		);
	}
}
