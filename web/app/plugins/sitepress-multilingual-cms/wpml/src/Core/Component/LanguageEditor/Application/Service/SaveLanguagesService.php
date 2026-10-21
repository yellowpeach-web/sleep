<?php

namespace WPML\Core\Component\LanguageEditor\Application\Service;

use WPML\Core\Component\LanguageEditor\Application\Result;
use WPML\Core\Component\LanguageEditor\Domain\Ate\AteMappingInterface;
use WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat;
use WPML\Core\Component\LanguageEditor\Domain\EnglishNameQualification;
use WPML\Core\Component\LanguageEditor\Domain\LanguageDerivation;
use WPML\Core\Component\LanguageEditor\Domain\LanguagePairKey;
use WPML\Core\Component\LanguageEditor\Domain\Repository\LabelsRepositoryInterface;
use WPML\Core\Component\LanguageEditor\Domain\Repository\LanguageRepositoryInterface;
use WPML\Core\Component\LanguageEditor\Domain\Settings\SettingsInterface;

class SaveLanguagesService {

    private $languages;

    private $settings;

    private $ate;

    private $labels;


  public function __construct(
        LanguageRepositoryInterface $languages,
        SettingsInterface $settings,
        AteMappingInterface $ate,
        LabelsRepositoryInterface $labels
    ) {
      $this->languages = $languages;
      $this->settings  = $settings;
      $this->ate       = $ate;
      $this->labels    = $labels;
  }


  public function run( array $languages, string $defaultCode ): Result {
      $current = $this->languages->activeCodes();
      $posted  = self::codeCounts( $languages );
      $desired = [];
    foreach ( $languages as $row ) {
      if ( isset( $row['code'] ) ) {
        $desired[ (string) $row['code'] ] = $row;
      }
    }
      $defaultCode = self::keptDefault( $desired, $defaultCode, $current, (string) $this->settings->defaultLanguage() );

      $invalid = $this->validate( $languages, $defaultCode );
    if ( $invalid !== null ) {
        return $invalid;
    }

      $add       = new AddLanguageService( $this->languages, self::presetCodesIn( $languages ) );
      $effective = [];
      $added     = [];
    foreach ( $this->orderForAdd( $languages, $defaultCode ) as $row ) {
        $row  = (array) $row;
        $code = isset( $row['code'] ) ? (string) $row['code'] : '';
      if ( $code === '' ) {
          return Result::error( 'code_required', [ 'code' => $code ] );
      }
        $presetCode = isset( $row['presetCode'] ) && $row['presetCode'] !== null ? (string) $row['presetCode'] : '';

      if ( in_array( $code, $current, true ) ) {
        if ( $presetCode !== ''
          && ( $posted[ $code ] ?? 0 ) > 1
          && $this->activeCodeForPair( $presetCode, $row['country'] ?? null ) === null ) {
            return $this->alreadyActive( $row, $code );
        }
          $effective[ $code ] = $code;
          continue;
      }

      if ( $presetCode !== '' ) {
          $activeForPair = $this->activeCodeForPair( $presetCode, $row['country'] ?? null );
        if ( $activeForPair !== null ) {
            $effective[ $code ] = $activeForPair;
            continue;
        }
      }

      if ( $presetCode === '' ) {
        if ( empty( $row['custom'] ) ) {
            return Result::error( 'missing_preset', [ 'code' => $code, 'displayCode' => $row['displayCode'] ?? null ] );
        }
          $result = $this->addCustom( $row, $desired );
        if ( ! $result->isOk() ) {
            $payload = $result->payload();
            $error   = isset( $payload['error'] ) ? (string) $payload['error'] : 'add_failed';
            unset( $payload['error'] );
            return Result::error( $error, array_merge( $payload, [ 'code' => $code ] ) );
        }
          $real               = (string) ( $result->payload()['code'] ?? $code );
          $effective[ $code ] = $real;
          $added[ $code ]     = $real;
          continue;
      }
        $reviveLegacy = array_key_exists( 'reviveLegacy', $row ) && $row['reviveLegacy'] !== null
          ? (bool) $row['reviveLegacy']
          : null;

        $result = $add->run( $presetCode, $row['country'] ?? null, $row['displayCode'] ?? null, $reviveLegacy );
      if ( ! $result->isOk() ) {
          $payload = $result->payload();
          $error   = isset( $payload['error'] ) ? (string) $payload['error'] : 'add_failed';
          unset( $payload['error'] );
          return Result::error( $error, array_merge( $payload, [ 'code' => $code ] ) );
      }
        $real                = (string) ( $result->payload()['code'] ?? $code );
        $effective[ $code ]  = $real;
        $added[ $code ]      = $real;
    }

      $realDefault = $effective[ $defaultCode ] ?? $defaultCode;
    if ( $realDefault !== '' ) {
        $this->setDefault( $realDefault );
    }

    foreach ( $current as $code ) {
      if ( ! isset( $desired[ $code ] ) ) {
          $this->remove( $code );
      }
    }

    foreach ( $languages as $row ) {
        $code = isset( $row['code'] ) ? (string) $row['code'] : '';
      if ( $code === '' || ! isset( $effective[ $code ] ) ) {
          continue;
      }
        $rc = $effective[ $code ];

      if ( isset( $row['fields'] ) && is_array( $row['fields'] ) ) {
          $fieldError = $this->saveFields( $rc, $row['fields'] );
        if ( $fieldError !== null ) {
            return $fieldError;
        }
      }
      if ( isset( $row['labels'] ) && is_array( $row['labels'] ) ) {
          $this->labels->save( $rc, $row['labels'] );
      }
      if ( array_key_exists( 'mapping', $row ) ) {
          $mapping = is_array( $row['mapping'] ) ? $row['mapping'] : [];
          $this->ate->saveMapping( $rc, (string) ( $mapping['ateLang'] ?? '' ), $mapping['ateCountry'] ?? null );
      } elseif ( isset( $added[ $code ] ) && ! empty( $row['custom'] ) ) {
          $this->ate->saveMapping( $rc, '', null );
      }
      if ( array_key_exists( 'hidden', $row ) ) {
          $this->setHidden( $rc, (bool) $row['hidden'] );
      }
    }

      return Result::ok( [ 'added' => $added, 'default' => $realDefault ] );
  }


