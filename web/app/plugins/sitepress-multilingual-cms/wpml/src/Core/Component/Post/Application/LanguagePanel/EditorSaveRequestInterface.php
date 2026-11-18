<?php

namespace WPML\Core\Component\Post\Application\LanguagePanel;

interface EditorSaveRequestInterface {


  public function isEditorSave(): bool;


  public function isSiteEditorSave(): bool;


  public function savedPostId(): ?int;


}
