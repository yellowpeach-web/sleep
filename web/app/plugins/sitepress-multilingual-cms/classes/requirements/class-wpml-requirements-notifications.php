<?php

use WPML\Setup\Option;

class WPML_Requirements_Notification {
	private $template_service;

	public function __construct( IWPML_Template_Service $template_service ) {
		$this->template_service = $template_service;
	}

	public function get_message( $issues, $limit = 0 ) {
		if ( $issues ) {
			$product_name = $this->get_product_names( $issues );

			if ( 'Elementor' === $product_name ) {
				$requirements = $issues['requirements'];

				if ( 1 === count( $requirements ) && 'WPML String Translation' === $requirements[0]['name'] ) {
					if ( Option::isTMAllowed() ) {
						$strings = $this->get_elementor_message_for_regular_account( $product_name );
					} else {
						$strings = $this->get_elementor_message_for_blog_account( $product_name );
					}

					$issues = [];
				} else {
					$strings = $this->get_default_message( $issues );
				}
			} else {
				$strings = $this->get_default_message( $issues );
			}

			return $this->get_shared_message( $strings, $issues, $limit );
		}

		return null;
	}

	private function get_default_message( $issues ) {
		return [
			/* translators: %s is the product name, */
			'title' => sprintf( __( 'To easily translate %s, you need to add the following WPML components:', 'sitepress' ), $this->get_product_names( $issues ) ),
		];
	}

	private function get_elementor_message_for_blog_account( $product_name ) {
		$message_text_first_part_url = \WPML\OutboundLinks\OutboundLinks::to(
			'https://wpml.org/documentation/plugins-compatibility/elementor/translate-elementor-site-wpml-multilingual-blog/',
			array(
				'medium'   => 'notice',
				'campaign' => 'requirements',
			)
		);
		$message_text_last_part_url  = \WPML\OutboundLinks\OutboundLinks::to(
			'https://app.wpml.org/purchase',
			array(
				'medium'   => 'notice',
				'campaign' => 'account',
			)
		);

		return [
			'title'   => __( 'Your WPML Blog account only allows you to manually translate Elementor content', 'sitepress' ),
			'message' => wpml_bold_names(
				sprintf(
				/* translators: Notice shown when the site's account does not cover the editor being asked for. %1$s and %3$s: the opening and closing tags of a link to a page on wpml.org, %2$s and %5$s: the name of the plugin the content was made with, %4$s: a line break, %6$s and %7$s: the opening and closing tags of a link to the upgrade page. */
					__( '%1$sLearn how to translate %2$s content manually%3$s %4$sAlternatively, to translate %5$s content using the <b>Advanced Translation Editor</b>, automatic translation, professional services, or by other users on your site %6$supgrade to WPML CMS account%7$s.', 'sitepress' ),
					'<a href="' . $message_text_first_part_url . '" target="_blank">',
					$product_name,
					'</a>',
					'<br><br>',
					$product_name,
					'<a href="' . $message_text_last_part_url . '" target="_blank">',
					'</a>'
				),
				array(
					'a'  => array( 'href' => array(), 'target' => array() ),
					'br' => array(),
				)
			),
		];
	}

	private function get_elementor_message_for_regular_account( $product_name ) {
		$title_link_url = admin_url( 'plugin-install.php?tab=commercial' );
		$message_url    = \WPML\OutboundLinks\OutboundLinks::to(
			'https://wpml.org/documentation/plugins-compatibility/elementor/',
			array(
				'medium'   => 'notice',
				'campaign' => 'requirements',
			)
		);

		return [
			'title'   => wpml_bold_names(
				sprintf(
				/* translators: Notice shown when an add-on is missing. %1$s: the name of the plugin the content was made with, %2$s: the opening tag of a link to the installation page, %3$s: its closing tag. */
					__( 'To translate content created with %1$s, you need to %2$sinstall WPML String Translation%3$s', 'sitepress' ),
					$product_name,
					'<a href="' . $title_link_url . '" target="_blank">',
					'</a>'
				),
				array( 'a' => array( 'href' => array(), 'target' => array() ) )
			),
			'message' => sprintf(
			/* translators: Link shown under a notice about a plugin. %1$s: the opening tag of a link to a page on wpml.org, %2$s: the name of that plugin, %3$s: the closing tag of the link. */
				esc_html__( '%1$sLearn how to translate %2$s content%3$s', 'sitepress' ),
				'<a href="' . $message_url . '" target="_blank">',
				$product_name,
				'</a>'
			),
		];
	}

