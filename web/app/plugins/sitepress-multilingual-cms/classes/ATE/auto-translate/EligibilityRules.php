<?php

namespace WPML\TM\ATE\AutoTranslate;

use WPML\Element\API\Languages;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\AutomaticTranslationCapabilities;

abstract class EligibilityRules {

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function shouldAutoTranslate( $trid, $postId, $targetLang ) {
		if ( ! AutomaticTranslationCapabilities::isAvailable() ) {
			return false;
		}

		$postTranslations = $this->sitepress->post_translations();

		$isOriginalPost = ! (bool) $postTranslations->get_source_lang_code( $postId );
		$postLanguage   = $postTranslations->get_element_lang_code( $postId );

		return $isOriginalPost
		       && $postLanguage === Languages::getDefaultCode()
		       && $this->postTranslatesAutomatically( $postId )
		       && AutomaticTranslationCapabilities::isLanguageEligible( $targetLang, $postLanguage )
		       && $this->isExistingTranslationOpen( $trid, $targetLang );
	}

	abstract protected function postTranslatesAutomatically( $postId );

	abstract protected function isTranslationOpen( $translation );

	private function isExistingTranslationOpen( $trid, $targetLang ) {
		$translation = Jobs::getTridJob( $trid, $targetLang );

		if ( ! $translation ) {
			return true;
		}

		if ( Jobs::isDeliveredAndCurrent( $translation ) ) {
			return false;
		}

		return $this->isTranslationOpen( $translation );
	}
}
