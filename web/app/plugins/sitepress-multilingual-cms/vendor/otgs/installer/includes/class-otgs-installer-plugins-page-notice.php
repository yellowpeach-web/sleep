<?php

use OTGS\Installer\AdminNotices\Notices\ToolsetTexts;
use OTGS\Installer\AdminNotices\Notices\WPMLTexts;

class OTGS_Installer_Plugins_Page_Notice {

	const TEMPLATE = 'plugins-page';
	const DISPLAY_SUBSCRIPTION_NOTICE_KEY = 'display_subscription_notice';
	const DISPLAY_SETTING_NOTICE_KEY = 'display_setting_notice';

	private $plugins = array();

	private $template_service;

	public function __construct( OTGS_Template_Service $template_service ) {
		$this->template_service = $template_service;
	}

	public function add_hooks() {
		foreach ( $this->get_plugins() as $plugin_id => $plugin_data ) {
			add_action( 'after_plugin_row_' . $plugin_id, array(
				$this,
				'show_purchase_notice_under_plugin'
			), 10, 2 );
		}
	}

	public function get_plugins() {
		return $this->plugins;
	}

	public function add_plugin( $plugin_id, $plugin_data ) {
		$this->plugins[ $plugin_id ] = $plugin_data;
	}

	public function show_purchase_notice_under_plugin( $plugin_file, $plugin_data ) {
		$display_subscription_notice = isset( $this->plugins[ $plugin_file ][ self::DISPLAY_SUBSCRIPTION_NOTICE_KEY ] )
			? $this->plugins[ $plugin_file ][ self::DISPLAY_SUBSCRIPTION_NOTICE_KEY ]
			: false;

		if ( $display_subscription_notice ) {
			echo $this->template_service->show(
				$this->get_model( $display_subscription_notice ),
				self::TEMPLATE
			);
		}
	}

	private function get_model( $notice ) {
		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );

		list( $tr_classes, $notice_classes ) = $this->get_classes();

		if ( is_multisite() ) {
			if ( is_network_admin() ) {
				$menu_url = network_admin_url( 'plugin-install.php?tab=commercial' );
			} else {
				$menu_url = admin_url( 'options-general.php?page=installer' );
			}
		} else {
			$menu_url = admin_url( 'plugin-install.php?tab=commercial' );
		}

		$menu_url .= '&repository=' . $notice['repo'];
		$menu_url_with_action = $menu_url . '&action=' . $notice['type'];

		$texts = 'toolset' === $notice['repo'] ? ToolsetTexts::class : WPMLTexts::class;

		switch ( $notice['type'] ) {
			case 'expired':
				$message = $this->prepareMessage(
					__( 'Your %s account has expired. %sPurchase today%s to protect your site from breaking changes in future WordPress releases.', 'installer' ),
					$notice['product'],
					$texts::getPurchaseUrl()
				);
				break;

			case 'in_grace':
				$message = $this->prepareMessage(
					__( 'Your %s account has expired. %sRenew today%s to protect your site from breaking changes in future WordPress releases.', 'installer' ),
					$notice['product'],
					$texts::getRenewUrl()
				);
				break;

			case 'refunded':
				$message = $this->prepareMessage(
					__( 'Remember to remove %s from this website. %sCheck my order status%s', 'installer' ),
					$notice['product'],
					$menu_url_with_action
				);

				$notice_classes .= ' notice-otgs-refund';
				break;

			case 'legacy_free':
				$message = sprintf(
					__( 'You have an old, free subscription for Toolset Types which doesn\'t provide automatic updates. %sUpgrade your account%s', 'installer' ),
							
					'<a href="' . $menu_url . '">',
					'</a>'
				);

				break;

			default:
				$message = $this->prepareMessage(
					__( 'You are using an unregistered version of %s and are not receiving compatibility and security updates. %sRegister now%s', 'installer' ),
					$notice['product'],
					$menu_url_with_action
				);
				break;
		}

		return array(
			'strings'   => array(
				'valid_subscription' => $this->prepareMessage($message, $notice['product'], $menu_url_with_action),
			),
			'css'       => array(
				'tr_classes'     => $tr_classes,
				'notice_classes' => $notice_classes,
			),
			'col_count' => $wp_list_table->get_column_count(),
		);
	}

	private function get_classes() {
		$tr_classes     = 'plugin-update-tr';
		$notice_classes = 'update-message installer-q-icon';

		if ( version_compare( get_bloginfo( 'version' ), '4.6', '>=' ) ) {
			$tr_classes     = 'plugin-update-tr installer-plugin-update-tr js-otgs-plugin-tr';
			$notice_classes = 'update-message notice inline notice-otgs';
		}

		return array( $tr_classes, $notice_classes );
	}

    private function prepareMessage($message, $notice, $menu_url) {
        return sprintf($message, $notice, '<a href="' . $menu_url . '">', '</a>');
    }
}
