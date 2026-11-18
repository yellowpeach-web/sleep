<?php

namespace WPML\TranslationPolicy\Policy;

use WPML\TranslationPolicy\TranslateEverything\GateInterface;

interface PolicyReaderInterface {

	public function forPost( int $postId ): PolicyAnswer;

	public function forMany( array $postIds ): array;

	public function forType( string $postType ): PolicyAnswer;

	public function site(): PolicyAnswer;

	public function resolverAnswer( int $postId, bool $findOriginal );

	public function isSiteEditorAte(): bool;

	public function wantsAteAccount(): bool;

	public function automaticTranslationNeedsAccountOnly(): bool;

	public function isAccountUsable(): bool;

	public function jobSticksToClassicEditor( int $jobId, string $sendMode = '' ): bool;

	public function jobMode( int $jobId ): string;

	public function jobTypeIsAutomatic( int $jobId ): bool;

	public function editorSql(): EditorSqlInterface;

	public function listEditor(): ListEditorInterface;

	public function editorFilters(): EditorFiltersInterface;

	public function translateEverythingGate(): GateInterface;

	public function readsClassicEditorHistory(): bool;

	public function editorSwitchKeepsTranslations(): bool;

	public function carriesEditsMadeOutsideWpml(): bool;
}
