<?php

namespace WPML\Core\Component\LanguageEditor\Application\Service;

use WPML\Core\Component\LanguageEditor\Application\Result;
use WPML\Core\Component\LanguageEditor\Domain\Bcp47;
use WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat;
use WPML\Core\Component\LanguageEditor\Domain\DisplayCodeDerivation;
use WPML\Core\Component\LanguageEditor\Domain\EnglishNameQualification;
use WPML\Core\Component\LanguageEditor\Domain\LanguageDerivation;
use WPML\Core\Component\LanguageEditor\Domain\LanguagePairKey;
use WPML\Core\Component\LanguageEditor\Domain\Repository\LanguageRepositoryInterface;

class AddLanguageService {

    private $languages;

    private $batchPresetCodes;


  public function __construct( LanguageRepositoryInterface $languages, array $batchPresetCodes = [] ) {
      $this->languages        = $languages;
      $this->batchPresetCodes = array_map(
        static function ( string $presetCode ): string {
            return strtolower( trim( $presetCode ) );
        },
        $batchPresetCodes
      );
  }


  public function run( string $presetCode, ?string $country, ?string $displayCode, ?bool $reviveLegacy = null ): Result {
      $check = $this->validate( $presetCode, $country, $displayCode, [], [], $reviveLegacy, [] );
    if ( ! $check->isOk() ) {
        return $check;
    }
      $ctx             = $check->payload();
      $code            = $ctx['code'];
      $storedCountry   = $ctx['storedCountry'];
      $resolvedDisplay = $ctx['resolvedDisplay'];
      $locale          = $ctx['locale'];
      $englishName     = $ctx['englishName'];
      $type            = $ctx['type'];
      $pair            = $ctx['pair'];
      $revived         = $ctx['revived'];
      $bcp47 = '' !== $ctx['bcp47'] ? $ctx['bcp47'] : null;

      $storedName = $ctx['retaken'] ? $this->languages->englishNameOf( $code ) : null;

    if ( $storedName !== null && $storedName !== '' ) {
        $englishName = $storedName;
    } else {
        $englishName = $this->uniqueEnglishName(
            $englishName,
            $code,
            $storedCountry,
            $this->hasBatchSibling( $presetCode, $storedCountry )
        );
    }

      $tag = null !== $bcp47 ? $bcp47 : str_replace( '_', '-', $locale );

      $existingId = $ctx['rowId'];

      $isRtl = null;

    if ( $existingId !== null ) {
        $this->languages->reactivate(
          $existingId,
          [
                'english_name'   => $englishName,
                'default_locale' => $locale,
                'tag'            => $tag,
                'country'        => $storedCountry,
                'type'           => $type,
                'display_code'   => $resolvedDisplay,
                'bcp_47'         => $bcp47,
                'is_rtl'         => $isRtl,
            ]
        );
    } else {
        $languageId = $this->languages->insert(
          [
                'code'           => $code,
                'english_name'   => $englishName,
                'default_locale' => $locale,
                'tag'            => $tag,
                'country'        => $storedCountry,
                'type'           => $type,
                'display_code'   => $resolvedDisplay,
                'bcp_47'         => $bcp47,
                'is_rtl'         => $isRtl,
            ]
        );

      if ( ! $languageId ) {
          return Result::error( 'insert_failed', [ 'code' => $code, 'displayCode' => $resolvedDisplay ] );
      }
    }

    if ( ! $revived || ! $this->languages->hasFlag( $code ) ) {
        $this->languages->setFlag( $code, str_replace( '.svg', '', $pair['flag'] ) );
    }

      return Result::ok(
        [
              'code'        => $code,
              'displayCode' => $resolvedDisplay,
              'active'      => true,
          ]
      );
  }


  private function storedDisplayCode( string $code ): string {
      $stored = $this->languages->displayCodeOf( $code );

      return $stored !== null && trim( $stored ) !== '' ? $stored : $code;
  }

