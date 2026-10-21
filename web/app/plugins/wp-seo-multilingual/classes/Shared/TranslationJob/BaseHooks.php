<?php

namespace WPML\WPSEO\Shared\TranslationJob;

use WPML\FP\Str;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;

use function WPML\FP\spreadArgs;

abstract class BaseHooks implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const PURPOSE_SEO_TITLE     = 'seo_title';
	const PURPOSE_SEO_META_DESC = 'seo_meta_description';
	const PREFIX_JOB_FIELD_TERM = 't';

	public function add_hooks() {
		Hooks::onFilter( 'wpml_tm_adjust_translation_fields', 10, 2 )
			->then( spreadArgs( [ $this, 'adjustFields' ] ) );

		Hooks::onFilter( 'wpml_tm_adjust_term_translation_fields' )
			->then( spreadArgs( [ $this, 'adjustTermJobFields' ] ) );

		Hooks::onFilter( 'wpml_st_translation_job_admin_text_prefixes_to_groups' )
			->then( spreadArgs( [ $this, 'addAdminTextPrefix' ] ) );
	}

	abstract protected function getFieldPrefix();

	abstract protected function getTopLevelGroup();

	abstract protected function getKeyPurposeMap();

	abstract protected function getTermJobFieldLabels();

	protected function getTermJobKeyPurposeMap() {
		return $this->getKeyPurposeMap();
	}

	public function addAdminTextPrefix( $prefixes ) {
		return array_merge( $prefixes, static::getTopLevelGroup() );
	}

	public function adjustFields( $fields, $job ) {
		foreach ( $fields as &$field ) {
			$fieldType = Obj::prop( 'field_type', $field );
			$field     = $this->extraAdjustField( $field, $fieldType, $job );

			if ( preg_match( '/^' . self::PREFIX_JOB_FIELD_TERM . '?' . $this->getFieldPrefix() . '/', $fieldType ) ) {
				$field = $this->addTitleAndGroup( $field );
				$field = $this->addPurpose( $field );
			}
		}

		return $fields;
	}

	public function adjustTermJobFields( $fields ) {
		$labels = $this->getTermJobFieldLabels();

		foreach ( $fields as &$field ) {
			$metaKey = (string) Obj::prop( 'meta_key', $field );

			if ( isset( $labels[ $metaKey ] ) ) {
				$field['title'] = $labels[ $metaKey ];
				$field['group'] = $this->getTopLevelGroup();

				$purpose = Obj::prop( $metaKey, $this->getTermJobKeyPurposeMap() );
				if ( $purpose ) {
					$field['purpose'] = $purpose;
				}
			}
		}

		return $fields;
	}

	protected function extraAdjustField( $field, $fieldType, $job ) {
		return $field;
	}

	private function addTitleAndGroup( $field ) {
		$title = (string) Obj::prop( 'title', $field );
		if ( $title && Str::startsWith( $this->getFieldPrefix(), $title ) ) {
			$field['title'] = apply_filters(
				'wpml_labelize_string',
				substr( $title, strlen( $this->getFieldPrefix() ) ),
				'TranslationJob'
			);
		}
		$field['group'] = $this->getTopLevelGroup();

		return $field;
	}

	private function addPurpose( $field ) {
		$fieldKey = preg_replace( '/^(' . self::PREFIX_JOB_FIELD_TERM . '?field-)(.*)(-\d+)$/', '$2', $field['field_type'] );

		$purpose = wpml_collect( $this->getKeyPurposeMap() )->get( $fieldKey );

		if ( $purpose ) {
			$field['purpose'] = $purpose;
		}

		return $field;
	}
}
