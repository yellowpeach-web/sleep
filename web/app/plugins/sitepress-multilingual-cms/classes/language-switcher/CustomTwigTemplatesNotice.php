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
		$uploads   = $this->get_uploads_templates();

		if ( $templates || $uploads ) {
			$this->notices()->add_notice( $this->create_notice( $templates, $uploads ) );
		} elseif ( $this->notices()->get_notice( self::NOTICE_ID, self::NOTICE_GROUP ) ) {
			$this->notices()->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
		}
	}

	private function current_user_manages_wpml() {
		return current_user_can( 'manage_options' ) || current_user_can( 'wpml_manage_languages' );
	}

	public function get_twig_templates() {
		return ( new CustomTemplates( $this->settings, $this->templates ) )->get( \WPML_LS_Template::ENGINE_TWIG );
	}

	public function get_uploads_templates() {
		return ( new CustomTemplates( $this->settings, $this->templates ) )->getUploads();
	}

	private function create_notice( array $templates, array $uploads ) {
		$notice = $this->notices()->create_notice( self::NOTICE_ID, $this->get_text( $templates, $uploads ), self::NOTICE_GROUP );
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

	private function get_text( array $templates, array $uploads ) {
		/* translators: Heading of an admin notice about custom language switcher templates. */
		$text = '<h2>' . esc_html__( 'Action needed for your language switcher', 'sitepress' ) . '</h2>';

		if ( $templates ) {
			$text .= $this->get_twig_part( $templates );
		}

		if ( $uploads ) {
			$text .= $this->get_uploads_part( $uploads );
		}

		$text .= '<p><strong>' . sprintf(
			/* translators: %1$s and %2$s are the opening and closing tags of a link to WPML Support. The sentence is an offer of help. */
			esc_html__( 'Need help? %1$sContact WPML Support%2$s and we\'ll help you convert your template.', 'sitepress' ),
			'<a href="' . esc_url( $this->get_support_url() ) . '" target="_blank" rel="noopener noreferrer">',
			'</a>'
		) . '</strong></p>';

		return $text;
	}

	private function get_twig_part( array $templates ) {
		/* translators: First paragraph of an admin notice. "Twig" is the name of a template language and stays in English. The list of the templates follows. */
		$text  = '<p>' . esc_html__( 'These custom language switcher templates are written in Twig. Support for Twig templates will end in a future version of WPML.', 'sitepress' ) . '</p>';
		$text .= $this->get_list( $templates );
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
		$text .= '</ul>';

		return $text;
	}

	private function get_uploads_part( array $uploads ) {
		/* translators: Paragraph of an admin notice, before a list of custom language switcher templates that are in the uploads folder of the site. */
		$text  = '<p>' . esc_html__( 'These language switcher templates are in the uploads folder. WPML does not run templates from a folder that uploads and other plugins can write to, so a switcher that uses one shows the default style of its type instead.', 'sitepress' ) . '</p>';
		$text .= $this->get_list( $uploads, true );
		$text .= '<p>' . sprintf(
			/* translators: %1$s is a folder path in the theme. %2$s and %3$s are the opening and closing tags of a link to the documentation. */
			esc_html__( 'Move each template into your theme, to %1$s. The switchers that use it then use the moved template. %2$sLearn more%3$s', 'sitepress' ),
			'<code>' . $this->get_breakable_path( $this->get_theme_folder() ) . '</code>',
			'<a href="' . esc_url( $this->get_docs_url() ) . '" target="_blank" rel="noopener noreferrer">',
			'</a>'
		) . '</p>';

		return $text;
	}

	private function get_list( array $templates, $not_used = false ) {
		$list = '<ul class="wpml-ls-twig-templates-list" style="max-height:none;overflow:visible">';

		foreach ( $templates as $template ) {
			$list .= '<li><strong>' . esc_html( $template['name'] ) . '</strong> &rarr; <code>' . $this->get_breakable_path( $template['folder'] ) . '</code>';

			if ( $not_used ) {
				/* translators: Added to the name or the folder of a custom language switcher template that is in the uploads folder of the site, which WPML does not use, in the language switcher settings. */
				$list .= ' (' . esc_html__( 'not used any more: move it into your theme', 'sitepress' ) . ')';
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

		return $list . '</ul>';
	}

	private function get_theme_folder() {
		$folder = get_stylesheet_directory() . '/wpml/templates/language-switchers/';
		$root   = defined( 'ABSPATH' ) ? rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) . '/' : '';
		$folder = str_replace( '\\', '/', $folder );

		return '' !== $root && 0 === strpos( $folder, $root ) ? substr( $folder, strlen( $root ) ) : $folder;
	}

	private function get_options_url() {
		return admin_url( 'admin.php?page=' . \WPML_LS_Admin_UI::get_page_hook() );
	}

	private function get_docs_url() {
		return OutboundLinks::to( 'https://wpml.org/documentation/getting-started-guide/language-switcher-options/custom-language-switcher/#custom-language-switcher-templates', [ 'medium' => 'notice', 'campaign' => 'language-switcher' ] );
	}

	private function get_support_url() {
		return OutboundLinks::to( SupportForumUrl::URL, [ 'medium' => 'notice', 'campaign' => 'support' ] );
	}
}
