<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

use WPML\Core\Component\Translation\Application\Service\Taxonomy\TaxonomySendOperationRepository;
use WPML\Core\Component\Translation\Application\Service\TranslationService\Dto\ResultDto;
use WPML\Core\Component\Translation\Domain\HowToHandleExistingTranslationType;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationMethod\TargetLanguageMethodType;
use WPML\Legacy\Component\Language\Application\Query\LanguagesQuery;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\TM\ATE\TranslateEverything\TranslatableTaxonomies;
use WPML\TM\Dashboard\Taxonomy\TaxonomyLabelStatus;

class TaxonomySendOperationCoordinator {

  const ACTION_START    = 'start';
  const ACTION_CONTINUE = 'continue';
  const ACTION_RETRY    = 'retry';

  const ROUND_MARGIN = 50;

  const REQUEST_TIME_BUDGET_SECONDS = 20;

  const MAX_ROUNDS_PER_REQUEST = 50;

  private $repository;

  private $languages;

  private $walker;

  private $sweeper;


  public function __construct(
    ?TaxonomySendOperationRepository $repository = null,
    ?LanguagesQueryInterface $languages = null,
    ?TaxonomySendPairWalker $walker = null,
    ?TaxonomySendOperationSweeper $sweeper = null
  ) {
    $this->repository = $repository ?: new TaxonomySendOperationRepository();
    $this->languages  = $languages;
    $this->walker     = $walker;
    $this->sweeper    = $sweeper;
  }


  public function prepare( array $requestData ) {
    if ( ! isset( $requestData['taxonomyAction'] ) ) {
      return null;
    }

    $action = is_string( $requestData['taxonomyAction'] ) ? $requestData['taxonomyAction'] : '';
    if ( self::ACTION_START === $action ) {
      return $this->start( $requestData );
    }
    if ( self::ACTION_CONTINUE === $action ) {
      return $this->continueOperation( $requestData );
    }
    if ( self::ACTION_RETRY === $action ) {
      return $this->retryOperation( $requestData );
    }

    throw new InvalidArgumentException( esc_html__( 'The taxonomy send action is not valid.', 'wpml' ) );
  }


  public function mayContinueInRequest(
    array $requestData,
    array $settled,
    int $roundsSent,
    float $elapsedSeconds,
    float $slowestRoundSeconds
  ): bool {
    if ( self::ACTION_START === ( $requestData['taxonomyAction'] ?? null )
      || ! empty( $settled['completed'] )
      || empty( $settled['progressed'] )
      || empty( $settled['operationId'] )
      || $roundsSent >= $this->maxRoundsPerRequest()
    ) {
      return false;
    }

    $budget = $this->requestTimeBudgetSeconds();

    return $budget > 0 && $elapsedSeconds + $slowestRoundSeconds <= $budget;
  }


  public function nextRoundRequest( array $requestData, array $settled ): array {
    return [
      'taxonomyAction' => self::ACTION_RETRY === ( $requestData['taxonomyAction'] ?? null )
        ? self::ACTION_RETRY
        : self::ACTION_CONTINUE,
      'operationId'    => $settled['operationId'] ?? '',
    ];
  }


  public function mergeRounds( array $before, array $round ): array {
    $merged = array_merge( $before, $round );
    foreach ( [ 'createdTranslations', 'ignoredElements', 'failedElements', 'outcomes' ] as $list ) {
      $merged[ $list ] = array_merge( (array) ( $before[ $list ] ?? [] ), (array) ( $round[ $list ] ?? [] ) );
    }
    $merged['progressed'] = ! empty( $before['progressed'] ) || ! empty( $round['progressed'] );

    return $merged;
  }


