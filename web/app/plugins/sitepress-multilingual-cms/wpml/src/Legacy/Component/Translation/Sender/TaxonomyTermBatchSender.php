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
use WPML\Legacy\Component\Translation\Sender\Taxonomy\CarriedTermPairs;

class TaxonomyTermBatchSender {

  private $wpdb;

  private $translationQuery;

  const IGNORED_ELEMENT_REASON_IN_PROGRESS = 'content_already_in_progress';

  const REFUSAL_REASONS = [
    'up_to_date'       => CompletedTranslationValidator::IGNORED_ELEMENT_REASON,
    'being_translated' => self::IGNORED_ELEMENT_REASON_IN_PROGRESS,
    'review_open'      => self::IGNORED_ELEMENT_REASON_IN_PROGRESS,
    'unsupported_pair' => self::IGNORED_ELEMENT_REASON_UNSUPPORTED_PAIR,
  ];

  const IGNORED_ELEMENT_REASON_UNSUPPORTED_PAIR = 'automatic_translation_not_supported';

  private $dispatcherFactory;

  private $dispatcher;

  private $claimRepository;

  private $assignedTranslatorsValidator;


  public function __construct(
    \wpdb $wpdb,
    TranslationQueryInterface $translationQuery,
    $dispatcherFactory = null,
    ?TaxonomyTermPairClaimRepositoryInterface $claimRepository = null,
    ?\WPML\Core\Component\Translation\Domain\TranslationMethod\Validator\AssignedTranslatorsValidator $assignedTranslatorsValidator = null
  ) {
    $this->wpdb                       = $wpdb;
    $this->translationQuery           = $translationQuery;
    $this->dispatcherFactory          = $dispatcherFactory;
    $this->claimRepository            = $claimRepository ?: new TaxonomyTermPairClaimRepository( $wpdb );
    $this->assignedTranslatorsValidator = $assignedTranslatorsValidator;
  }


  public function send( TranslationBatch $batch ): array {
    CarriedTermPairs::clear();
    try {
      return $this->sendWithCarriedCheck( $batch );
    } finally {
      CarriedTermPairs::clear();
    }
  }


