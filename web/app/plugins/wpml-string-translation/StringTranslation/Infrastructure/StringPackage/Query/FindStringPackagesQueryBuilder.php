<?php

namespace WPML\StringTranslation\Infrastructure\StringPackage\Query;

use WPML\StringTranslation\Application\Setting\Repository\SettingsRepositoryInterface as SettingsRepository;
use WPML\StringTranslation\Application\StringPackage\Query\Criteria\StringPackageCriteria;

class FindStringPackagesQueryBuilder {
	use TranslationStatusQueryBuilderTrait;

	private $wpdb;

	private $settingsRepository;

	public function __construct( $wpdb, SettingsRepository $settingsRepository ) {
		$this->wpdb               = $wpdb;
		$this->settingsRepository = $settingsRepository;
	}

	public function build( StringPackageCriteria $criteria ): string {
		$sourceLanguage = $this->sqlStringLiteral( $this->getSourceLanguageCode( $criteria ) );
		$kindSlug       = $this->sqlStringLiteral( $criteria->getType() );

		return "
			SELECT sp.ID, sp.name, sp.kind_slug, sp.title, sp.word_count, sp.translator_note
			FROM {$this->wpdb->prefix}icl_string_packages sp
			INNER JOIN {$this->wpdb->prefix}icl_translations source_t
				ON source_t.element_id = sp.ID
				AND source_t.element_type = CONCAT('package_', sp.kind_slug)
				AND source_t.language_code = {$sourceLanguage}
			WHERE sp.kind_slug = {$kindSlug}
				{$this->buildPostTitleCondition( $criteria )}
				AND {$this->buildTranslationStatusCondition( $criteria, $this->getTargetLanguageCodes( $criteria ) )}
			{$this->buildSortingQueryPart( $criteria )}
			{$this->buildPagination( $criteria )}
		";
	}
}