  public function validate( string $presetCode, ?string $country, ?string $displayCode, array $claimedDisplayCodes = [], array $claimedLocales = [], ?bool $reviveLegacy = null, array $claimedBcp47Tags = [] ): Result {
      $presetCode = strtolower( trim( $presetCode ) );
      $country    = $country === null || $country === '' ? null : $country;

    if ( $displayCode !== null && trim( $displayCode ) === '' ) {
        return Result::error( 'code_required' );
    }
      $displayCode = $displayCode === null ? null : strtolower( $displayCode );

    if ( $presetCode === '' ) {
        return Result::error( 'missing_preset', [ 'displayCode' => $displayCode ] );
    }

    if ( $displayCode !== null ) {
        $codeError = CustomLanguageFormat::validateCode( $displayCode );
      if ( $codeError !== '' ) {
          return Result::error( $codeError, [ 'displayCode' => $displayCode ] );
      }
    }

      $preset          = $this->languages->preset( $presetCode );
      $presetUnvouched = false;
    if ( $preset === null ) {
        $preset          = $this->languages->preset( $presetCode, true );
        $presetUnvouched = $preset !== null;
    }
      $script = $preset && ! empty( $preset['script'] ) ? (string) $preset['script'] : null;
      $language = $preset !== null && isset( $preset['language'] ) && (string) $preset['language'] !== ''
        ? (string) $preset['language']
        : LanguagePairKey::languageSubtag( $presetCode, $script );

    if ( $preset ) {
        $mode = isset( $preset['country_mode'] ) ? $preset['country_mode'] : 'optional';
      if ( $mode === 'required' && $country === null ) {
          return Result::error( 'country_required' );
      }
      if ( $mode === 'none' && $country !== null ) {
          return Result::error( 'country_not_allowed' );
      }
    }

      $pair          = $this->languages->presetPair( $presetCode, $country );
      $pairUnvouched = false;
    if ( $pair === null ) {
        $pair          = $this->languages->presetPair( $presetCode, $country, true );
        $pairUnvouched = $pair !== null;
    }
    if ( $pair === null ) {
        return Result::error( 'country_not_allowed', [ 'country' => $country ] );
    }
      $code          = $pair['code'];
      $storedCountry = $pair['country'];

      $incomingPair = LanguagePairKey::forPreset( $language, $script, $storedCountry );
    foreach ( $this->languages->activePairs() as $activePair ) {
      if ( LanguagePairKey::forHead( LanguagePairKey::headOf( $activePair ), $activePair['country'] ) === $incomingPair ) {
          return Result::error(
            'language_already_active',
            [
                  'code'        => $code,
                  'displayCode' => $this->storedDisplayCode( $activePair['code'] ),
              ]
          );
      }
    }

    if ( in_array( $code, $this->languages->activeCodes(), true ) ) {
        return Result::error(
          'language_already_active',
          [
                'code'        => $code,
                'displayCode' => $this->storedDisplayCode( $code ),
            ]
        );
    }

      $retaken = false;
      $revived = false;
    foreach ( $this->languages->inactivePairs() as $inactivePair ) {
      if ( LanguagePairKey::forHead( LanguagePairKey::headOf( $inactivePair ), $inactivePair['country'] ) !== $incomingPair ) {
            continue;
      }
      if ( ! $this->languages->hasRetainedContent( $inactivePair['code'] ) ) {
            continue;
      }
        $code    = $inactivePair['code'];
        $retaken = true;
        break;
    }

    if ( ! $retaken && $storedCountry !== null ) {
        $incomingHead = LanguageDerivation::languageHead( LanguageDerivation::baseIdentifier( $language, $script ) );
        $legacy       = $this->reviveCandidate( $incomingHead );
      if ( $legacy !== null ) {
        if ( $reviveLegacy === null ) {
            return Result::error( 'revive_decision_required', [ 'legacyCode' => $legacy['code'] ] );
        }
        if ( $reviveLegacy === true ) {
            $code    = $legacy['code'];
            $retaken = true;
            $revived = true;
        }
      }
    }

      $existingId = $this->languages->rowId( $code );

    if ( ( $pairUnvouched || $presetUnvouched ) && $existingId === null ) {
        return Result::error(
          'pair_unvouched',
          [
                'code'        => $code,
                'country'     => $storedCountry,
                'customRoute' => $this->languages->withdrawnCatalogueCode( $code ) !== null,
            ]
        );
    }

    if ( $existingId !== null && $this->languages->isCustomIdentity( $code ) ) {
        return Result::error(
          'code_held_by_custom_language',
          [
                'code'        => $code,
                'displayCode' => $this->storedDisplayCode( $code ),
                'englishName' => $this->languages->englishNameOf( $code ) ?? '',
            ]
        );
    }

      $activeDisplayCodes = array_map( 'strtolower', $this->languages->activeDisplayCodes() );
      $removedTokens      = $this->removedWithContentTokens( $code );
      $takenDisplayCodes  = array_merge(
        $activeDisplayCodes,
        $claimedDisplayCodes,
        $removedTokens
      );
      $sentIsRemovedToken = $displayCode !== null
        && in_array( $displayCode, $removedTokens, true );
      $resolvedDisplay    = $displayCode !== null && ! $sentIsRemovedToken
        ? $displayCode
        : DisplayCodeDerivation::shortestUnambiguous(
            $language,
            $storedCountry,
            $code,
            $takenDisplayCodes,
            $this->sameLanguageActive( $language ) || $this->sameLanguageRemovedWithContent( $language, $code )
        );
    if ( in_array( strtolower( $resolvedDisplay ), array_merge( $activeDisplayCodes, $claimedDisplayCodes ), true ) ) {
        return Result::error( 'display_code_in_use', [ 'displayCode' => $resolvedDisplay ] );
    }


      $bcp47 = Bcp47::compose( $language, $script, $storedCountry );

    if ( '' !== $bcp47 ) {
        $takenTags = array_merge( array_map( 'strtolower', $this->languages->activeBcp47Tags() ), $claimedBcp47Tags );
      if ( in_array( strtolower( $bcp47 ), $takenTags, true ) ) {
          return Result::error( 'bcp47_in_use', [ 'code' => $code, 'bcp47' => $bcp47 ] );
      }
    }

      $englishName = $preset && ! empty( $preset['english_name'] ) ? (string) $preset['english_name'] : $code;
      $type        = $preset && ! empty( $preset['type'] ) ? (string) $preset['type'] : 'national';


      return Result::ok(
        [
              'code'            => $code,
              'storedCountry'   => $storedCountry,
              'resolvedDisplay' => $resolvedDisplay,
              'locale'          => $pair['default_locale'],
              'englishName'     => $englishName,
              'type'            => $type,
              'bcp47'           => $bcp47,
              'pair'            => $pair,
              'revived'         => $revived,
              'rowId'           => $existingId,
              'retaken'         => $retaken,
          ]
      );
  }




