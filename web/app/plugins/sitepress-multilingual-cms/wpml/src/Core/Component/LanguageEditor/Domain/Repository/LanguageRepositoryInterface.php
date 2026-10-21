<?php

namespace WPML\Core\Component\LanguageEditor\Domain\Repository;

interface LanguageRepositoryInterface {


  public function activeCodes(): array;


  public function activePairs(): array;


  public function inactivePairs(): array;


  public function hasRetainedContent( string $code ): bool;


  public function activeDisplayCodes(): array;


  public function removedWithContentDisplayCodes(): array;


  public function displayCodeOf( string $code ): ?string;


  public function activeLocales(): array;


  public function activeBcp47Tags(): array;


  public function isActive( string $code ): bool;


  public function rowId( string $code ): ?int;


  public function rowIsCustom( string $code ): ?bool;

  public function isCustomIdentity( string $code ): bool;


  public function preset( string $presetCode, bool $includeUnvouched = false ): ?array;


  public function presetPair( string $presetCode, ?string $country, bool $includeUnvouched = false ): ?array;


  public function presetOwningCode( string $code ): ?array;


  public function withdrawnCatalogueCode( string $code ): ?array;


  public function englishNameTaken( string $name, string $code ): bool;


  public function activeNameRows(): array;


  public function englishNameOf( string $code ): ?string;


  public function englishNameOwner( string $name, string $excludeCode ): ?array;


  public function renameEnglishName( int $id, string $name ): void;


  public function reactivate( int $id, array $fields ): void;


  public function insert( array $fields ): ?int;


  public function insertCustom( array $fields ): ?int;


  public function deactivate( string $code ): void;


  public function setFlag( string $code, string $flagFile ): void;

	public function offeredFlagsFor( string $code ): ?array;


  public function hasFlag( string $code ): bool;


  public function countryFlag( string $code ): ?string;


  public function countryName( string $code ): ?string;


  public function setTag( string $code, ?string $tag ): void;


  public function setEncodeUrl( string $code, bool $encode ): void;


  public function setRtl( string $code, ?bool $rtl ): void;


}