  public function validate( array $languages, string $defaultCode ): ?Result {
      $current = $this->languages->activeCodes();
      $posted  = self::codeCounts( $languages );
      $desired = [];
    foreach ( $languages as $row ) {
      if ( isset( $row['code'] ) ) {
        $desired[ (string) $row['code'] ] = $row;
      }
    }
      $defaultCode = self::keptDefault( $desired, $defaultCode, $current, (string) $this->settings->defaultLanguage() );

    if ( $defaultCode !== '' && ! isset( $desired[ $defaultCode ] ) ) {
        return Result::error( 'default_language_removed', [ 'code' => $defaultCode ] );
    }

      $add            = new AddLanguageService( $this->languages, self::presetCodesIn( $languages ) );
      $claimedDisplay = [];
      $claimedNames   = [];
      $claimedTags    = [];

    foreach ( $this->orderForAdd( $languages, $defaultCode ) as $row ) {
        $row  = (array) $row;
        $code = isset( $row['code'] ) ? (string) $row['code'] : '';
      if ( $code === '' ) {
          return Result::error( 'code_required', [ 'code' => $code ] );
      }
        $presetCode = isset( $row['presetCode'] ) && $row['presetCode'] !== null ? (string) $row['presetCode'] : '';

      if ( in_array( $code, $current, true ) ) {
        if ( $presetCode !== ''
          && ( $posted[ $code ] ?? 0 ) > 1
          && $this->activeCodeForPair( $presetCode, $row['country'] ?? null ) === null ) {
            return $this->alreadyActive( $row, $code );
        }
          continue;
      }

      if ( $presetCode !== '' && $this->activeCodeForPair( $presetCode, $row['country'] ?? null ) !== null ) {
          continue;
      }

      if ( $presetCode === '' ) {
        if ( empty( $row['custom'] ) ) {
            return Result::error( 'missing_preset', [ 'code' => $code, 'displayCode' => $row['displayCode'] ?? null ] );
        }
          $result = $this->validateCustomRow( $row, $desired, $claimedDisplay, $claimedNames );
      } else {
          $rowRevive = array_key_exists( 'reviveLegacy', $row ) && $row['reviveLegacy'] !== null
            ? (bool) $row['reviveLegacy']
            : null;
          $result    = $add->validate( $presetCode, $row['country'] ?? null, $row['displayCode'] ?? null, $claimedDisplay, [], $rowRevive, $claimedTags );
      }

      if ( ! $result->isOk() ) {
          $payload = $result->payload();
          $error   = isset( $payload['error'] ) ? (string) $payload['error'] : 'add_failed';
          unset( $payload['error'] );
          return Result::error( $error, array_merge( $payload, [ 'code' => $code ] ) );
      }

        $ctx              = $result->payload();
        $claimedDisplay[] = strtolower( (string) ( $ctx['resolvedDisplay'] ?? $ctx['displayCode'] ?? '' ) );
        $claimedNames[]   = strtolower( (string) ( $ctx['englishName'] ?? '' ) );
        $claimedTag = strtolower( (string) ( $ctx['bcp47'] ?? '' ) );
      if ( '' !== $claimedTag ) {
          $claimedTags[] = $claimedTag;
      }
    }

      return null;
  }


