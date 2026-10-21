<?php

namespace WPML\TM\Menu\TranslationServices;

class ActiveServiceTemplate {

	const ACTIVE_SERVICE_TEMPLATE = 'active-service.twig';
	const HOURS_BEFORE_TS_REFRESH = 24;

	public static function render( $templateRenderer, \WPML_TP_Service $active_service ) {
		return $templateRenderer( self::getModel( $active_service ), self::ACTIVE_SERVICE_TEMPLATE );
	}

	private static function getModel( \WPML_TP_Service $active_service ) {
		$model = [
			'strings'            => [
				/* translators: Label in front of the name of the translation service the site uses, on the translation services screen. */
				'title'                  => __( 'Active service:', 'sitepress' ),
				/* translators: Button label on the translation services screen: stop using this translation service. Verb, imperative. */
				'deactivate'             => __( 'Deactivate', 'sitepress' ),
				'modal_header'           => sprintf(
					/* translators: Heading of the dialog where the account details for the translation service are entered. %s: the name of that service. */
					__(
						'Enter your %s authentication details',
						'sitepress'
					),
					$active_service->get_name()
				),
				'modal_tip'              => $active_service->get_popup_message() ?
					$active_service->get_popup_message() :
					/* translators: Line in the dialog where the account details for the translation service are entered, pointing at where the token can be found. %s: the name of that service. */
					__( 'You can find the API token at %s site', 'sitepress' ),
				'modal_title'            => sprintf(
					/* translators: Title of the dialog where the account details for the translation service are entered. %s: the name of that service. */
					__( '%s authentication', 'sitepress' ),
					$active_service->get_name()
				),
				'refresh_language_pairs' => __( 'Refresh language pairs', 'sitepress' ),
				/* translators: Button label on the translation services screen: read the details of the service again from the service itself. Verb, imperative. */
				'refresh_ts_info'        => __( 'Refresh information', 'sitepress' ),
				/* translators: Link text inside a sentence on the translation services screen, opening the pages that explain the service. It starts in lower case because it sits inside the sentence. */
				'documentation_lower'    => __( 'documentation', 'sitepress' ),
				'refreshing_ts_message'  => __(
					'Refreshing translation service information...',
					'sitepress'
				),
			],
			'active_service'     => $active_service,
			'service_description' => wp_kses_post( (string) $active_service->get_description() ),
			'nonces'             => [
				\WPML_TP_Refresh_Language_Pairs::AJAX_ACTION => wp_create_nonce( \WPML_TP_Refresh_Language_Pairs::AJAX_ACTION ),
				ActivationAjax::REFRESH_TS_INFO_ACTION => wp_create_nonce( ActivationAjax::REFRESH_TS_INFO_ACTION ),
			],
			'needs_info_refresh' => self::shouldRefreshData( $active_service ),
		];

		$authentication_message = [];
		/* translators: First of three sentences shown together on the translation services screen, before the account details are entered. %1$s: the name of the translation service, in both places. */
		$authentication_message[] = __(
			'To send content for translation to %1$s, you need to have an %1$s account.',
			'sitepress'
		);
		/* translators: sentence 2/3: create account with the translation service ("one" is "one account) */
		$authentication_message[] = __(
			"If you don't have one, you can create it after clicking the authenticate button.",
			'sitepress'
		);
		/* translators: Third of three sentences shown together on the translation services screen, before the account details are entered. %2$s: a link, already wrapped in its tags, whose text is "documentation". */
		$authentication_message[] = __(
			'Please, check the %2$s page for more details.',
			'sitepress'
		);

		$model['strings']['authentication'] = [
			'description'               => implode( ' ', $authentication_message ),
			/* translators: Button label on the translation services screen: hand over the account details so the service can be used. Verb, imperative. */
			'authenticate_button'       => __( 'Authenticate', 'sitepress' ),
			/* translators: Button label on the translation services screen: take back the permission given to the translation service. Verb, imperative. */
			'de_authorize_button'       => __( 'De-authorize', 'sitepress' ),
			/* translators: Button label on the translation services screen: change the account details already given. Verb, imperative. */
			'update_credentials_button' => __( 'Update credentials', 'sitepress' ),
			'is_authorized'             => self::isAuthorizedText( $active_service->get_name() ),
		];

		return $model;
	}

	private static function isAuthorizedText( $serviceName ) {
		$query_args = [
			'page' => WPML_TM_FOLDER . \WPML_Translation_Management::PAGE_SLUG_MANAGEMENT,
			'sm'   => 'dashboard',
		];

		$href = add_query_arg( $query_args, admin_url( 'admin.php' ) );

		$dashboard = '<a href="' . esc_url( $href ) . '">' .
					 /* translators: Link text that opens the screen where content is sent for translation. It is the name of that screen. */
					 __( 'Translation Dashboard', 'sitepress' ) .
					 '</a>';

		$isAuthorized  = sprintf(
			/* translators: %s: translation service name. */
			__( 'Success! You can now send content to %s.', 'sitepress' ),
			esc_html( $serviceName )
		);
		$isAuthorized .= '<br/>';
		// translators: "%s" is replaced with the link to the "Translation Dashboard"
		$isAuthorized .= sprintf(
			/* translators: %s: link to the Translation Dashboard. */
			__( 'Go to the %s to choose the content and send it to translation.', 'sitepress' ),
			$dashboard
		);

		return $isAuthorized;
	}

	private static function shouldRefreshData( \WPML_TP_Service $active_service ) {
		$refresh_time = time() - ( self::HOURS_BEFORE_TS_REFRESH * HOUR_IN_SECONDS );

		return ! $active_service->get_last_refresh() || $active_service->get_last_refresh() < $refresh_time;
	}
}
