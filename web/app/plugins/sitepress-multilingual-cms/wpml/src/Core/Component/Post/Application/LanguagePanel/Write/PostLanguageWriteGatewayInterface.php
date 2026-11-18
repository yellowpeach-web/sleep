<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel\Write;

interface PostLanguageWriteGatewayInterface {


  public function getPost( int $postId ): ?array;


  public function isActiveLanguage( string $code ): bool;


  public function getElementTrid( int $postId, string $postType ): ?int;


  public function getElementLanguageCode( int $postId ): ?string;


  public function getElementIdInLanguage( int $trid, string $language, string $postType ): ?int;


  public function getOriginalElementId( int $trid ): ?int;


  public function countGroupMembers( int $trid, string $postType ): int;


  public function switchLanguage( int $postId, string $postType, string $language ): bool;


  public function setTranslationOf( int $postId, string $postType, ?int $trid, string $language, ?string $sourceLanguage ): void;


  public function makeDuplicates( int $postId, array $languages ): void;


  public function resetDuplication( int $postId ): void;


  public function setDuplication( int $originalId, string $language );


  public function setEditorMode( string $scope, string $editor, int $postId, bool $isSwitchingWpmlNative ): void;


  public function setShowTranslations( ?bool $show ): bool;


  public function getRootPageId(): ?int;


  public function assignRootPage( int $pageId ): void;


  public function resolveTranslationIntent( array $intent ): array;


  public function enableAmsSubscription( int $userId ): void;


}