  private function validateCustomRow( array $row, array $desired, array $claimedDisplay, array $claimedNames ): Result {
      $code = strtolower( trim( (string) ( $row['displayCode'] ?? ( $row['code'] ?? '' ) ) ) );
    if ( $code === '' ) {
        return Result::error( 'code_required', [] );
    }

      $badFormat = $this->rejectMalformedFormat( $row, $code );
    if ( $badFormat !== null ) {
        return $badFormat;
    }

      $catalogue = $this->rejectCatalogueCode( $code );
    if ( $catalogue !== null ) {
        return $catalogue;
    }

      $activeDisplayCodes = array_map( 'strtolower', $this->languages->activeDisplayCodes() );
    if ( in_array( $code, array_merge( $activeDisplayCodes, $claimedDisplay ), true ) ) {
        return Result::error( 'display_code_in_use', [ 'displayCode' => $code ] );
    }

      $englishName = isset( $row['englishName'] ) ? trim( (string) $row['englishName'] ) : '';
    if ( $englishName === '' ) {
        return Result::error( 'name_required', [ 'displayCode' => $code ] );
    }
    if ( in_array( strtolower( $englishName ), $claimedNames, true ) || $this->nameWouldConflict( $englishName, $code, $desired ) ) {
        return Result::error( 'name_in_use', [ 'name' => $englishName ] );
    }

      $locale = isset( $row['locale'] ) ? trim( (string) $row['locale'] ) : '';
    if ( $locale === '' ) {
        return Result::error( 'locale_required', [ 'displayCode' => $code ] );
    }

      return Result::ok(
        [
              'displayCode' => $code,
              'locale'      => $locale,
              'englishName' => $englishName,
          ]
      );
  }


  private function nameWouldConflict( string $name, string $code, array $desired ): bool {
      $owner = $this->languages->englishNameOwner( $name, $code );
    if ( $owner === null ) {
        return false;
    }
    if ( ! $owner['active'] && ! $this->languages->hasRetainedContent( $owner['code'] ) ) {
        return false;
    }
    if ( ! isset( $desired[ $owner['code'] ] ) ) {
        return ! $owner['active'];
    }
      $postedEnglish = $this->postedEnglishLabel( $desired[ $owner['code'] ] );
    if ( $postedEnglish !== null && strcasecmp( $postedEnglish, $name ) !== 0 ) {
        return false;
    }

      return true;
  }


  private static function codeCounts( array $languages ): array {
      $counts = [];
    foreach ( $languages as $row ) {
        $row = (array) $row;
      if ( ! isset( $row['code'] ) ) {
          continue;
      }
        $code = (string) $row['code'];
        $counts[ $code ] = ( $counts[ $code ] ?? 0 ) + 1;
    }

      return $counts;
  }


  private function alreadyActive( array $row, string $code ): Result {
      return Result::error(
        'language_already_active',
        [
              'code'        => $code,
              'displayCode' => $row['displayCode'] ?? null,
              'activeCode'  => $code,
              'country'     => $row['country'] ?? null,
          ]
      );
  }