  public function settle( array $context, ResultDto $result ): array {
    $resultData  = $result->toArray();
    $createdKeys = [];
    foreach ( $resultData['createdTranslations'] as $created ) {
      if ( 'taxonomy' !== $created['type'] ) {
        continue;
      }
      $createdKeys[ $this->outcomeKey(
        $created['originalElementId'],
        $created['targetLanguageCode']
      ) ] = true;
    }

    $ignoredKeys = [];
    foreach ( $resultData['ignoredElements'] as $ignored ) {
      if ( 'taxonomy' !== $ignored['elementType'] ) {
        continue;
      }
      $ignoredKeys[ $this->outcomeKey(
        $ignored['elementId'],
        $ignored['targetLanguageCode']
      ) ] = true;
    }

    $failureKeys      = [];
    $labelFailureKeys = [];
    foreach ( $resultData['failedElements'] as $failure ) {
      $key = $this->outcomeKey(
        $failure['elementId'],
        $failure['targetLanguage']
      );
      $elementType = $failure['elementType'];
      if ( 'string' === $elementType ) {
        $labelFailureKeys[ $key ] = true;
      } elseif ( 'taxonomy' === $elementType ) {
        $failureKeys[ $key ] = $failure['kind'];
      }
    }

    $handledKeys = [];
    foreach ( $context['handled'] as $pair ) {
      $handledKeys[ $this->outcomeKey( $pair['elementId'], $pair['targetLanguage'] ) ] = true;
    }

    $termSuccessful = 0;
    $failures       = [];
    $outcomes       = [];
    foreach ( $context['page'] as $pagePair ) {
      $pair    = [
        'elementId'      => $pagePair['elementId'],
        'taxonomy'       => $pagePair['taxonomy'],
        'sourceLanguage' => $pagePair['sourceLanguage'],
        'targetLanguage' => $pagePair['targetLanguage'],
      ];
      $key     = $this->outcomeKey( $pair['elementId'], $pair['targetLanguage'] );
      $outcome = isset( $handledKeys[ $key ] ) ? 'ignored' : ( $failureKeys[ $key ]
        ?? ( isset( $createdKeys[ $key ] )
          ? 'accepted'
          : ( isset( $ignoredKeys[ $key ] ) ? 'ignored' : 'remaining' ) ) );
      if ( 'rejected' === $outcome || 'blocked' === $outcome || 'remaining' === $outcome ) {
        $failures[] = $pair + [ 'outcome' => $outcome ];
      } else {
        ++$termSuccessful;
      }
      $outcomes[] = $pair + [ 'outcome' => $outcome ];
    }

    $labelSuccessful           = 0;
    $additionalWarnings        = [];
    $incompleteLabelTaxonomies = [];
    if ( $context['labelsWereSent'] ) {
      $labelIds     = $context['manifest']['labelIds'];
      $labelTargets = array_flip( $this->labelTargetLanguageCodes( $context['manifest'] ) );
      $handledLabelKeys = [];
      foreach ( $resultData['createdTranslations'] as $created ) {
        $key = $this->outcomeKey(
          $created['originalElementId'],
          $created['targetLanguageCode']
        );
        if ( 'string' === $created['type']
          && in_array( $created['originalElementId'], $labelIds, true )
          && isset( $labelTargets[ $created['targetLanguageCode'] ] )
          && ! isset( $labelFailureKeys[ $key ] ) ) {
          $handledLabelKeys[ $key ] = true;
        }
      }
      foreach ( $resultData['ignoredElements'] as $ignored ) {
        $key = $this->outcomeKey(
          $ignored['elementId'],
          $ignored['targetLanguageCode']
        );
        if ( 'string' === $ignored['elementType']
          && in_array( $ignored['elementId'], $labelIds, true )
          && isset( $labelTargets[ $ignored['targetLanguageCode'] ] )
          && ! isset( $labelFailureKeys[ $key ] ) ) {
          $handledLabelKeys[ $key ] = true;
        }
      }
      $labelSuccessful = count( $handledLabelKeys );

      $expectedLabels = count( $labelIds ) * count( $labelTargets );
      if ( count( $handledLabelKeys ) < $expectedLabels ) {
        $additionalWarnings[]        = __( 'Some taxonomy labels were not sent for translation. Select them and try again.', 'wpml' );
        $incompleteLabelTaxonomies = $context['manifest']['labelSelections'];
      }
    }

    $manifest  = $this->repository->commitRound(
      $context['operationId'],
      $context['manifest'],
      $termSuccessful,
      $labelSuccessful,
      $failures,
      $context['labelsWereSent'],
      ! empty( $context['isRetry'] ) ? $context['page'] : [],
      $context['walk'],
      $additionalWarnings,
      $incompleteLabelTaxonomies
    );
    $progressed        = $this->progressed( $context['manifest'], $manifest );
    $retryableFailures = $this->retryableFailures( $manifest );
    $complete  = ! empty( $context['isRetry'] )
      ? ! $retryableFailures && ! empty( $manifest['ended'] )
      : $this->repository->isComplete( $manifest );
    $roundLimitReached = ! $complete && $manifest['rounds'] >= $this->roundCeiling( $manifest );
    if ( $roundLimitReached ) {
      $complete               = true;
      $manifest['warnings'][] = __( 'WPML stopped this taxonomy send because it was not finishing.', 'wpml' );
    }
    $remaining = max( 0, $this->total( $manifest ) - $manifest['successful'] );
    $status    = 'running';
    if ( $complete ) {
      if ( $manifest['failures'] || $remaining > 0 ) {
        $status = $manifest['successful'] > 0 ? 'partial_failure' : 'failure';
      } else {
        $status = $manifest['warnings'] ? 'completed_with_warnings' : 'success';
      }
    }

    $retryable = $roundLimitReached ? [] : array_slice(
      $this->retryableWork( $manifest, $retryableFailures ),
      0,
      TaxonomySendOperationRepository::PAGE_SIZE
    );
    if ( $complete && ! $retryable ) {
      $this->repository->deleteOperation( $context['operationId'] );
    }
    if ( $roundLimitReached ) {
      $this->log(
        'taxonomy_send_operation_round_limit_reached',
        [
          'operationId' => $context['operationId'],
          'rounds'      => $manifest['rounds'],
          'ceiling'     => $this->roundCeiling( $manifest ),
        ]
      );
    }

    $this->log(
      'taxonomy_send_operation_round_finished',
      [
        'operationId' => $context['operationId'],
        'pageSize'    => count( $context['page'] ),
        'outcomes'    => array_count_values( array_column( $outcomes, 'outcome' ) ),
        'errorCodes'  => array_values( array_unique( array_column( $resultData['failedElements'], 'errorCode' ) ) ),
        'successful'  => $manifest['successful'],
        'remaining'   => $remaining,
        'status'      => $status,
        'completed'   => $complete,
        'ended'       => $manifest['ended'],
        'rounds'      => $manifest['rounds'],
        'progressed'  => $progressed,
      ]
    );

    return [
      'operationId' => $context['operationId'],
      'status'      => $status,
      'total'       => $this->total( $manifest ),
      'successful'  => $manifest['successful'],
      'remaining'   => $remaining,
      'completed'   => $complete,
      'progressed'  => $progressed,
      'overwrite'   => ! empty( $manifest['selection']['overwrite'] ),
      'warnings'    => array_values( $manifest['warnings'] ),
      'incompleteTaxonomies' => $this->incompleteTaxonomies( $manifest ),
      'incompleteLabelTaxonomies' => array_values( $manifest['incompleteLabelTaxonomies'] ),
      'outcomes'    => $outcomes,
      'retryable'   => $this->withTermNames( $retryable ),
    ];
  }