  private function sendWithCarriedCheck( TranslationBatch $batch ): array {
    $jobs            = [];
    $preparedByJobId = [];
    $members         = [];
    $ignored         = [];
    $failed          = [];
    $candidates      = [];
    $seenPairs       = [];
    $tpLanguages     = [];
    $owner           = $batch->getOperationId() ?: 'send-' . wp_generate_uuid4();

    foreach ( $batch->getTargetLanguages() as $targetLanguage ) {
      if ( $targetLanguage->getMethod() instanceof \WPML\Core\Component\Translation\Domain\TranslationMethod\TranslationServiceMethod ) {
        $tpLanguages[ $targetLanguage->getLanguageCode() ] = true;
      }
      foreach ( $targetLanguage->getElements() as $element ) {
        if ( ! $this->isTerm( $element ) ) {
          continue;
        }

        $method = $targetLanguage->getMethod();
        if ( ! $method instanceof AutomaticMethod
          && ! $method instanceof \WPML\Core\Component\Translation\Domain\TranslationMethod\LocalTranslatorMethod
          && ! $method instanceof \WPML\Core\Component\Translation\Domain\TranslationMethod\TranslationServiceMethod ) {
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

        if ( $method instanceof \WPML\Core\Component\Translation\Domain\TranslationMethod\LocalTranslatorMethod
          && ! $this->translatorAvailable( $method, $element->getOriginalLanguageCode() ) ) {
          $failed[] = [
            'elementType'    => $element->getType()->get(),
            'elementId'      => $element->getElementId(),
            'targetLanguage' => $targetLanguage->getLanguageCode(),
            'reason'         => __( 'The assigned translator is no longer available for this language pair. Choose another translator and try again.', 'wpml' ),
            'kind'           => 'rejected',
            'errorCode'      => 'translator_not_available',
          ];
          continue;
        }

        $candidates[] = [ $element, $targetLanguage ];
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
    $carriedPairs = CarriedTermPairs::find( $this->wpdb, $pairsOf( $candidates ) );
    if ( CarriedTermPairs::failed() ) {
      foreach ( $candidates as $candidate ) {
        $element        = $candidate[0];
        $targetLanguage = $candidate[1];
        $failed[] = [
          'elementType'    => $element->getType()->get(),
          'elementId'      => $element->getElementId(),
          'targetLanguage' => $targetLanguage->getLanguageCode(),
          'reason'         => __( 'The taxonomy term could not be checked for an existing post translation job. Try again.', 'wpml' ),
          'kind'           => 'rejected',
          'errorCode'      => 'taxonomy_term_carried_check_failed',
        ];
      }
      $candidates = [];
    } elseif ( $carriedPairs ) {
      $eligible = [];
      foreach ( $candidates as $candidate ) {
        $element        = $candidate[0];
        $targetLanguage = $candidate[1];
        $pairKey        = $element->getElementId() . '|' . $targetLanguage->getLanguageCode();
        if ( isset( $carriedPairs[ $pairKey ] ) ) {
          $ignored[] = new IgnoredElement(
            $element->getType(),
            $element->getElementId(),
            $targetLanguage->getLanguageCode(),
            $targetLanguage->getMethod(),
            $this->carriedReason( $carriedPairs[ $pairKey ] )
          );
        } else {
          $eligible[] = $candidate;
        }
      }
      $candidates = $eligible;
    }

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

      $automatic = $targetLanguage->getMethod() instanceof AutomaticMethod;
      $overwrite = HowToHandleExistingTranslationType::HANDLE_EXISTING_OVERRIDE === $batch->getHowToHandleExisting();
      $preparedJob = $automatic
        ? $this->dispatcher()->prepareTermBundleMember(
          $element->getElementId(),
          $taxonomy,
          $element->getOriginalLanguageCode(),
          $targetLanguage->getLanguageCode(),
          $overwrite
        )
        : $this->dispatcher()->prepareTermJobWithRecord(
          $element->getElementId(),
          $taxonomy,
          $element->getOriginalLanguageCode(),
          $targetLanguage->getLanguageCode(),
          $overwrite,
          '' !== (string) $batch->getOperationId(),
          $targetLanguage->getMethod() instanceof \WPML\Core\Component\Translation\Domain\TranslationMethod\LocalTranslatorMethod
            ? $targetLanguage->getMethod()->getTranslatorId()
            : 0
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

      if ( $automatic && null !== $preparedJob ) {
        $members[] = $preparedJob;
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

    $acceptedPairs = [];
    if ( $members ) {
      $operationId = (string) $batch->getOperationId();
      if ( '' !== $operationId ) {
        if ( ! $this->bundleRounds()->hold( $operationId, $members ) ) {
          $this->dispatcher()->releasePreparedJobs( $members );
          foreach ( $members as $member ) {
            $failed[] = Taxonomy\TaxonomyTermBundleRounds::failedElement( [
              'member'    => $member,
              'kind'      => 'rejected',
              'reason'    => __( 'The taxonomy term could not be prepared for translation. Try again.', 'wpml' ),
              'errorCode' => 'taxonomy_term_preparation_failed',
            ] );
          }
        }
      } else {
        $dispatched = $this->bundleRounds()->dispatch( $members, false );
        foreach ( $dispatched['accepted'] as $member ) {
          $acceptedPairs[ $member->getTermTaxonomyId() . '|' . $member->getTargetLanguage() ] = true;
        }
        foreach ( $dispatched['failed'] as $failure ) {
          $failed[] = Taxonomy\TaxonomyTermBundleRounds::failedElement( $failure );
        }
      }
    }
    $memberTranslations = $this->translationsOfPairs( $acceptedPairs );

    if ( ! $jobs ) {
      if ( $claimed ) {
        $this->claimRepository->releaseMany( $pairsOf( $claimed ), $owner );
      }

      return [ $memberTranslations, $ignored, $failed ];
    }

    $ateJobs = array_values( array_filter( $jobs, function ( $job ) use ( $preparedByJobId ) {
      return ! $preparedByJobId[ $job->id ]['target']->getMethod() instanceof \WPML\Core\Component\Translation\Domain\TranslationMethod\TranslationServiceMethod;
    } ) );
    $tpJobs  = array_values( array_filter( $jobs, function ( $job ) use ( $preparedByJobId ) {
      return $preparedByJobId[ $job->id ]['target']->getMethod() instanceof \WPML\Core\Component\Translation\Domain\TranslationMethod\TranslationServiceMethod;
    } ) );
    $results = [];
    if ( $ateJobs ) {
      $results[] = $this->dispatcher()->dispatchToAte( $ateJobs );
    }
    if ( $tpJobs ) {
      \WPML\TM\TranslationProxy\TpBatchState::setRemoteTargetLanguages( array_keys( $tpLanguages ) );
      $operationId = (string) $batch->getOperationId();
      $results[] = $this->dispatcher()->dispatchToTp(
        $tpJobs,
        [ 'batchName' => $batch->getBatchName(), 'deadline' => $batch->getDeadline(), 'extraFields' => $batch->getTranslationServiceExtraFields() ],
        '' !== $operationId ? function ( string $name, int $id ) use ( $operationId ): bool {
          return ( new \WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomySendOperationRepository() )->recordTpBatch( $operationId, $name, $id );
        } : null
      );
    }
    $dispatchResult = $this->mergeDispatchResults( $results );

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
      array_merge( $acceptedJobIds ? $this->translationQuery->getManyByJobIds( $acceptedJobIds ) : [], $memberTranslations ),
      $ignored,
      $failed,
    ];
  }


  private function translationsOfPairs( array $pairs ): array {
    if ( ! $pairs ) {
      return [];
    }
    $termTaxonomyIds = array_values( array_unique( array_map( function ( string $pair ): int {
      return (int) strstr( $pair, '|', true );
    }, array_keys( $pairs ) ) ) );

    try {
      $translations = $this->translationQuery->getManyByElementIds( TranslationType::taxonomy(), $termTaxonomyIds );
    } catch ( \WPML\PHP\Exception\InvalidArgumentException $e ) {
      return [];
    }

    return array_values( array_filter(
      $translations,
      function ( $translation ) use ( $pairs ): bool {
        return isset( $pairs[ $translation->getOriginalElementId() . '|' . $translation->getTargetLanguageCode() ] );
      }
    ) );
  }


  private function bundleRounds(): Taxonomy\TaxonomyTermBundleRounds {
    return new Taxonomy\TaxonomyTermBundleRounds( $this->wpdb, $this->dispatcher() );
  }


  private function mergeDispatchResults( array $results ): \WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomyTermDispatchResult {
    $merged = new \WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomyTermDispatchResult( [], [], [] );
    foreach ( $results as $result ) {
      $merged = new \WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomyTermDispatchResult(
        $merged->getAcceptedMappings() + $result->getAcceptedMappings(),
        $merged->getBlockedMappings() + $result->getBlockedMappings(),
        array_merge( $merged->getRejectedJobIds(), $result->getRejectedJobIds() ),
        $merged->getErrorCode() ?: $result->getErrorCode(),
        $merged->getErrorMessage() ?: $result->getErrorMessage(),
        array_merge( $merged->getRemainingJobIds(), $result->getRemainingJobIds() ),
        array_merge( $merged->getUncertainJobIds(), $result->getUncertainJobIds() )
      );
    }

    return $merged;
  }


  private function translatorAvailable(
    \WPML\Core\Component\Translation\Domain\TranslationMethod\LocalTranslatorMethod $method,
    string $sourceLanguage
  ): bool {
    if ( null === $this->assignedTranslatorsValidator ) {
      $this->assignedTranslatorsValidator = \WPML\Container\make(
        \WPML\Core\Component\Translation\Domain\TranslationMethod\Validator\AssignedTranslatorsValidator::class
      );
    }

    try {
      return $this->assignedTranslatorsValidator->validate( [ $method ], $sourceLanguage );
    } catch ( \Throwable $e ) {
      return false;
    }
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


  private function carriedReason( array $carried ): string {
    $postId = $carried['postId'] ?? 0;
    $post   = $postId > 0 ? get_post( $postId ) : null;
    if ( $post instanceof \WP_Post ) {
      $type  = get_post_type_object( $post->post_type );
      $label = $type && isset( $type->labels->singular_name ) ? trim( (string) $type->labels->singular_name ) : '';
      $title = trim( $post->post_title );
      if ( '' !== $label && '' !== $title ) {
        return sprintf(
          /* translators: %1$s is the singular post type label, %2$s is the carrying post's title. */
          __( 'This taxonomy term is already being translated with the %1$s "%2$s".', 'wpml' ),
          $label,
          $title
        );
      }
    }

    return self::IGNORED_ELEMENT_REASON_IN_PROGRESS;
  }


  private function refusalReason(): ?string {
    $refusal = $this->dispatcher()->getLastRefusal();
    if ( 'carried_by_post' === $refusal ) {
      $postId = method_exists( $this->dispatcher(), 'getLastCarryingPostId' )
        ? $this->dispatcher()->getLastCarryingPostId()
        : null;

      return $this->carriedReason( [ 'postId' => (int) $postId ] );
    }

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
