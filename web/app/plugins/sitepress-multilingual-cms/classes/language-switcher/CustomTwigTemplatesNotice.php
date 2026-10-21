<?php

namespace WPML\LanguageSwitcher;

use WPML\OutboundLinks\OutboundLinks;
use WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl;

class CustomTwigTemplatesNotice {

	const NOTICE_ID    = 'wpml-ls-custom-twig-templates';
	const NOTICE_GROUP = 'wpml-language-switcher';

	private $settings;

	private $templates;

	private $notices;

	public function __construct( \WPML_LS_Settings $settings, \WPML_LS_Templates $templates, ?\WPML_Notices $notices = null ) {
		$this->settings  = $settings;
		$this->templates = $templates;
		$this->notices   = $notices;
	}

	private function notices() {
		if ( ! $this->notices ) {
			$this->notices = wpml_get_admin_notices();
		}

		return $this->notices;
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'refresh' ] );
	}

	public function refresh() {
		if ( wp_doing_ajax() || ! $this->current_user_manages_wpml() ) {
			return;
		}

		$templates = $this->get_twig_templates();

		if ( $templates ) {
			$this->notices()->add_notice( $this->create_notice( $templates ) );
		} elseif ( $this->notices()->get_notice( self::NOTICE_ID, self::NOTICE_GROUP ) ) {
			$this->notices()->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
		}
	}

	private function current_user_manages_wpml() {
		return current_user_can( 'manage_options' ) || current_user_can( 'wpml_manage_languages' );
	}

	public function get_twig_templates() {
		$registered = $this->templates->get_templates();
		$twig       = [];

		foreach ( is_array( $registered ) ? $registered : [] as $slug => $template ) {
			if ( $template->is_missing() || $template->is_core() || \WPML_LS_Template::ENGINE_TWIG !== $template->get_engine() ) {
				continue;
			}

			$data         = $template->get_template_data();
			$twig[ $slug ] = [
				'name'       => isset( $data['name'] ) ? (string) $data['name'] : (string) $slug,
				'folder'     => $this->get_folder( $data ),
				'in_uploads' => isset( $data['php_allowed'] ) && ! $data['php_allowed'],
				'used_by'    => [],
			];
		}

		if ( ! $twig ) {
			return [];
		}

		foreach ( $this->settings->get_active_slots() as $slot ) {
			if ( ! $slot->template_string() && isset( $twig[ $slot->template() ] ) ) {
				$twig[ $slot->template() ]['used_by'][] = $this->get_slot_label( $slot );
			}
		}

		ksort( $twig );

		return array_values( $twig );
	}

	private function get_slot_label( $slot ) {
		if ( $slot->is_footer() ) {
			/* translators: Name of a language switcher in a list: the one shown in the footer of the site. */
			return __( 'Footer language switcher', 'sitepress' );
		}

		if ( $slot->is_post_translations() ) {
			/* translators: Name of a language switcher in a list: the links to the other languages of a post, shown above or below its content. */
			return __( 'Post translations links', 'sitepress' );
		}

		if ( $slot->is_shortcode_actions() ) {
			/* translators: Name of a language switcher in a list: the one printed by the language switcher shortcode and the PHP action. */
			return __( 'Shortcode and PHP action language switcher', 'sitepress' );
		}

		if ( $slot->is_sidebar() ) {
			$sidebars = $this->settings->get_registered_sidebars();
			$name     = isset( $sidebars[ $slot->slug() ]['name'] ) ? $sidebars[ $slot->slug() ]['name'] : $slot->slug();

			/* translators: Name of a language switcher in a list: the one in a widget area. %s: the name of the widget area. */
			return sprintf( __( 'Widget area language switcher: %s', 'sitepress' ), $name );
		}

		$menus = $this->settings->get_available_menus();
		$name  = isset( $menus[ $slot->slug() ]->name ) ? $menus[ $slot->slug() ]->name : '#' . $slot->slug();

		/* translators: Name of a language switcher in a list: the one in a menu. %s: the name of the menu. */
		return sprintf( __( 'Menu language switcher: %s', 'sitepress' ), $name );
	}

	private function get_folder( array $template_data ) {
		$paths  = isset( $template_data['path'] ) ? (array) $template_data['path'] : [];
		$folder = (string) end( $paths );
		$root   = defined( 'ABSPATH' ) ? rtrim( ABSPATH, '/\\' ) . '/' : '';

		return '' !== $root && 0 === strpos( $folder, $root ) ? substr( $folder, strlen( $root ) ) : $folder;
	}

	private function create_notice( array $templates ) {
		$notice = $this->notices()->create_notice( self::NOTICE_ID, $this->get_text( $templates ), self::NOTICE_GROUP );
		$notice->set_css_class_types( 'notice-warning' );
		$notice->set_dismissible( true );
		$notice->set_dismissible_for_different_text( false );
		$notice->add_capability_check( [ 'manage_options', 'wpml_manage_languages' ] );
		$notice->set_restrict_to_screen_ids( $this->get_screen_ids() );

		return $notice;
	}

	private function get_screen_ids() {
		$screen_ids = [ 'dashboard', 'themes', WPML_PLUGIN_FOLDER . '/menu/languages' ];

		if ( defined( 'WPML_TM_FOLDER' ) ) {
			$screen_ids[] = 'wpml_page_' . WPML_TM_FOLDER . '/menu/settings';
		}

		return $screen_ids;
	}

	private function get_breakable_path( $folder ) {
		$segments = explode( '/', $folder );
		$last     = count( $segments ) - 1;
		$html     = '';

		foreach ( $segments as $index => $segment ) {
			$html .= '<span style="white-space:nowrap">' . esc_html( $segment ) . ( $index < $last ? '/' : '' ) . '</span>';
		}

		return $html;
	}

	private function get_text( array $templates ) {
		$has_uploads = false;
		$list        = '<ul class="wpml-ls-twig-templates-list" style="max-height:none;overflow:visible">';

		foreach ( $templates as $template ) {
			$list .= '<li><strong>' . esc_html( $template['name'] ) . '</strong> &rarr; <code>' . $this->get_breakable_path( $template['folder'] ) . '</code>';

			if ( $template['in_uploads'] ) {
				$has_uploads = true;
				/* translators: Added after the folder of a custom language switcher template that is in the uploads folder of the site. */
				$list .= ' (' . esc_html__( 'in the uploads folder', 'sitepress' ) . ')';
			}

			if ( $template['used_by'] ) {
				/* translators: Added under a custom language switcher template. %s: the language switchers of the site that use it, separated by commas. */
				$list .= '<br>' . esc_html( sprintf( __( 'Used by: %s', 'sitepress' ), implode( ', ', $template['used_by'] ) ) );
			} else {
				/* translators: Added under a custom language switcher template that none of the switchers set in the language switcher options uses. */
				$list .= '<br>' . esc_html__( 'Not used by a switcher in the language switcher options; it may be used by a shortcode or theme code.', 'sitepress' );
			}

			$list .= '</li>';
		}

		$list .= '</ul>';

		/* translators: Heading of an admin notice about custom language switcher templates. */
		$text  = '<h2>' . esc_html__( 'Action needed for your language switcher', 'sitepress' ) . '</h2>';
		/* translators: First paragraph of an admin notice. "Twig" is the name of a template language and stays in English. The list of the templates follows. */
		$text .= '<p>' . esc_html__( 'These custom language switcher templates are written in Twig. Support for Twig templates will end in a future version of WPML.', 'sitepress' ) . '</p>';
		$text .= $list;
		/* translators: Line that introduces a list of ways to keep the language switcher templates working. */
		$text .= '<p>' . esc_html__( 'To keep them working:', 'sitepress' ) . '</p>';
		$text .= '<ul>';
		$text .= '<li>' . sprintf(
			/* translators: %1$s and %2$s are the opening and closing tags of a link to the documentation. "template.php" and "template.twig" are file names and stay as they are. */
			esc_html__( 'Add a %1$stemplate.php%2$s file next to template.twig in the template folder.', 'sitepress' ),
			'<a href="' . esc_url( $this->get_docs_url() ) . '" target="_blank" rel="noopener noreferrer">',
			'</a>'
		) . '</li>';
		$text .= '<li>' . sprintf(
			/* translators: %1$s and %2$s are the opening and closing tags of a link to the Languages screen, where the language switcher options are. */
			esc_html__( 'Or choose a built-in style in the %1$slanguage switcher options%2$s.', 'sitepress' ),
			'<a href="' . esc_url( $this->get_options_url() ) . '">',
			'</a>'
		) . '</li>';

		if ( $has_uploads ) {
			/* translators: Item of a list of ways to keep the language switchers working. PHP templates are not loaded from the uploads folder. */
			$text .= '<li>' . esc_html__( 'A template in the uploads folder cannot be a PHP template: move it into your theme.', 'sitepress' ) . '</li>';
		}

		$text .= '</ul>';
		$text .= '<p><strong>' . sprintf(
			/* translators: %1$s and %2$s are the opening and closing tags of a link to WPML Support. The sentence is an offer of help. */
			esc_html__( 'Need help? %1$sContact WPML Support%2$s and we\'ll help you convert your template.', 'sitepress' ),
			'<a href="' . esc_url( $this->get_support_url() ) . '" target="_blank" rel="noopener noreferrer">',
			'</a>'
		) . '</strong></p>';

		return $text;
	}

	private function get_options_url() {
		return admin_url( 'admin.php?page=' . \WPML_LS_Admin_UI::get_page_hook() );
	}

	private function get_docs_url() {
		return OutboundLinks::to( 'https://wpml.org/documentation/getting-started-guide/language-switcher-options/custom-language-switcher/', [ 'medium' => 'notice', 'campaign' => 'language-switcher' ] );
	}

	private function get_support_url() {
		return OutboundLinks::to( SupportForumUrl::URL, [ 'medium' => 'notice', 'campaign' => 'support' ] );
	}
}
