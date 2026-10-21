<?php

namespace WPML\Legacy\Component\Translation\Sender;

use WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface;
use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomyTermPairClaimRepositoryInterface;
use WPML\Legacy\Component\Translation\Sender\Taxonomy\TaxonomyTermPairClaimRepository;
use WPML\Core\Component\Translation\Domain\HowToHandleExistingTranslationType;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Element;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TargetLanguage;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TranslationBatch;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\CompletedTranslationValidator;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\IgnoredElement;
use WPML\Core\Component\Translation\Domain\TranslationMethod\AutomaticMethod;
use WPML\Core\Component\Translation\Domain\TranslationType;

class TaxonomyTermBatchSender {

  private $wpdb;

  private $translationQuery;

  const IGNORED_ELEMENT_REASON_IN_PROGRESS = 'content_already_in_progress';

  const REFUSAL_REASONS = [
    'up_to_date'       => CompletedTranslationValidator::IGNORED_ELEMENT_REASON,
    'being_translated' => self::IGNORED_ELEMENT_REASON_IN_PROGRESS,
    'review_open'      => self::IGNORED_ELEMENT_REASON_IN_PROGRESS,
  ];

  private $dispatcherFactory;

  private $dispatcher;

  private $claimRepository;


  public function __construct(
    \wpdb $wpdb,
    TranslationQueryInterface $translationQuery,
    $dispatcherFactory = null,
    ?TaxonomyTermPairClaimRepositoryInterface $claimRepository = null
  ) {
    $this->wpdb              = $wpdb;
    $this->translationQuery  = $translationQuery;
    $this->dispatcherFactory = $dispatcherFactory;
    $this->claimRepository   = $claimRepository ?: new TaxonomyTermPairClaimRepository( $wpdb );
  }


