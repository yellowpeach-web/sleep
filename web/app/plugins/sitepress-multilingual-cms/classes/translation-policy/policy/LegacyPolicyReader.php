<?php

namespace WPML\TranslationPolicy\Policy;

use WPML\FP\Obj;
use WPML\Settings\PostType\Automatic;
use WPML\TM\API\Jobs;
use WPML\TM\PostEditScreen\Endpoints\SetEditorMode;
use WPML\TranslationPolicy\TranslateEverything\GateInterface;
use WPML\TranslationPolicy\TranslateEverything\TodaysGate;

class LegacyPolicyReader implements PolicyReaderInterface {

	const SCOPES = [
		SetEditorMode::MODE_FOR_THIS_POST => PolicyTable::SCOPE_POST,
		SetEditorMode::MODE_FOR_POST_TYPE => PolicyTable::SCOPE_TYPE,
		SetEditorMode::MODE_FOR_GLOBAL    => PolicyTable::SCOPE_SITE,
	];

	public function forPost( int $postId ): PolicyAnswer {
		return $this->answer( \WPML_TM_Post_Edit_TM_Editor_Mode::get_translation_editor_with_scope( null, $postId ) );
	}

	public function forMany( array $postIds ): array {
		$answers = [];
		foreach ( $postIds as $postId ) {
			$answers[ (int) $postId ] = $this->forPost( (int) $postId );
		}

		return $answers;
	}

	public function forType( string $postType ): PolicyAnswer {
		return $this->answer( \WPML_TM_Post_Edit_TM_Editor_Mode::get_translation_editor_for_post_type_with_scope( $postType ) );
	}

	public function site(): PolicyAnswer {
		return $this->forType( '' );
	}

	public function resolverAnswer( int $postId, bool $findOriginal ) {
		return null;
	}

	public function isSiteEditorAte(): bool {
		return \WPML_TM_ATE_Status::is_enabled();
	}

	public function wantsAteAccount(): bool {
		return \WPML_TM_ATE_Status::is_enabled();
	}

	public function automaticTranslationNeedsAccountOnly(): bool {
		return false;
	}

	public function isAccountUsable(): bool {
		return \WPML_TM_ATE_Status::is_enabled_and_activated();
	}

	public function jobSticksToClassicEditor( int $jobId, string $sendMode = '' ): bool {
		return (bool) wpml_tm_load_old_jobs_editor()->shouldStickToWPMLEditor( $jobId );
	}

	public function jobMode( int $jobId ): string {
		return Jobs::isEligibleForAutomaticTranslations( $jobId ) ? 'auto' : 'manual';
	}

	public function jobTypeIsAutomatic( int $jobId ): bool {
		$job = Jobs::get( $jobId );

		return $job && (bool) Automatic::isAutomatic( str_replace( 'post_', '', (string) Obj::prop( 'original_post_type', $job ) ) );
	}

	public function editorSql(): EditorSqlInterface {
		return new LegacyEditorSql();
	}

	public function listEditor(): ListEditorInterface {
		return new LegacyListEditor();
	}

	public function editorFilters(): EditorFiltersInterface {
		return new LegacyEditorFilters();
	}

	public function translateEverythingGate(): GateInterface {
		return new TodaysGate();
	}

	public function readsClassicEditorHistory(): bool {
		return true;
	}

	public function editorSwitchKeepsTranslations(): bool {
		return false;
	}

	public function carriesEditsMadeOutsideWpml(): bool {
		return false;
	}

	private function answer( array $editorAndScope ): PolicyAnswer {
		list( $editor, $scope ) = $editorAndScope;

		return new PolicyAnswer( $this->editor( $editor ), null, self::SCOPES[ $scope ] ?? '' );
	}

	private function editor( string $legacyEditor ): string {
		if ( \WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE === $legacyEditor ) {
			return PolicyTable::EDITOR_NATIVE;
		}

		return \WPML_TM_ATE_Status::is_enabled() ? PolicyTable::EDITOR_ATE : PolicyTable::EDITOR_CTE;
	}
}
