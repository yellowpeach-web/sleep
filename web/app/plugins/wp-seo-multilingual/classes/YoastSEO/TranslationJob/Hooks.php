<?php

namespace WPML\WPSEO\YoastSEO\TranslationJob;

use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\WPSEO\Shared\TranslationJob\BaseHooks;
use WPML\WPSEO\YoastSEO\Terms\Meta\Hooks as TermsMetaHooks;
use WPML\WPSEO\YoastSEO\Utils;

class Hooks extends BaseHooks {

	const OPTION_PREFIX = 'wpseo_';

	protected function getFieldPrefix() {
		return 'field-_yoast_wpseo_';
	}

	protected function getTopLevelGroup() {
		return [ self::OPTION_PREFIX => 'Yoast SEO' ];
	}

	protected function getKeyPurposeMap() {
		return [
			'_yoast_wpseo_title'    => self::PURPOSE_SEO_TITLE,
			'_yoast_wpseo_metadesc' => self::PURPOSE_SEO_META_DESC,
		];
	}

	protected function getTermJobFieldLabels() {
		return TermsMetaHooks::FIELDS;
	}

	protected function getTermJobKeyPurposeMap() {
		return [
			Utils::KEY_META_TITLE => self::PURPOSE_SEO_TITLE,
			Utils::KEY_META_DESC  => self::PURPOSE_SEO_META_DESC,
		];
	}

	protected function extraAdjustField( $field, $fieldType, $job ) {
		return $this->addPurposeToTermMeta( $field, $fieldType, $job );
	}

	private function addPurposeToTermMeta( $field, $fieldType, $job ) {
		$jobType = Obj::prop( 'original_post_type', $job );

		if ( 'package_' . TermsMetaHooks::PACKAGE['kind_slug'] !== $jobType ) {
			return $field;
		}

		foreach ( $this->getTermJobKeyPurposeMap() as $metaKey => $purpose ) {
			if ( Str::endsWith( $metaKey, (string) $fieldType ) ) {
				$field['purpose'] = $purpose;
				break;
			}
		}

		return $field;
	}
}