  private function activeCodeForPair( string $presetCode, ?string $country ): ?string {
      $presetCode = strtolower( trim( $presetCode ) );
    if ( $presetCode === '' ) {
        return null;
    }
      $country = $country === null || $country === '' ? null : $country;
      $pair = $this->languages->presetPair( $presetCode, $country, true );
    if ( $pair === null ) {
        return null;
    }

      $preset = $this->languages->preset( $presetCode, true );
      $script = $preset && ! empty( $preset['script'] ) ? (string) $preset['script'] : null;
      $language = $preset !== null && isset( $preset['language'] ) && (string) $preset['language'] !== ''
        ? (string) $preset['language']
        : LanguagePairKey::languageSubtag( $presetCode, $script );

      $incomingPair = LanguagePairKey::forPreset( $language, $script, $pair['country'] );

    foreach ( $this->languages->activePairs() as $activePair ) {
      if ( LanguagePairKey::forHead( LanguagePairKey::headOf( $activePair ), $activePair['country'] ) === $incomingPair ) {
          return $activePair['code'];
      }
    }

      return null;
  }


  private function addCustom( array $row, array $desired ): Result {
      $code = strtolower( trim( (string) ( $row['displayCode'] ?? ( $row['code'] ?? '' ) ) ) );
    if ( $code === '' ) {
        return Result::error( 'code_required', [] );
    }

      $badFormat = $this->rejectMalformedFormat( $row, $code );
    if ( $badFormat !== null ) {
        return $badFormat;
    }

      $catalogue = $this->rejectCatalogueCode( $code );
    if ( $catalogue !== null ) {
        return $catalogue;
    }

      $activeDisplayCodes = array_map( 'strtolower', $this->languages->activeDisplayCodes() );
    if ( in_array( strtolower( $code ), $activeDisplayCodes, true ) ) {
        return Result::error( 'display_code_in_use', [ 'displayCode' => $code ] );
    }

      $englishName = isset( $row['englishName'] ) ? trim( (string) $row['englishName'] ) : '';
    if ( $englishName === '' ) {
        return Result::error( 'name_required', [ 'displayCode' => $code ] );
    }

      $free = $this->freeEnglishName( $englishName, $code, $desired );
    if ( ! $free->isOk() ) {
        return $free;
    }
      $locale = isset( $row['locale'] ) ? trim( (string) $row['locale'] ) : '';
    if ( $locale === '' ) {
        return Result::error( 'locale_required', [ 'displayCode' => $code ] );
    }


      $hreflang    = isset( $row['hreflang'] ) && $row['hreflang'] !== ''
        ? (string) $row['hreflang']
        : str_replace( '_', '-', $locale );
      $country     = isset( $row['country'] ) && $row['country'] !== null && $row['country'] !== ''
        ? (string) $row['country']
        : null;

      $withdrawn = $this->languages->withdrawnCatalogueCode( str_replace( '_', '-', $code ) );
    if ( $country === null && $withdrawn !== null && $withdrawn['country'] !== null ) {
        $country = $withdrawn['country'];
    }

      $canonical = LanguageDerivation::customCanonicalCode(
        $code,
        $this->languages->activeCodes(),
        function ( string $candidate ): bool {
            return $this->languages->rowId( $candidate ) !== null
              || $this->languages->presetOwningCode( $candidate ) !== null;
        },
        function ( string $candidate ): bool {
            return $this->languages->rowIsCustom( $candidate ) === false;
        }
      );

      $id = $this->languages->insertCustom(
        [
              'code'           => $canonical,
              'english_name'   => $englishName,
              'default_locale' => $locale,
              'tag'            => $hreflang,
              'country'        => $country,
              'type'           => 'international',
              'display_code'   => $code,
              'encode_url'     => ! empty( $row['encodeUrl'] ),
              'is_rtl'         => self::authoredDirection( $row, 'isRtl' ),
          ]
      );
    if ( $id === null ) {
        $owner = $this->languages->englishNameOwner( $englishName, $canonical );
      if ( $owner !== null && ! empty( $owner['active'] ) ) {
          return Result::error( 'name_in_use', [ 'name' => $englishName ] );
      }
      if ( $this->languages->isActive( $canonical ) ) {
          return Result::error( 'display_code_in_use', [ 'displayCode' => $code ] );
      }

        return Result::error( 'insert_failed', [ 'displayCode' => $code ] );
    }

    if ( ! empty( $row['flag'] ) ) {
        $flagError = $this->guardFlag( $canonical, (string) $row['flag'] );
      if ( null !== $flagError ) {
          return $flagError;
      }
        $this->languages->setFlag( $canonical, (string) $row['flag'] );
    } elseif ( $withdrawn !== null && $withdrawn['flag'] !== null ) {
        $this->languages->setFlag( $canonical, $withdrawn['flag'] );
    } else {
        $this->languages->setFlag( $canonical, LanguageDerivation::FLAG_NEUTRAL_GLOBE );
    }

      return Result::ok( [ 'code' => $canonical ] );
  }


