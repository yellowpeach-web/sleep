<?php

namespace WPML\Legacy\Component\Translation\Sender;

use WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface;
use WPML\Core\Component\Translation\Domain\Sender\DuplicationSenderInterface;
use WPML\Core\Component\Translation\Domain\Translation;
use WPML\Core\Component\Translation\Domain\TranslationBatch\DuplicationBatch;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\IgnoredElement;
use WPML\Core\Component\Translation\Domain\TranslationMethod\DuplicateMethod;
use WPML\Core\Component\Translation\Domain\TranslationType;

class DuplicationSender implements DuplicationSenderInterface {

  const SEND_VIA_DASHBOARD = 6;

  private $legacyTranslationManagement;

  private $duplicationBatchMapper;

  private $translationQuery;

  private $ignoredElements = [];


  public function __construct(
    DuplicationBatchMapper $duplicationBatchMapper,
    TranslationQueryInterface $translationQuery
  ) {
    $this->legacyTranslationManagement = \wpml_load_core_tm();
    $this->duplicationBatchMapper      = $duplicationBatchMapper;
    $this->translationQuery            = $translationQuery;
  }


  public function send( DuplicationBatch $batch ): array {
    $legacyBatch = $this->duplicationBatchMapper->map( $batch );

    do_action(
      'wpml_tm_send_post_jobs',
      $legacyBatch,
      'post',
      self::SEND_VIA_DASHBOARD
    );

    $this->ignoredElements = array_map(
      function ( array $refused ) {
        return new IgnoredElement(
          TranslationType::post(),
          $refused['element_id'],
          $refused['language'],
          new DuplicateMethod(),
          $refused['reason']
        );
      },
      $this->legacyTranslationManagement->get_refused_duplications()
    );

    $translatedPostIds = $this->legacyTranslationManagement->get_sent_job_ids();
    if ( ! is_array( $translatedPostIds ) ) {
      return [];
    }

    if ( $translatedPostIds ) {
      return $this->translationQuery->getManyByTranslatedElementIds( $translatedPostIds );
    }

    return [];
  }


  public function getIgnoredElements(): array {
    return $this->ignoredElements;
  }


}
