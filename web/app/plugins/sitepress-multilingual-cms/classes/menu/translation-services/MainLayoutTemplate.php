<?php

namespace WPML\TM\Menu\TranslationServices;

use WPML\LIB\WP\Nonce;
use WPML\Setup\Option;
use WPML\TM\Menu\TranslationServices\Endpoints\Activate;
use WPML\TM\Menu\TranslationServices\Endpoints\Deactivate;
use WPML\TM\Menu\TranslationServices\Endpoints\Select;
use WPML\UIPage;

class MainLayoutTemplate {

	const SERVICES_LIST_TEMPLATE = 'services-layout.twig';

	public static function render(
		$templateRenderer,
		$activeServiceRenderer,
		$hasPreferredService,
		$retrieveServiceTabsData
	) {
		echo $templateRenderer(
			self::getModel( $activeServiceRenderer, $hasPreferredService, $retrieveServiceTabsData ),
			self::SERVICES_LIST_TEMPLATE
		);
	}

	private static function getModel( $activeServiceRenderer, $hasPreferredService, $retrieveServiceTabsData ) {
		$services = $retrieveServiceTabsData();

		$translationServicesUrl = \WPML\OutboundLinks\OutboundLinks::to(
			'https://wpml.org/documentation/translating-your-contents/professional-translation-via-wpml/',
			array(
				'medium'   => 'dashboard',
				'campaign' => 'translation-management',
			)
		);

		return [
			'active_service'        => $activeServiceRenderer(),
			'has_active_service'    => (bool) ActiveServiceRepository::get(),
			'services'              => $services,
			'has_preferred_service' => $hasPreferredService,
			'has_services'          => ! empty( $services ),
			'translate_everything'  => Option::shouldTranslateEverything(),
			'nonces'                => [
				ActivationAjax::NONCE_ACTION    => wp_create_nonce( ActivationAjax::NONCE_ACTION ),
				AuthenticationAjax::AJAX_ACTION => wp_create_nonce( AuthenticationAjax::AJAX_ACTION ),
			],
			'settings_url'          => UIPage::getSettings(),
			'lsp_logo_placeholder'  => WPML_TM_URL . '/res/img/lsp-logo-placeholder.png',
			'strings'               => [
				/* translators: Name of the screen where a translation service is chosen, and its heading. */
				'translation_services'                => __( 'Translation Services', 'sitepress' ),
				'translation_services_description'    => sprintf(
					/* translators: Text at the top of the translation services screen. %s: the address of a page on wpml.org; it fills the link tag that is already in the text. */
					__(
						'WPML integrates with dozens of professional <a target="_blank" href="%s">translation services</a>. Connect to your preferred service to send and receive translation jobs from directly within WPML.',
						'sitepress'
					),
					esc_url( $translationServicesUrl )
				),
				'enable_unlisted_translation_service' => __( 'Activate a translation service that\'s not listed here', 'sitepress' ),
				'ts'                                  => [
					'different'   => __( 'Looking for a different translation service?', 'sitepress' ),
					'tell_us_url' => \WPML\OutboundLinks\OutboundLinks::to(
						'https://app.wpml.org/home/contact-us',
						array(
							'medium'   => 'settings',
							'campaign' => 'translation-management',
							'content'  => 'tell-us-which-service',
						)
					),
					'tell_us'     => __( 'Tell us which one', 'sitepress' ),
				],
			],
			'endpoints'             => [
				'selectService'     => [
					'endpoint' => Select::class,
					'nonce'    => Nonce::create( Select::class )
				],
				'deactivateService' => [
					'nonce'    => Nonce::create( Deactivate::class ),
					'endpoint' => Deactivate::class
				],
				'activateService'   => [
					'nonce'    => Nonce::create( Activate::class ),
					'endpoint' => Activate::class
				],
			],
		];
	}
}