  private function start( array $requestData ): array {
    $selectors      = $this->selectors( $requestData['taxonomySelections'] ?? [] );
    $labelSelectors = $this->selectors( $requestData['taxonomyLabelSelections'] ?? [] );
    $sendMethods    = $this->methods( $requestData['targetLanguageMethods'] ?? [] );
    $methods        = $this->taxonomyChoices(
      $requestData['taxonomyTargetLanguageChoices'] ?? null,
      $sendMethods
    );
    $extraInformation = is_array( $requestData['extraInformation'] ?? null ) ? $requestData['extraInformation'] : [];
    $existingPolicy   = is_string( $extraInformation['howToHandleExistingTranslations'] ?? null )
      ? $extraInformation['howToHandleExistingTranslations']
      : '';
    $overwrite      = HowToHandleExistingTranslationType::HANDLE_EXISTING_OVERRIDE === $existingPolicy;
    $allowed        = array_flip( TranslatableTaxonomies::get() );
    $translatable           = array_flip( $this->walker()->translatableLanguages( array_map( 'strval', array_keys( $methods ) ) ) );
    $translatableMethods    = array_intersect_key( $methods, $translatable );
    $warnings               = [];
    $incompleteTaxonomies   = [];
    $automaticLanguages     = array_keys(
      array_filter(
        $translatableMethods,
        function ( string $method ): bool {
          return TargetLanguageMethodType::AUTOMATIC === $method;
        }
      )
    );
    $walkedTaxonomies       = [];

    if ( $selectors && ! $methods ) {
      $warnings[] = __( 'Choose a translation method before sending taxonomy terms.', 'wpml' );
      $incompleteTaxonomies = $selectors;
    }

    foreach ( $selectors as $selector ) {
      if ( ! isset( $allowed[ $selector ] ) || ! taxonomy_exists( $selector ) ) {
        $warnings[] = sprintf(
          /* translators: %s is a taxonomy slug. */
            esc_html__( 'The taxonomy “%s” is no longer available for translation.', 'wpml' ),
          $selector
        );
        $incompleteTaxonomies[] = $selector;
        continue;
      }

      foreach ( $translatableMethods as $targetLanguage => $method ) {
        if ( 'do_nothing' === $method || TargetLanguageMethodType::AUTOMATIC === $method ) {
          continue;
        }
        if ( 'please_choose' === $method ) {
          $warnings[] = sprintf(
            /* translators: %s is a target language code. */
            esc_html__( 'Choose a translation method for %s before sending taxonomy terms.', 'wpml' ),
            $targetLanguage
          );
          $incompleteTaxonomies[] = $selector;
          continue;
        }
        $warnings[] = sprintf(
          /* translators: %s is a target language code. */
          esc_html__( 'Taxonomy terms were not sent to %s because terms currently support Automatic translation only.', 'wpml' ),
          $targetLanguage
        );
        $incompleteTaxonomies[] = $selector;
      }

      $walkedTaxonomies[] = $selector;
    }

    $frozen = $this->walker()->freeze( $walkedTaxonomies, $automaticLanguages, $overwrite );

    $validLabelSelectors          = [];
    $incompleteLabelTaxonomies    = [];
    foreach ( $labelSelectors as $selector ) {
      if ( isset( $allowed[ $selector ] ) && taxonomy_exists( $selector ) ) {
        $validLabelSelectors[] = $selector;
      } else {
        $warnings[] = sprintf(
          /* translators: %s is a taxonomy slug. */
          esc_html__( 'The taxonomy labels for “%s” are no longer available for translation.', 'wpml' ),
          $selector
        );
        $incompleteLabelTaxonomies[] = $selector;
      }
    }

    $hasRequestedLabelMethod = (bool) array_filter(
      $methods,
      function ( string $method ): bool {
        return 'do_nothing' !== $method;
      }
    );
    $baseRequest = $this->baseRequest( $requestData, $translatable );
    $labelIds = [];
    if ( $validLabelSelectors && ! $sendMethods && ( ! $methods || $hasRequestedLabelMethod ) ) {
      $warnings[] = __( 'Choose a supported translation method before sending taxonomy labels.', 'wpml' );
      $incompleteLabelTaxonomies = array_merge( $incompleteLabelTaxonomies, $validLabelSelectors );
    } elseif ( $validLabelSelectors && $baseRequest['targetLanguageMethods'] ) {
      $labelIds = ( new TaxonomyLabelStatus() )->resolveLabelStringIds( $validLabelSelectors );
      if ( ! $labelIds ) {
        $warnings[] = esc_html__( 'Taxonomy labels could not be prepared in String Translation.', 'wpml' );
        $incompleteLabelTaxonomies = array_merge( $incompleteLabelTaxonomies, $validLabelSelectors );
      }
    }

    $operationId = $this->repository->create(
      get_current_user_id(),
      $frozen['selection'],
      $frozen['termTotal'],
      $labelIds,
      $warnings,
      $baseRequest,
      $validLabelSelectors,
      $incompleteTaxonomies,
      $incompleteLabelTaxonomies
    );
    $manifest = $this->repository->get( $operationId, get_current_user_id() );

    $this->log(
      'taxonomy_send_operation_initialized',
      [
        'operationId'              => $operationId,
        'userId'                   => get_current_user_id(),
        'termPairs'                => $frozen['termTotal'],
        'walkedTaxonomies'         => count( $frozen['selection']['taxonomies'] ),
        'labelPairs'               => $this->total( $manifest ) - $frozen['termTotal'],
        'warningCount'             => count( $warnings ),
        'incompleteTaxonomies'     => count( array_unique( $incompleteTaxonomies ) ),
        'incompleteLabelTaxonomies' => count( array_unique( $incompleteLabelTaxonomies ) ),
      ]
    );

    $this->sweepAbandonedOperations();

    return $this->context( $operationId, $manifest );
  }


