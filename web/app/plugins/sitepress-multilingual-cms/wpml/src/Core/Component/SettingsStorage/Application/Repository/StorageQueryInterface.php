<?php

namespace WPML\Core\Component\SettingsStorage\Application\Repository;

interface StorageQueryInterface {

  const OPTION_NAME  = 'icl_sitepress_settings';
  const ROW_PREFIX   = 'icl_sitepress_settings#';
  const REGISTRY_ROW = 'icl_sitepress_settings#__keys';


  public function scanRowKeys(): array;


  public function readRawStoredBlob();


  public function optionRowExists( string $optionName ): bool;


  public function registryRowExistsInDatabase( bool $onError = true ): bool;


}