  private function rejectMalformedFormat( array $row, string $code ): ?Result {
      $codeError = CustomLanguageFormat::validateCode( $code );
    if ( $codeError !== '' ) {
        return Result::error( $codeError, [ 'displayCode' => $code ] );
    }

      $locale      = isset( $row['locale'] ) ? trim( (string) $row['locale'] ) : '';
      $localeError = CustomLanguageFormat::validateLocale( $locale );
    if ( $localeError !== '' ) {
        return Result::error( $localeError, [ 'locale' => $locale, 'displayCode' => $code ] );
    }

      $hreflang      = isset( $row['hreflang'] ) ? trim( (string) $row['hreflang'] ) : '';
      $hreflangError = CustomLanguageFormat::validateHreflang( $hreflang );
    if ( $hreflangError !== '' ) {
        return Result::error( $hreflangError, [ 'hreflang' => $hreflang, 'displayCode' => $code ] );
    }

      return null;
  }


  private function rejectCatalogueCode( string $code ): ?Result {
      $catalogueCode = str_replace( '_', '-', $code );
      $owner         = $this->languages->presetOwningCode( $catalogueCode );
    if ( $owner === null ) {
        return null;
    }

    if ( $this->languages->withdrawnCatalogueCode( $catalogueCode ) !== null
      && $this->languages->rowIsCustom( $catalogueCode ) !== false ) {
        return null;
    }

      return Result::error(
        'code_is_catalogue',
        [
              'code'        => $code,
              'englishName' => $owner['english_name'],
          ]
      );
  }


  private function freeEnglishName( string $name, string $code, array $desired ): Result {
      $owner = $this->languages->englishNameOwner( $name, $code );
    if ( $owner === null ) {
        return Result::ok( [] );
    }

      $keepsItsName = ! $owner['active'] && $this->languages->hasRetainedContent( $owner['code'] );

    if ( ! $keepsItsName && ( ! $owner['active'] || ! isset( $desired[ $owner['code'] ] ) ) ) {
        $this->languages->renameEnglishName( $owner['id'], $this->qualifiedName( $name, $owner ) );

        return Result::ok( [] );
    }

      $postedEnglish = isset( $desired[ $owner['code'] ] )
        ? $this->postedEnglishLabel( $desired[ $owner['code'] ] )
        : null;
    if ( $postedEnglish !== null && strcasecmp( $postedEnglish, $name ) !== 0 ) {
        $newName = $this->languages->englishNameTaken( $postedEnglish, $owner['code'] )
          ? $this->qualifiedName( $postedEnglish, $owner )
          : $postedEnglish;
        $this->languages->renameEnglishName( $owner['id'], $newName );

        return Result::ok( [] );
    }

      return Result::error( 'name_in_use', [ 'name' => $name ] );
  }


  private function postedEnglishLabel( array $row ): ?string {
      $labels = isset( $row['labels'] ) && is_array( $row['labels'] ) ? $row['labels'] : [];
      $label  = isset( $labels['en'] ) ? trim( (string) $labels['en'] ) : '';

      return $label === '' ? null : $label;
  }


  private function qualifiedName( string $name, array $owner ): string {
      return EnglishNameQualification::qualifiedName( $name, $owner, $this->languages );
  }

  private function remove( string $code ): void {
    if ( ! $this->languages->isActive( $code ) || $code === $this->settings->defaultLanguage() ) {
        return;
    }
      $this->languages->deactivate( $code );
      $hidden = $this->settings->hiddenLanguages();
    if ( in_array( $code, $hidden, true ) ) {
        $this->settings->setHiddenLanguages( array_values( array_diff( $hidden, [ $code ] ) ) );
    }
  }


  private function setDefault( string $code ): void {
    if ( $this->languages->isActive( $code ) && $code !== $this->settings->defaultLanguage() ) {
        $this->settings->setDefaultLanguage( $code );
    }
  }


  private function setHidden( string $code, bool $hidden ): void {
    if ( ! $this->languages->isActive( $code ) || ( $hidden && $code === $this->settings->defaultLanguage() ) ) {
        return;
    }
      $current = $this->settings->hiddenLanguages();
    if ( $hidden ) {
      if ( ! in_array( $code, $current, true ) ) {
          $current[] = $code;
      }
    } else {
        $current = array_values( array_diff( $current, [ $code ] ) );
    }
      $this->settings->setHiddenLanguages( $current );
  }


