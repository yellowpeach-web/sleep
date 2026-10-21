<?php

namespace ACFML\Options;

use function WPML\Container\make;

class HooksFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		$hooks = [ make( StorageLanguage::class ) ];

		if ( is_admin() ) {
			$hooks[] = make( EditorHooks::class );
		}

		if ( is_admin() || wpml_is_rest_request() ) {
			$hooks[] = new TranslationJobHooks();
		}

		$isDoingXmlrpc = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
		if ( is_admin() || wpml_is_rest_request() || $isDoingXmlrpc || wp_doing_cron() ) {
			$hooks[] = new StringTranslationHooks();
		}

		$hooks[] = new CustomNamespacesHooks();
		$hooks[] = new CurrentLanguageHooks();

		return $hooks;
	}
}