  private function requestTimeBudgetSeconds(): float {
    return (float) apply_filters( 'wpml_taxonomy_send_time_budget', self::REQUEST_TIME_BUDGET_SECONDS );
  }


  private function maxRoundsPerRequest(): int {
    return intval( apply_filters( 'wpml_taxonomy_send_max_rounds_per_request', self::MAX_ROUNDS_PER_REQUEST ) );
  }


  private function sweepAbandonedOperations(): void {
    try {
      $this->sweeper = $this->sweeper ?: new TaxonomySendOperationSweeper();
      $removed       = $this->sweeper->sweep();
    } catch ( \Throwable $e ) {
      $this->log( 'taxonomy_send_operations_sweep_failed', [ 'error' => get_class( $e ) ] );

      return;
    }

    if ( $removed > 0 ) {
      $this->log( 'taxonomy_send_operations_swept', [ 'removed' => $removed ] );
    }
  }


  private function continueOperation( array $requestData ): array {
    $this->assertNoInjectedWork( $requestData );
    $operationId = is_string( $requestData['operationId'] ?? null ) ? $requestData['operationId'] : '';
    $manifest    = $this->repository->get( $operationId, get_current_user_id() );
    if ( ! empty( $manifest['halted'] ) ) {
      throw new InvalidArgumentException( esc_html__( 'This translation send has retryable taxonomy terms. Retry those exact terms before continuing.', 'wpml' ) );
    }

    return $this->context( $operationId, $manifest );
  }


