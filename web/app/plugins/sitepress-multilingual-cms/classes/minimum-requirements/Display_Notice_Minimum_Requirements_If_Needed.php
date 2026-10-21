<?php

namespace WPML\MinimumRequirements;

use IWPML_Backend_Action;
use Throwable;
use WPML\Core\Component\MinimumRequirements\Application\Service\RequirementsService;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\User;
use WPML_Notice;

use function WPML\PHP\Logger\error;

class Display_Notice_Minimum_Requirements_If_Needed implements IWPML_Backend_Action {
	const ALLOWED_SCREENS
		= array(
			'edit-post',
			'term',
			'toplevel_page_tm/menu/main',
			'sitepress-multilingual-cms/menu/languages',
			'sitepress-multilingual-cms/menu/theme-localization',
			'wpml_page_tm/menu/translations-queue',
			'sitepress-multilingual-cms/menu/menu-sync/menus-sync',
			'wpml_page_sitepress-multilingual-cms/menu/taxonomy-translation',
			'wpml_page_tm/menu/settings',
			'wpml-string-translation/menu/string-translation',
			'admin_page_wpml-package-management',
		);

	const NOTICE_GROUP = 'wpml-requirements';
	const NOTICE_ID = 'wpml-requirements-notice';
	const SUCCESS_NOTICE_ID = 'wpml-requirements-met-notice';


	public function __construct() {
	}


	public function add_hooks() {
		Hooks::onAction( 'current_screen' )
		     ->then( [ $this, 'retrieve_invalid_requirements' ] )
		     ->then( [ $this, 'load_statusbar_notification_data' ] )
		     ->then( [ $this, 'display_wordpress_notice_if_needed' ] );
	}


	public function retrieve_invalid_requirements( $screens ) {
		global $wpml_dic;
		try {
			$requirements_service = $wpml_dic->make( RequirementsService::class );

			if ( $this->isSupportPage( $screens[0] ?? null ) ) {
				return [ 'items' => $requirements_service->getInvalidRequirements( false ), 'pending' => false ];
			}

			$invalid_requirements = $requirements_service->getCachedInvalidRequirements();

			return null === $invalid_requirements
				? [ 'items' => [], 'pending' => true ]
				: [ 'items' => $invalid_requirements, 'pending' => false ];
		} catch ( Throwable $e ) {
			error( 'Failed to get InvalidRequirements: ' . $e->getMessage() . ' ' . $e->getTraceAsString() );

			return [ 'items' => [], 'pending' => true ];
		}
	}

	public function display_wordpress_notice_if_needed( $requirements ) {
		try {
			if ( $requirements['pending'] ) {
				return;
			}

			$invalid_requirements = $requirements['items'];
			if ( empty( $invalid_requirements ) ) {
				$isRemoved = $this->remove_requirements_are_not_met_notice_if_exists();
				if ( $isRemoved ) {
					$this->display_requirements_met_success_notice();
				}
			} else {
				$this->display_requirements_are_not_met_notice();
			}
		} catch ( Throwable $e ) {
			error( 'Failed to get InvalidRequirements: ' . $e->getMessage() . ' ' . $e->getTraceAsString() );
		}
	}

	public function load_statusbar_notification_data( $requirements ) {
		$data = [
			'supportPageUrl' => admin_url( 'admin.php?page=sitepress-multilingual-cms/menu/support.php' ),
			'items'          => $requirements['items'],
			'pending'        => $requirements['pending'],
		];

		if ( $requirements['pending'] ) {
			$data['refreshEndpoint'] = [
				'url'   => get_rest_url( null, 'wpml/v1/minimum-requirements' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			];
		}

		wp_localize_script(
			'wpml-ate-jobs-sync-ui',
			'wpmlMinimumRequirements',
			$data
		);

		return $requirements;
	}


	private function display_requirements_are_not_met_notice() {
		$admin_notices = wpml_get_admin_notices();

		$notice = new WPML_Notice(
			self::NOTICE_ID,
			$this->get_requirements_are_not_met_text(),
			self::NOTICE_GROUP
		);

		$notice->set_css_class_types( [ 'warning' ] );
		$notice->add_capability_check( [ 'manage_options' ] );
		$notice->set_dismissible( true );

		$notice->add_user_restriction( User::getCurrentId() );
		$notice->set_restrict_to_screen_ids( self::ALLOWED_SCREENS );

		$admin_notices->add_notice( $notice );
	}

	private function get_requirements_are_not_met_text() {
		$support_url = esc_url( admin_url( 'admin.php?page=sitepress-multilingual-cms/menu/support.php' ) );
		$message     = __( 'Your site doesn\'t meet WPML\'s minimum requirements.', 'sitepress' );
		/* translators: Button label in a notice about something WPML needs: put it right straight away. Verb phrase, imperative. */
		$button_text = __( 'Fix now', 'sitepress' );

		$notice_text = <<<HTML
<div class="wpml-minimum-requirements-notice" data-testid="wpml-minimum-requirements-notice">
    <span>{$message}</span>
    <a href="{$support_url}" class="wpml-button base-btn gray-dark-btn">{$button_text}
    </a>
</div>
HTML;

		return $notice_text;
	}

	private function get_all_requirements_are_met_notice() {
		$message = __( 'Great! All WPML requirements are now met. Your site is ready to use WPML.', 'sitepress' );

		$notice_text = <<<HTML
<div style="display: flex; align-items: center;min-height:32px;" data-testid="wpml-minimum-requirements-are-met">
    <span>{$message}</span>
</div>
HTML;

		return $notice_text;
	}

	private function display_requirements_met_success_notice() {
		$admin_notices = wpml_get_admin_notices();

		$notice = new WPML_Notice(
			self::SUCCESS_NOTICE_ID,
			$this->get_all_requirements_are_met_notice(),
			self::NOTICE_GROUP
		);

		$notice->set_css_class_types( [ 'success' ] );
		$notice->add_capability_check( [ 'manage_options' ] );
		$notice->set_dismissible( true );
		$notice->add_user_restriction( User::getCurrentId() );
		$notice->set_flash( true );

		$admin_notices->add_notice( $notice );
	}

	private function remove_requirements_are_not_met_notice_if_exists() {
		$service = wpml_get_admin_notices();

		$notice = $service->get_notice( self::NOTICE_ID, self::NOTICE_GROUP );
		if ( ! $notice ) {
			return false;
		}

		$service->undismiss_notice( $notice );
		$service->remove_notice( $notice->get_group(), $notice->get_id() );

		return true;
	}

	private function isSupportPage( $screen ): bool {
		if ( ! is_object( $screen ) || ! isset( $screen->id ) || ! is_string( $screen->id ) ) {
			return false;
		}

		$slug = WPML_PLUGIN_FOLDER . '/menu/support';

		return $screen->id === $slug || substr( $screen->id, - strlen( '_' . $slug ) ) === '_' . $slug;
	}
}