	private function get_shared_message( $strings, $issues, $limit = 0 ) {
		$strings = array_merge(
			array(
				/* translators: Button label: save a file to the computer, the log of one request or a plugin; also the name of a kind of request in the Endpoint column of the job log table. Verb, imperative. */
				'download'   => __( 'Download', 'sitepress' ),
				/* translators: Button label in a notice about a missing add-on: fetch and set it up. Verb, imperative. */
				'install'    => __( 'Install', 'sitepress' ),
				/* translators: Link text that opens the plugins screen so an add-on can be turned on. Verb, imperative. */
				'activate'   => __( 'Activate', 'sitepress' ),
				/* translators: Text on the button of a notice about an add-on while it is being turned on; three dots show that it is still working. */
				'activating' => __( 'Activating...', 'sitepress' ),
				/* translators: Text on the button of a notice about an add-on once it is turned on. Past participle used as a state. */
				'activated'  => __( 'Activated', 'sitepress' ),
				/* translators: Word in front of the details of something that went wrong; a colon and the details follow it. */
				'error'      => __( 'Error', 'sitepress' ),
			),
			$strings
		);

		$model = array(
			'strings' => $strings,
			'shared'  => array(
				'install_link' => get_admin_url( null, 'plugin-install.php?tab=commercial' ),
			),
			'options' => array(
				'limit' => $limit,
			),
			'data'    => $issues,
		);

		return $this->template_service->show( $model, 'plugins-status.twig' );
	}

	public function get_settings( $integrations ) {

		if ( $integrations ) {
			$model = array(
				'strings' => array(
					/* translators: %s will be replaced with a list of plugins or themes. */
					'title'        => sprintf( __( 'One more step before you can translate on %s', 'sitepress' ), $this->build_items_in_sentence( $integrations ) ),
					'message'      => __( "You need to enable WPML's Translation Editor, to translate conveniently.", 'sitepress' ),
					/* translators: Text shown in a notice once the step it offered has been carried through. */
					'enable_done'  => __( 'Done.', 'sitepress' ),
					'enable_error' => __( 'Something went wrong. Please try again or contact the support.', 'sitepress' ),
				),
				'nonces'  => array(
					'enable' => wp_create_nonce( 'wpml_set_translation_editor' ),
				),
			);

			return $this->template_service->show( $model, 'integrations-tm-settings.twig' );
		}

		return null;
	}

	private function get_product_names( $issues ) {
		$products = wp_list_pluck( $issues['causes'], 'name' );

		return $this->build_items_in_sentence( $products );
	}

	private function build_items_in_sentence( $items ) {
		if ( count( $items ) <= 2 ) {
			/* translators: Word that joins the last two names in a list of plugins, as in "WooCommerce and Elementor". It is used on its own between the two names. */
			$product_names = implode( ' ' . _x( 'and', 'Used between elements of a two elements list', 'sitepress' ) . ' ', $items );

			return $product_names;
		}

		$last  = array_slice( $items, - 1 );
		$first = implode( ', ', array_slice( $items, 0, - 1 ) );
		$both  = array_filter( array_merge( array( $first ), $last ), 'strlen' );
		/* translators: Words that join the last name in a list of three or more plugins, as in "WooCommerce, Elementor, and Divi". Keep the comma if your language uses one there. */
		$product_names = implode( _x( ', and', 'Used before the last element of a three or more elements list', 'sitepress' ) . ' ', $both );

		return $product_names;
	}
}