  private function retryOperation( array $requestData ): array {
    $this->assertNoInjectedWork( $requestData );
    $operationId = is_string( $requestData['operationId'] ?? null ) ? $requestData['operationId'] : '';
    $manifest    = $this->repository->get( $operationId, get_current_user_id() );
    $failures    = array_slice( $this->retryableFailures( $manifest ), 0, TaxonomySendOperationRepository::PAGE_SIZE );

    if ( ! $failures ) {
      return $this->context( $operationId, $manifest, null, true );
    }

    list( $needed, $handled ) = $this->walker()->splitStillNeeded( $manifest['selection'], $failures );

    return $this->context( $operationId, $manifest, $needed, true, $handled );
  }


  private function context(
    string $operationId,
    array $manifest,
    ?array $retryPage = null,
    bool $isRetry = false,
    array $handled = []
  ): array {
    $walk = null;
    $page = $retryPage;
    if ( null === $page ) {
      $next = $this->walker()->next(
        $manifest['selection'],
        $manifest['cursor'],
        TaxonomySendOperationRepository::PAGE_SIZE
      );
      $page = $next['pairs'];
      $walk = [
        'cursor' => $next['cursor'],
        'ended'  => $next['ended'],
      ];
    }
    $request           = $manifest['baseRequest'];
    $targets           = [];
    $taxonomyIds       = [];
    foreach ( $page as $pair ) {
      $id = $pair['elementId'];
      $taxonomyIds[] = $id;
      $targets[ $pair['targetLanguage'] ][] = $id;
    }

    $request['taxonomies']      = array_values( array_unique( $taxonomyIds ) );
    $request['taxonomyTargets'] = $targets;
    $request['operationId']     = $operationId;
    $labelsWereSent             = empty( $manifest['labelsSent'] ) && ! empty( $manifest['labelIds'] );
    $request['strings']         = $labelsWereSent ? $manifest['labelIds'] : [];
    $request['posts']           = [];
    $request['stringPackages']  = [];

    return [
      'request'          => $request,
      'operationId'      => $operationId,
      'manifest'         => $manifest,
      'page'             => array_merge( $page, $handled ),
      'handled'          => $handled,
      'walk'             => $walk,
      'labelsWereSent'   => $labelsWereSent,
      'isRetry'          => $isRetry,
    ];
  }