  public function send( TranslationBatch $batch ): array {
    $jobs            = [];
    $preparedByJobId = [];
    $ignored         = [];
    $failed          = [];
    $candidates      = [];
    $seenPairs       = [];
    $owner           = $batch->getOperationId() ?: 'send-' . wp_generate_uuid4();

    foreach ( $batch->getTargetLanguages() as $targetLanguage ) {
      foreach ( $targetLanguage->getElements() as $element ) {
        if ( ! $this->isTerm( $element ) ) {
          continue;
        }

        if ( ! $targetLanguage->getMethod() instanceof AutomaticMethod ) {
          $ignored[] = new IgnoredElement(
            $element->getType(),
            $element->getElementId(),
            $targetLanguage->getLanguageCode(),
            $targetLanguage->getMethod(),
            sprintf(
              /* translators: %s is a target language code. */
              __( 'Taxonomy terms were not sent to %s because terms currently support Automatic translation only.', 'wpml' ),
              $targetLanguage->getLanguageCode()
            )
          );
          continue;
        }

        if ( $element->getOriginalLanguageCode() === $targetLanguage->getLanguageCode() ) {
          continue;
        }

        $pairKey = $element->getElementId() . '|' . $targetLanguage->getLanguageCode();
        if ( isset( $seenPairs[ $pairKey ] ) ) {
          continue;
        }
        $seenPairs[ $pairKey ] = true;
        $candidates[]          = [ $element, $targetLanguage ];
      }
    }

    $pairsOf = function ( array $list ): array {
      return array_map(
        function ( array $candidate ): array {
          return [ $candidate[0]->getElementId(), $candidate[1]->getLanguageCode() ];
        },
        $list
      );
    };
    $claimedKeys = $candidates ? $this->claimRepository->claimMany( $pairsOf( $candidates ), $owner ) : [];
    $claimed     = [];

    foreach ( $candidates as $candidate ) {
      $element        = $candidate[0];
      $targetLanguage = $candidate[1];

      if ( ! isset( $claimedKeys[ $element->getElementId() . '|' . $targetLanguage->getLanguageCode() ] ) ) {
        $failed[] = [
          'elementType'    => $element->getType()->get(),
          'elementId'      => $element->getElementId(),
          'targetLanguage' => $targetLanguage->getLanguageCode(),
          'reason'         => __( 'This taxonomy term is already being sent in another translation operation. Try again.', 'wpml' ),
          'kind'           => 'remaining',
          'errorCode'      => 'taxonomy_term_claimed',
        ];
        continue;
      }
      $claimed[] = $candidate;

      $taxonomy = $this->taxonomyOf( $element->getElementId() );

      if ( null === $taxonomy ) {
        $ignored[] = new IgnoredElement(
          $element->getType(),
          $element->getElementId(),
          $targetLanguage->getLanguageCode(),
          $targetLanguage->getMethod(),
          'The term no longer exists.'
        );
        continue;
      }

      $preparedJob = $this->dispatcher()->prepareTermJobWithRecord(
        $element->getElementId(),
        $taxonomy,
        $element->getOriginalLanguageCode(),
        $targetLanguage->getLanguageCode(),
        HowToHandleExistingTranslationType::HANDLE_EXISTING_OVERRIDE === $batch->getHowToHandleExisting(),
        '' !== (string) $batch->getOperationId()
      );

      $lastRefusal = null === $preparedJob ? $this->dispatcher()->getLastRefusal() : null;
      if ( 'dispatch_uncertain' === $lastRefusal ) {
        $failed[] = [
          'elementType'    => $element->getType()->get(),
          'elementId'      => $element->getElementId(),
          'targetLanguage' => $targetLanguage->getLanguageCode(),
          'reason'         => __( 'Our translation system may already have received this taxonomy term. It was not sent again to avoid a duplicate charge.', 'wpml' ),
          'kind'           => 'remaining',
          'errorCode'      => 'taxonomy_term_dispatch_uncertain',
        ];
        continue;
      }

      $refusal = null === $preparedJob ? $this->refusalReason() : null;
      if ( null !== $refusal ) {
        $ignored[] = new IgnoredElement(
          $element->getType(),
          $element->getElementId(),
          $targetLanguage->getLanguageCode(),
          $targetLanguage->getMethod(),
          $refusal
        );
        continue;
      }

      if ( null === $preparedJob || null === $preparedJob->getModel() ) {
        $failed[] = [
          'elementType'    => $element->getType()->get(),
          'elementId'      => $element->getElementId(),
          'targetLanguage' => $targetLanguage->getLanguageCode(),
          'reason'         => __( 'The taxonomy term could not be prepared for translation. Try again.', 'wpml' ),
          'kind'           => 'rejected',
          'errorCode'      => 'taxonomy_term_preparation_failed',
        ];
        continue;
      }

      $job = $preparedJob->getModel();
      $jobs[] = $job;
      $preparedByJobId[ $job->id ] = [
        'element'  => $element,
        'target'   => $targetLanguage,
        'prepared' => $preparedJob,
      ];
    }

    if ( ! $jobs ) {
      if ( $claimed ) {
        $this->claimRepository->releaseMany( $pairsOf( $claimed ), $owner );
      }

      return [ [], $ignored, $failed ];
    }

    $dispatchResult = $this->dispatcher()->dispatchToAte( $jobs );

    $retryableJobIds      = array_merge(
      $dispatchResult->getRejectedJobIds(),
      $dispatchResult->getRemainingJobIds()
    );
    $rejectedPreparedJobs = [];
    foreach ( $retryableJobIds as $jobId ) {
      if ( isset( $preparedByJobId[ $jobId ] ) ) {
        $rejectedPreparedJobs[] = $preparedByJobId[ $jobId ]['prepared'];
      }
    }
    $this->dispatcher()->releasePreparedJobs( $rejectedPreparedJobs );

    $this->claimRepository->releaseMany( $pairsOf( $claimed ), $owner );

    foreach ( $dispatchResult->getRejectedJobIds() as $jobId ) {
      if ( ! isset( $preparedByJobId[ $jobId ] ) ) {
        continue;
      }

      $prepared = $preparedByJobId[ $jobId ];
      $failed[] = [
        'elementType'    => $prepared['element']->getType()->get(),
        'elementId'      => $prepared['element']->getElementId(),
        'targetLanguage' => $prepared['target']->getLanguageCode(),
        'reason'         => __( 'The taxonomy term was not accepted by our translation system. Try again.', 'wpml' ),
        'kind'           => 'rejected',
        'errorCode'      => $dispatchResult->getErrorCode() ?: 'taxonomy_term_rejected',
      ];
    }

    foreach ( $dispatchResult->getBlockedJobIds() as $jobId ) {
      if ( ! isset( $preparedByJobId[ $jobId ] ) ) {
        continue;
      }

      $prepared = $preparedByJobId[ $jobId ];
      $failed[] = [
        'elementType'    => $prepared['element']->getType()->get(),
        'elementId'      => $prepared['element']->getElementId(),
        'targetLanguage' => $prepared['target']->getLanguageCode(),
        'reason'         => __( 'The taxonomy term is waiting for automatic translation to become available.', 'wpml' ),
        'kind'           => 'blocked',
        'errorCode'      => $dispatchResult->getErrorCode() ?: 'taxonomy_term_blocked',
      ];
    }

    foreach ( $dispatchResult->getUncertainJobIds() as $jobId ) {
      if ( ! isset( $preparedByJobId[ $jobId ] ) ) {
        continue;
      }

      $prepared = $preparedByJobId[ $jobId ];
      $failed[] = [
        'elementType'    => $prepared['element']->getType()->get(),
        'elementId'      => $prepared['element']->getElementId(),
        'targetLanguage' => $prepared['target']->getLanguageCode(),
        'reason'         => __( 'Our translation system may already have received this taxonomy term. It was not sent again to avoid a duplicate charge.', 'wpml' ),
        'kind'           => 'remaining',
        'errorCode'      => $dispatchResult->getErrorCode() ?: 'taxonomy_term_dispatch_uncertain',
      ];
    }

    foreach ( $dispatchResult->getRemainingJobIds() as $jobId ) {
      if ( ! isset( $preparedByJobId[ $jobId ] ) ) {
        continue;
      }

      $prepared = $preparedByJobId[ $jobId ];
      $failed[] = [
        'elementType'    => $prepared['element']->getType()->get(),
        'elementId'      => $prepared['element']->getElementId(),
        'targetLanguage' => $prepared['target']->getLanguageCode(),
        'reason'         => __( 'The taxonomy term was not sent because an earlier translation batch failed. Try again.', 'wpml' ),
        'kind'           => 'remaining',
        'errorCode'      => 'taxonomy_term_remaining',
      ];
    }

    $acceptedJobIds = $dispatchResult->getAcceptedJobIds();

    return [
      $acceptedJobIds ? $this->translationQuery->getManyByJobIds( $acceptedJobIds ) : [],
      $ignored,
      $failed,
    ];
  }


  private function isTerm( Element $element ): bool {
    return $element->getType()->get() === TranslationType::TAXONOMY;
  }


  private function taxonomyOf( int $termTaxonomyId ) {
    $wpdb = $this->wpdb;

    $sql = $wpdb->prepare(
      "SELECT taxonomy FROM {$wpdb->prefix}term_taxonomy WHERE term_taxonomy_id = %d",
      $termTaxonomyId
    );

    if ( ! is_string( $sql ) ) {
      return null;
    }

    $taxonomy = $wpdb->get_var( $sql );

    return is_string( $taxonomy ) && '' !== $taxonomy ? $taxonomy : null;
  }


  private function refusalReason(): ?string {
    $refusal = $this->dispatcher()->getLastRefusal();

    return is_string( $refusal ) ? ( self::REFUSAL_REASONS[ $refusal ] ?? null ) : null;
  }


  private function dispatcher() {
    if ( null === $this->dispatcher ) {
      $this->dispatcher = null !== $this->dispatcherFactory
        ? call_user_func( $this->dispatcherFactory )
        : new \WPML\TM\ATE\TranslateEverything\TaxonomyTermJobDispatcher( $this->wpdb );
    }

    return $this->dispatcher;
  }

}