  private function saveFields( string $code, array $fields ): ?Result {
    if ( ! $this->languages->isActive( $code ) ) {
        return null;
    }

      $tag = null;
    if ( array_key_exists( 'hreflang', $fields ) ) {
        $tag = $fields['hreflang'] === null ? '' : trim( (string) $fields['hreflang'] );
        $hreflangError = CustomLanguageFormat::validateHreflang( $tag );
      if ( '' !== $hreflangError ) {
          return Result::error( $hreflangError, [ 'code' => $code, 'hreflang' => $tag, 'displayCode' => $code ] );
      }
    }

    if ( ! empty( $fields['flag'] ) ) {
        $flagError = $this->guardFlag( $code, (string) $fields['flag'] );
      if ( null !== $flagError ) {
          return $flagError;
      }
    }

    if ( $tag !== null ) {
        $this->languages->setTag( $code, '' === $tag ? null : $tag );
    }
    if ( array_key_exists( 'encodeUrl', $fields ) ) {
        $this->languages->setEncodeUrl( $code, (bool) $fields['encodeUrl'] );
    }
    if ( array_key_exists( 'isRtl', $fields ) ) {
        $rtl = self::authoredDirection( $fields, 'isRtl' );
        $this->languages->setRtl( $code, null === $rtl ? null : 1 === $rtl );
    }
    if ( ! empty( $fields['flag'] ) ) {
        $this->languages->setFlag( $code, (string) $fields['flag'] );
    }

      return null;
  }


  private static function presetCodesIn( array $languages ): array {
      $presetCodes = [];
    foreach ( $languages as $row ) {
        $row        = (array) $row;
        $presetCode = isset( $row['presetCode'] ) && $row['presetCode'] !== null
          ? strtolower( trim( (string) $row['presetCode'] ) )
          : '';
      if ( $presetCode !== '' ) {
          $presetCodes[] = $presetCode;
      }
    }

      return $presetCodes;
  }


  private static function keptDefault( array $desired, string $defaultCode, array $current, string $storedDefault ): string {
    if ( $defaultCode === '' || isset( $desired[ $defaultCode ] ) || in_array( $defaultCode, $current, true ) || $desired === [] ) {
        return $defaultCode;
    }
    if ( $storedDefault !== '' && isset( $desired[ $storedDefault ] ) ) {
        return $storedDefault;
    }
      $codes = array_keys( $desired );
    foreach ( $codes as $code ) {
      if ( strtolower( $code ) === strtolower( $defaultCode ) ) {
          return $code;
      }
    }

      return $codes[0];
  }


  private function orderForAdd( array $languages, string $defaultCode ): array {
      $first   = [];
      $customs = [];
      $rest    = [];
    foreach ( $languages as $row ) {
        $rowArr = (array) $row;
        $code   = isset( $rowArr['code'] ) ? (string) $rowArr['code'] : '';
      if ( $code !== '' && $code === $defaultCode ) {
          $first[] = $row;
      } elseif ( ! empty( $rowArr['custom'] ) ) {
          $customs[] = $row;
      } else {
          $rest[] = $row;
      }
    }

      return array_merge( $first, $customs, $rest );
  }



	private function guardFlag( string $code, string $flag ) {
		if ( preg_match( '#^(https?:)?//#', $flag ) ) {
			return null;
		}
		$offered = $this->languages->offeredFlagsFor( $code );
		if ( null === $offered ) {
			return null;
		}
		$file = strtolower( trim( $flag ) );
		if ( '' !== $file && '@code' !== $file && '.svg' !== substr( $file, -4 ) ) {
			$file .= '.svg';
		}
		$shipped = \WPML\LanguageEditor\Flags\FlagFile::normalize( $file );
		if ( '' !== $shipped ) {
			$file = $shipped;
		}
		if ( in_array( $file, $offered, true ) ) {
			return null;
		}

		return Result::error( 'flag_not_offered', [ 'code' => $code, 'flag' => $flag ] );
	}


  private static function authoredDirection( array $data, string $key ): ?int {
      if ( ! array_key_exists( $key, $data ) || null === $data[ $key ] || '' === $data[ $key ] ) {
          return null;
      }

      return filter_var( $data[ $key ], FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
  }
}