  private function retryableFailures( array $manifest ): array {
    return array_values(
      array_filter(
        $manifest['failures'],
        function ( $failure ) {
          return in_array( $failure['outcome'] ?? '', [ 'rejected', 'remaining' ], true );
        }
      )
    );
  }


  private function retryableWork( array $manifest, array $retryableFailures ): array {
    if ( $retryableFailures ) {
      return $retryableFailures;
    }

    if ( $manifest['halted'] && ! $manifest['ended'] ) {
      return array_map(
        function ( array $pair ) {
          return $pair + [ 'outcome' => 'remaining' ];
        },
        $this->walker()->next(
          $manifest['selection'],
          $manifest['cursor'],
          TaxonomySendOperationRepository::PAGE_SIZE
        )['pairs']
      );
    }

    return [];
  }


  private function withTermNames( array $pairs ): array {
    return array_map(
      function ( array $pair ): array {
        $term = get_term_by( 'term_taxonomy_id', is_numeric( $pair['elementId'] ?? null ) ? (int) $pair['elementId'] : 0, '', ARRAY_A );

        return $pair + [ 'name' => is_array( $term ) && is_string( $term['name'] ?? null ) ? $term['name'] : '' ];
      },
      $pairs
    );
  }


  private function incompleteTaxonomies( array $manifest ): array {
    $taxonomies = $manifest['incompleteTaxonomies'];
    foreach ( $manifest['failures'] as $failure ) {
      $taxonomies[] = $failure['taxonomy'];
    }
    if ( ! $manifest['ended'] ) {
      $taxonomies = array_merge(
        $taxonomies,
        array_slice( $manifest['selection']['taxonomies'], $manifest['cursor']['taxonomyIndex'] )
      );
    }

    return array_values( array_unique( $taxonomies ) );
  }


  private function selectors( $raw ): array {
    if ( ! is_array( $raw ) || count( $raw ) > 100 ) {
      throw new InvalidArgumentException( esc_html__( 'The taxonomy selection is not valid.', 'wpml' ) );
    }

    $selectors = [];
    foreach ( $raw as $selector ) {
      if ( ! is_string( $selector ) || sanitize_key( $selector ) !== $selector || '' === $selector ) {
        throw new InvalidArgumentException( esc_html__( 'The taxonomy selection is not valid.', 'wpml' ) );
      }
      $selectors[] = $selector;
    }

    return array_values( array_unique( $selectors ) );
  }


