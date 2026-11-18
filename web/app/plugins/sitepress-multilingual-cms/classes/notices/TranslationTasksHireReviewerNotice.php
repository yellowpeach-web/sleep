<?php

namespace WPML\Notices;

use WPML\OutboundLinks\OutboundLinks;
use WPML\SuperGlobals\Request;
use WPML\TM\Menu\TranslationQueue\TranslationQueuePage;

class TranslationTasksHireReviewerNotice {

	const NOTICE_ID    = 'wpml-translation-tasks-hire-reviewer';
	const NOTICE_GROUP = RootPageNotice::NOTICE_GROUP;

	const PAGE = TranslationQueuePage::SLUG;

	const REVIEWERS_URL = 'https://wpml.org/documentation/reviewers/';

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

		if ( self::PAGE !== Request::page() ) {
			return;
		}

		$this->admin_notices->add_notice( $this->build_notice(), true );
	}

	public static function is_translation_tasks_tab() {
		return TranslationQueuePage::TAB === Request::param( 'tab' );
	}

	private function build_notice() {
		$url = OutboundLinks::to(
			self::REVIEWERS_URL,
			[
				'medium'   => 'notice',
				'campaign' => 'translation-roles',
			]
		);

		$text = sprintf(
			/* translators: Line in the translators section. %1$s: opening link tag, %2$s: closing link tag; the words between them become the link. */
			__( 'You can %1$shire an experienced translator%2$s to review your automatic translations for perfect quality.', 'sitepress' ),
			'<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">',
			'</a>'
		);

		$notice = $this->admin_notices->create_notice( self::NOTICE_ID, $text, self::NOTICE_GROUP );
		$notice->set_css_class_types( [ 'info' ] );
		$notice->set_dismissible( true );
		$notice->add_capability_check( [ 'manage_translations' ] );
		$notice->set_restrict_to_pages( [ self::PAGE, TranslationQueuePage::LEGACY_SLUG ] );
		$notice->add_display_callback( [ self::class, 'is_translation_tasks_tab' ] );

		return $notice;
	}
}
