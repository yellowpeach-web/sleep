<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel;

interface EditorScreenQueryInterface {


  public function isBlockEditorScreen(): bool;


  public function isSiteEditorScreen(): bool;


  public function currentPost(): ?array;


  public function canEditThemeOptions(): bool;


  public function isSetupComplete(): bool;


  public function isRestPostType( string $postType ): bool;


  public function canSeeContentSetup(): bool;


  public function restRoute( string $postType ): array;


  public function postTypeLabels( string $postType ): array;


  public function isAutomaticTranslations(): bool;


  public function isAdminBarShowing(): bool;


  public function postHogTrackingMode(): string;


  public function ajaxUrl(): string;


  public function nonce( string $action ): string;


  public function settingsUrl( string $query ): string;


  public function translationDashboardUrl(): string;


}
