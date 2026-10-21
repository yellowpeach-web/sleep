<?php

namespace WPML\Notices;

use WPML\LIB\WP\User;

class RootPageNotice {

	const NOTICE_ID    = 'wpml-root-page-unusable';
	const NOTICE_GROUP = 'wpml-core';

	const LEGACY_NOTICE_ID = 'wpml-root-page-html-file-invalid';

	const OPTION_KEY = 'wpml_root_html_file_invalid';

	const DELETED_NOTICE_ID = 'wpml-root-page-deleted';

	const SETTINGS_PAGE    = 'tm/menu/settings';
	const SETTINGS_SECTION = 'urls-and-seo';

	private $admin_notices;

	public function __construct( \WPML_Notices $admin_notices ) {
		$this->admin_notices = $admin_notices;
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'maybe_register_notice' ] );
	}

	public function maybe_register_notice() {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}

		$this->maybe_register_deleted_notice();

		$problem = $this->diagnose();

		if ( ! $problem ) {
			$this->remove();
			return;
		}

		$this->admin_notices->add_notice( $this->build_notice( $problem[0], $problem[1] ), true );
	}

	private function diagnose() {
		if ( \WPML_Root_Page::uses_html_root() ) {
			return $this->diagnose_html_file();
		}

		if ( \WPML_Root_Page::uses_page_root() ) {
			return $this->diagnose_page();
		}

		return null;
	}

	private function diagnose_html_file() {
		$urls     = icl_get_setting( 'urls' );
		$raw_path = isset( $urls['root_html_file_path'] ) ? (string) $urls['root_html_file_path'] : '';
		$result   = \WPML\UrlHandling\RootPage\HtmlFile::resolve( $raw_path );

		if ( ! empty( $result['valid'] ) ) {
			return null;
		}

		$fix = sprintf(
			/* translators: %s: a link, already wrapped in its tags, whose text is the menu path "WPML > Settings > URLs and SEO". */
			esc_html_x( 'Set a valid root file in %s. Allowed types are .html, .htm and .php.', 'Set a valid root file in {WPML > Settings > URLs and SEO} - 1/2', 'sitepress' ),
			$this->settings_link()
		);

		if ( '' === $raw_path ) {
			return [ esc_html__( 'No root page file is set.', 'sitepress' ), $fix ];
		}

		return [
			sprintf(
				/* translators: %s: the file path the admin originally entered */
				esc_html__( 'WPML could not load the root page file "%s".', 'sitepress' ),
				esc_html( $raw_path )
			),
			$fix,
		];
	}

	private function diagnose_page() {
		$urls         = icl_get_setting( 'urls' );
		$root_page_id = isset( $urls['root_page'] ) ? (int) $urls['root_page'] : 0;
		$root_page    = $root_page_id > 0 ? get_post( $root_page_id ) : null;

		if ( \WPML_Root_Page_Actions::is_usable_root_page( $root_page ) ) {
			return null;
		}

		if ( ! $root_page ) {
			return [
				esc_html__( 'No root page is set.', 'sitepress' ),
				sprintf(
					/* translators: %s: a link, already wrapped in its tags, whose text is the menu path "WPML > Settings > URLs and SEO". */
					esc_html_x( 'Create one in %s.', 'Create one in {WPML > Settings > URLs and SEO} - 1/2', 'sitepress' ),
					$this->settings_link()
				),
			];
		}

		if ( 'trash' === $root_page->post_status ) {
			if ( ! current_user_can( 'delete_post', $root_page_id ) ) {
				return [
					esc_html__( 'The root page is in the Trash.', 'sitepress' ),
					esc_html__( 'Ask an administrator to restore it, then publish it.', 'sitepress' ),
				];
			}

			$restore_url  = wp_nonce_url( admin_url( 'post.php?post=' . $root_page_id . '&action=untrash' ), 'untrash-post_' . $root_page_id );
			$restore_link = '<a href="' . esc_url( $restore_url ) . '">'
				/* translators: Link text inside the sentence "Restore {the root page}, then publish it." It restores the page from the Trash. */
				. esc_html_x( 'the root page', 'Restore {the root page}, then publish it - 2/2', 'sitepress' )
				. '</a>';

			return [
				esc_html__( 'The root page is in the Trash.', 'sitepress' ),
				sprintf(
					/* translators: %s: a link, already wrapped in its tags, whose text is "the root page". */
					esc_html_x( 'Restore %s, then publish it.', 'Restore {the root page}, then publish it - 1/2', 'sitepress' ),
					$restore_link
				),
			];
		}

		$page_link = '<a href="' . esc_url( (string) get_edit_post_link( $root_page_id ) ) . '">'
			/* translators: Link text inside the sentence "Publish {the root page} to show it at your site's root." It opens that page in the editor. */
			. esc_html_x( 'the root page', 'Publish {the root page} to show it at your site root - 2/2', 'sitepress' )
			. '</a>';

		return [
			esc_html__( 'The root page is not published.', 'sitepress' ),
			sprintf(
				/* translators: %s: a link, already wrapped in its tags, whose text is "the root page". */
				esc_html_x( 'Publish %s to show it at your site\'s root.', 'Publish {the root page} to show it at your site root - 1/2', 'sitepress' ),
				$page_link
			),
		];
	}

	private function settings_link() {
		$settings_url = admin_url( 'admin.php?page=' . self::SETTINGS_PAGE . '&section=' . self::SETTINGS_SECTION );

		return '<a href="' . esc_url( $settings_url ) . '">'
			/* translators: Link text inside the root page notices, e.g. "Create one in {WPML > Settings > URLs and SEO}." It is the menu path to that settings section and opens it. */
			. esc_html_x( 'WPML > Settings > URLs and SEO', 'Menu path linked inside the root page notices - 2/2', 'sitepress' )
			. '</a>';
	}

	private function build_notice( $fact, $fix ) {
		$text = '<p>' . $fact . '</p><p>'
			. esc_html__( 'Visitors see your normal front page instead.', 'sitepress' )
			. ' ' . $fix
			. '</p>';

		$notice = $this->admin_notices->create_notice( self::NOTICE_ID, $text, self::NOTICE_GROUP );
		$notice->set_css_class_types( 'warning' );
		$notice->set_dismissible( false );

		return $notice;
	}

	private function maybe_register_deleted_notice() {
		$flag = get_option( \WPML_Root_Page_Actions::DELETED_ROOT_FLAG );

		if ( ! $flag ) {
			return;
		}

		if ( ! current_user_can( User::CAP_MANAGE_TRANSLATIONS ) ) {
			return;
		}

		$notice_id = $this->deleted_notice_id( $flag );
		$notice    = $this->build_deleted_notice( $notice_id );

		$root_chosen_again = \WPML_Root_Page::uses_html_root() || \WPML_Root_Page::uses_page_root();

		if ( $root_chosen_again || $this->admin_notices->is_notice_dismissed( $notice ) ) {
			$this->admin_notices->remove_notice( self::NOTICE_GROUP, $notice_id );
			delete_option( \WPML_Root_Page_Actions::DELETED_ROOT_FLAG );

			return;
		}

		$this->admin_notices->add_notice( $notice );
	}

	private function deleted_notice_id( $flag ) {
		$page_id = is_array( $flag ) && isset( $flag['id'] ) ? (int) $flag['id'] : 0;
		$time    = is_array( $flag ) && isset( $flag['at'] ) ? (int) $flag['at'] : 0;

		return self::DELETED_NOTICE_ID . '-' . $page_id . '-' . $time;
	}

	private function build_deleted_notice( $notice_id ) {
		$text = '<p>' . esc_html__( 'The page set as your site root was deleted permanently, so WPML no longer shows a root page.', 'sitepress' )
			. ' ' . esc_html__( 'Visitors see your normal front page instead.', 'sitepress' )
			. ' ' . sprintf(
				/* translators: %s: a link, already wrapped in its tags, whose text is the menu path "WPML > Settings > URLs and SEO". "HTML file" and "A page" are the labels of the two root options in that section; use the same translations. */
				esc_html_x( 'To show something at your site root again, choose "HTML file" or "A page" in %s. For a page, create a new root page there.', 'Choose "HTML file" or "A page" in {WPML > Settings > URLs and SEO} - 1/2', 'sitepress' ),
				$this->settings_link()
			) . '</p>';

		$notice = $this->admin_notices->create_notice( $notice_id, $text, self::NOTICE_GROUP );
		$notice->set_css_class_types( 'warning' );
		$notice->set_dismissible( true );

		return $notice;
	}

	private function remove() {
		$this->admin_notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
		$this->admin_notices->remove_notice( self::NOTICE_GROUP, self::LEGACY_NOTICE_ID );
	}

	public function clear() {
		delete_option( self::OPTION_KEY );
		$this->remove();
	}
}