  private function methods( $raw ): array {
    if ( ! is_array( $raw ) ) {
      throw new InvalidArgumentException( esc_html__( 'Language pairs cannot be empty', 'wpml' ) );
    }

    $active = [];
    foreach ( $this->languages()->getActive() as $language ) {
      $active[ $language->getCode() ] = true;
    }
    $allowed = [
      TargetLanguageMethodType::AUTOMATIC,
      TargetLanguageMethodType::LOCAL_TRANSLATOR,
      TargetLanguageMethodType::TRANSLATION_SERVICE,
      TargetLanguageMethodType::DUPLICATE,
    ];
    $methods = [];
    foreach ( $raw as $entry ) {
      $language = is_array( $entry ) && is_string( $entry['targetLanguageCode'] ?? null ) ? $entry['targetLanguageCode'] : '';
      $method   = is_array( $entry ) && is_string( $entry['translationMethod'] ?? null ) ? $entry['translationMethod'] : '';
      if ( ! isset( $active[ $language ] ) || ! in_array( $method, $allowed, true ) ) {
        throw new InvalidArgumentException( esc_html__( 'A target language or translation method is not valid.', 'wpml' ) );
      }
      if ( isset( $methods[ $language ] ) ) {
        throw new InvalidArgumentException( esc_html__( 'A target language can have only one translation method.', 'wpml' ) );
      }
      $methods[ $language ] = $method;
    }

    return $methods;
  }


  private function taxonomyChoices( $raw, array $sendMethods ): array {
    if ( null === $raw ) {
      return $sendMethods;
    }
    if ( ! is_array( $raw ) ) {
      throw new InvalidArgumentException( esc_html__( 'A taxonomy target language choice is not valid.', 'wpml' ) );
    }

    $active = [];
    foreach ( $this->languages()->getActive() as $language ) {
      $active[ $language->getCode() ] = true;
    }
    $allowed = [
      TargetLanguageMethodType::AUTOMATIC,
      TargetLanguageMethodType::LOCAL_TRANSLATOR,
      TargetLanguageMethodType::TRANSLATION_SERVICE,
      TargetLanguageMethodType::DUPLICATE,
      'myself',
      'do_nothing',
      'please_choose',
    ];
    $choices = [];
    foreach ( $raw as $entry ) {
      $language = is_array( $entry ) && is_string( $entry['targetLanguageCode'] ?? null ) ? $entry['targetLanguageCode'] : '';
      $method   = is_array( $entry ) && is_string( $entry['translationMethod'] ?? null ) ? $entry['translationMethod'] : '';
      if ( ! isset( $active[ $language ] ) || ! in_array( $method, $allowed, true ) || isset( $choices[ $language ] ) ) {
        throw new InvalidArgumentException( esc_html__( 'A taxonomy target language choice is not valid.', 'wpml' ) );
      }

      $sendMethod = 'myself' === $method ? TargetLanguageMethodType::LOCAL_TRANSLATOR : $method;
      $isNoWork   = in_array( $method, [ 'do_nothing', 'please_choose' ], true );
      if ( ( $isNoWork && isset( $sendMethods[ $language ] ) )
        || ( ! $isNoWork && ( $sendMethods[ $language ] ?? null ) !== $sendMethod ) ) {
        throw new InvalidArgumentException( esc_html__( 'Taxonomy target choices do not match the translation request.', 'wpml' ) );
      }

      $choices[ $language ] = $method;
    }

    if ( array_diff_key( $sendMethods, $choices ) ) {
      throw new InvalidArgumentException( esc_html__( 'Taxonomy target choices do not match the translation request.', 'wpml' ) );
    }

    return $choices;
  }


