<?php

namespace WPML\Legacy\Component\Translator\Domain\Query;

use WPML\Core\SharedKernel\Component\Translator\Domain\LanguagePair;
use WPML\Core\SharedKernel\Component\Translator\Domain\Query\TranslatorsQueryInterface;
use WPML\Core\SharedKernel\Component\Translator\Domain\Translator;

class TranslatorsQuery implements TranslatorsQueryInterface {

  private $records;

  private $translators;

  private $selfTranslatorProvider;


  public function __construct( ?SelfTranslatorProvider $selfTranslatorProvider = null ) {
    $this->records = \WPML\Container\make( \WPML_Translator_Records::class );
    $this->selfTranslatorProvider = $selfTranslatorProvider ?: new SelfTranslatorProvider();
  }


  public function get() {
    if ( is_array( $this->translators ) ) {
      return array_values( $this->translators );
    }

    $this->translators = [];

    $translatorsData = $this->records->get_users_with_capability();
    if ( ! is_array( $translatorsData ) ) {
      return [];
    }

    foreach ( $translatorsData as $translator ) {
      if ( ! property_exists( $translator, 'language_pairs' ) || ! is_array( $translator->language_pairs ) ) {
        continue;
      }

      $languagePairs = [];
      foreach ( $translator->language_pairs as $from => $to ) {
        $languagePairs[] = new LanguagePair( $from, $to );
      }

      $id = (int) $translator->ID;
      $this->translators[$id] = new Translator(
        $translator->ID,
        $translator->display_name,
        $translator->user_nicename,
        $languagePairs
      );
    }

    return array_values( $this->translators );
  }


  public function getById( int $id ) {
    $this->get();

    $translators = $this->translators;

    if ( array_key_exists( $id, $translators ) ) {
      return $translators[ $id ];
    }

    $currentUserTranslator = $this->getSelfTranslatorProvider()->get();

    if ( ! $currentUserTranslator || $currentUserTranslator->getId() !== $id ) {
      return null;
    }

    return $currentUserTranslator;
  }


  public function getCurrentlyLoggedId() {
    $currentUser = \wp_get_current_user();

    if ( $currentUser->ID === 0 ) {
      return null;
    }

    return $this->getById( $currentUser->ID );
  }


  private function getSelfTranslatorProvider(): SelfTranslatorProvider {
    if ( ! $this->selfTranslatorProvider ) {
      $this->selfTranslatorProvider = new SelfTranslatorProvider();
    }

    return $this->selfTranslatorProvider;
  }


}