  private function reviveCandidate( string $incomingHead ) {
    foreach ( $this->languages->inactivePairs() as $inactivePair ) {
        $rowCountry = isset( $inactivePair['country'] ) ? $inactivePair['country'] : null;
      if ( $rowCountry !== null && $rowCountry !== '' ) {
            continue;
      }
      if ( strtolower( LanguagePairKey::headOf( $inactivePair ) ) !== strtolower( $incomingHead ) ) {
            continue;
      }
      if ( ! $this->languages->hasRetainedContent( $inactivePair['code'] ) ) {
            continue;
      }
      if ( $this->languages->isCustomIdentity( $inactivePair['code'] ) ) {
            continue;
      }

        return $inactivePair;
    }

      return null;
  }


  private function hasBatchSibling( string $presetCode, ?string $storedCountry ): bool {
    if ( $storedCountry === null || $storedCountry === '' ) {
        return false;
    }

      $presetCode = strtolower( trim( $presetCode ) );
    if ( $presetCode === '' ) {
        return false;
    }

      $siblings = 0;
    foreach ( $this->batchPresetCodes as $batchPresetCode ) {
      if ( $batchPresetCode === $presetCode ) {
          $siblings++;
      }
    }

      return $siblings > 1;
  }


  private function sameLanguageActive( string $language ): bool {
      $language = strtolower( trim( $language ) );
    if ( '' === $language ) {
        return false;
    }

    foreach ( $this->languages->activePairs() as $activePair ) {
        $head = strtolower( LanguagePairKey::headOf( $activePair ) );
      if ( strpos( $head . '-', $language . '-' ) === 0 ) {
          return true;
      }
    }

      return false;
  }


  private function sameLanguageRemovedWithContent( string $language, string $code ): bool {
      $language = strtolower( trim( $language ) );
    if ( '' === $language ) {
        return false;
    }

      $self = strtolower( trim( $code ) );

    foreach ( $this->languages->inactivePairs() as $inactivePair ) {
      if ( strtolower( $inactivePair['code'] ) === $self ) {
            continue;
      }
        $head = strtolower( LanguagePairKey::headOf( $inactivePair ) );
      if ( strpos( $head . '-', $language . '-' ) !== 0 ) {
            continue;
      }
      if ( $this->languages->hasRetainedContent( $inactivePair['code'] ) ) {
          return true;
      }
    }

      return false;
  }


  private function removedWithContentTokens( string $code ): array {
      $self   = strtolower( trim( $code ) );
      $tokens = [];

    foreach ( $this->languages->removedWithContentDisplayCodes() as $rowCode => $token ) {
      if ( strtolower( $rowCode ) === $self ) {
            continue;
      }
        $tokens[] = strtolower( $token );
    }

      return array_values( array_unique( $tokens ) );
  }


  private function uniqueEnglishName( $name, $code, $country, $mustQualify = false ) {
    if ( ! $mustQualify ) {
        $owner = $this->languages->englishNameOwner( $name, $code );
      if ( $owner === null ) {
          return $name;
      }

      if ( empty( $owner['active'] ) && ! $this->languages->hasRetainedContent( $owner['code'] ) ) {
          $this->languages->renameEnglishName(
              $owner['id'],
              EnglishNameQualification::qualifiedName( $name, $owner, $this->languages )
          );

          return $name;
      }
    }

      $candidate = EnglishNameQualification::composedName( $name, $country, $code, $this->languages );
    if ( ! $this->languages->englishNameTaken( $candidate, $code ) ) {
        return $candidate;
    }

      $holder = $this->languages->englishNameOwner( $candidate, $code );
    if ( $holder !== null && empty( $holder['active'] ) && ! $this->languages->hasRetainedContent( $holder['code'] ) ) {
        $this->languages->renameEnglishName(
            $holder['id'],
            EnglishNameQualification::yieldedName( $candidate, $holder, $this->languages )
        );

        return $candidate;
    }

      $base = $candidate;
      $n    = 2;
    while ( $this->languages->englishNameTaken( $candidate, $code ) ) {
        $candidate = $base . ' ' . $n;
        $n++;
    }

      return $candidate;
  }
}