  private function baseRequest( array $requestData, array $translatable ): array {
    return [
      'batchName'             => is_string( $requestData['batchName'] ?? null ) ? $requestData['batchName'] : '',
      'sourceLanguageCode'    => is_string( $requestData['sourceLanguageCode'] ?? null )
        ? $requestData['sourceLanguageCode']
        : $this->languages()->getDefaultCode(),
      'targetLanguageMethods' => array_values(
        array_filter(
          (array) ( $requestData['targetLanguageMethods'] ?? [] ),
          function ( $entry ) use ( $translatable ): bool {
            return is_array( $entry )
              && is_string( $entry['targetLanguageCode'] ?? null )
              && isset( $translatable[ $entry['targetLanguageCode'] ] );
          }
        )
      ),
      'extraInformation'      => (array) ( $requestData['extraInformation'] ?? [] ),
    ];
  }


  private function total( array $manifest ): int {
    return $this->termPairs( $manifest )
      + count( $manifest['labelIds'] ) * count( $this->labelTargetLanguageCodes( $manifest ) );
  }


  private function termPairs( array $manifest ): int {
    $walkedTerms = $manifest['termSuccessful'] + count( $manifest['failures'] ) + $manifest['failureOverflow'];

    return $manifest['ended'] ? $walkedTerms : max( $manifest['termTotal'], $walkedTerms );
  }


  private function roundCeiling( array $manifest ): int {
    return 2 * (int) ceil( $this->termPairs( $manifest ) / TaxonomySendOperationRepository::PAGE_SIZE )
      + self::ROUND_MARGIN;
  }


  private function progressed( array $before, array $after ): bool {
    return $after['successful'] > $before['successful']
      || $after['cursor'] !== $before['cursor']
      || ( $after['ended'] && ! $before['ended'] );
  }


  private function labelTargetLanguageCodes( array $manifest ): array {
    $labelTargets = [];
    $methods = is_array( $manifest['baseRequest']['targetLanguageMethods'] ?? null )
      ? $manifest['baseRequest']['targetLanguageMethods']
      : [];
    foreach ( $methods as $entry ) {
      if ( is_array( $entry ) && TargetLanguageMethodType::DUPLICATE !== (string) ( $entry['translationMethod'] ?? '' ) ) {
        $language = is_string( $entry['targetLanguageCode'] ?? null ) ? $entry['targetLanguageCode'] : '';
        if ( '' !== $language ) {
          $labelTargets[] = $language;
        }
      }
    }

    return array_values( array_unique( $labelTargets ) );
  }


  private function outcomeKey( int $elementId, string $targetLanguage ): string {
    return $elementId . '|' . $targetLanguage;
  }


  private function assertNoInjectedWork( array $requestData ): void {
    foreach ( [ 'taxonomySelections', 'taxonomyLabelSelections', 'taxonomies', 'taxonomyTargets', 'posts', 'strings', 'stringPackages' ] as $field ) {
      if ( ! empty( $requestData[ $field ] ) ) {
        throw new InvalidArgumentException( esc_html__( 'A translation send continuation cannot add new work.', 'wpml' ) );
      }
    }
  }


  private function log( string $event, array $context ): void {
    if ( ! class_exists( '\\WPML\\TM\\Jobs\\JobLog' ) ) {
      return;
    }

    \WPML\TM\Jobs\JobLog::maybeInitRequest();
    $ownsGroup = ! \WPML\TM\Jobs\JobLog::isGroupOpen();
    if ( $ownsGroup ) {
      \WPML\TM\Jobs\JobLog::createNewGroup(
        \WPML\TM\Jobs\JobLog::GROUP_ID_SEND_JOBS,
        'Taxonomy send operation'
      );
    }

    \WPML\TM\Jobs\JobLog::add( $event, $context );

    if ( $ownsGroup ) {
      \WPML\TM\Jobs\JobLog::finishCurrentGroup();
    }
  }


  private function languages(): LanguagesQueryInterface {
    if ( null === $this->languages ) {
      $this->languages = new LanguagesQuery( $GLOBALS['sitepress'] );
    }

    return $this->languages;
  }


  private function walker(): TaxonomySendPairWalker {
    if ( null === $this->walker ) {
      $this->walker = new TaxonomySendPairWalker();
    }

    return $this->walker;
  }

}
