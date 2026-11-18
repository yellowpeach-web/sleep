<?php

use WPML\PB\TranslationJob\LabelResolver;

class WPML_TM_Page_Builders {
	const PACKAGE_TYPE_EXTERNAL = 'external';
	const TRANSLATION_COMPLETE  = 10;

	const FIELD_STYLE_AREA   = 'AREA';
	const FIELD_STYLE_VISUAL = 'VISUAL';
	const FIELD_STYLE_LINK   = 'LINK';

	const TOP_LEVEL_GROUP_ID          = \WPML_TM_Xliff_Writer::DEFAULT_GROUP_ID;
	const TOP_LEVEL_GROUP_DESCRIPTION = 'Main Content';

	private $sitepress;

	private $wpmlPbIntegration;

	private $ownerJobService;

	private $labelResolver;

	private $elementTranslations;

	public function __construct(
		SitePress $sitepress,
		?\WPML_PB_Integration $wpmlPbIntegration = null,
		?\WPML_TM_Element_Translations $elementTranslations = null
	) {
		$this->sitepress           = $sitepress;
		$this->wpmlPbIntegration   = $wpmlPbIntegration;
		$this->elementTranslations = $elementTranslations;
	}

	public function translation_job_data_filter( array $translation_package, $post, $isOriginal = false ) {
		return $this->getOwnerJobService()->translation_job_data_filter( $translation_package, $post, $isOriginal );
	}

	public function pro_translation_completed_action( $new_element_id, array $fields, stdClass $job ) {
		$this->getOwnerJobService()->pro_translation_completed_action( $new_element_id, $fields, $job );
	}

	private function getOwnerJobService() {
		if ( null === $this->ownerJobService ) {
			$this->ownerJobService = new \WPML\TM\OwnedPackages\OwnerJobService(
				$this->sitepress,
				$this->wpmlPbIntegration,
				[ $this, 'create_field_wrapper' ],
				$this->get_label_resolver()
			);
		}

		return $this->ownerJobService;
	}

	public function applied_job_needs_update_filter( $needs_update, $job ) {
		return $this->getOwnerJobService()->applied_job_needs_update_filter( $needs_update, $job );
	}

	public function adjust_translation_fields_filter( array $fields ) {
		return $this->getOwnerJobService()->adjust_translation_fields_filter( $fields );
	}

	public function xliffUnitPlaceholdersFilter( array $patterns, $element ) {
		return $this->getOwnerJobService()->xliffUnitPlaceholdersFilter( $patterns, $element );
	}

	public function adjust_translation_job_filter( $elements ) {
		return $this->getOwnerJobService()->adjust_translation_job_filter( $elements );
	}

	public function job_layout_filter( array $layout ) {
		return $this->getOwnerJobService()->job_layout_filter( $layout );
	}

	public function populate_previous_translation( array $previousTranslations, array $package, $language ) {
		return $this->getOwnerJobService()->populate_previous_translation( $previousTranslations, $package, $language );
	}

	public function link_to_translation_filter( $link, $post_id, $lang, $trid ) {
		if ( $link && $trid && $this->getElementTranslations()->is_update_needed( $trid, $lang ) ) {
			$args = [
				'update_needed' => 1,
				'trid'          => $trid,
				'language_code' => $lang,
			];

			$link = add_query_arg( $args, $link );
		}

		return $link;
	}

	protected function get_label_resolver() {
		if ( ! $this->labelResolver ) {
			$this->labelResolver = new LabelResolver( null, null, new \WPML\PB\Gutenberg\ConfigLabels() );
		}

		return $this->labelResolver;
	}

	public function create_field_wrapper( $field_slug ) {
		return new WPML_TM_Page_Builders_Field_Wrapper( $field_slug );
	}

	private function getElementTranslations() {
		if ( null === $this->elementTranslations ) {
			$this->elementTranslations = wpml_tm_load_element_translations();
		}
		return $this->elementTranslations;
	}
}
