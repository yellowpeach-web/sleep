<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\Job;

use WPML\Core\Component\WordsToTranslate\Domain\Job\Query\JobQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Provider as ProviderPost;
use WPML\Core\Component\WordsToTranslate\Domain\StringBatch\Provider as ProviderStringBatch;
use WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Provider as ProviderStringPackage;
use WPML\Core\Component\WordsToTranslate\Domain\TranslatableDTO;
use WPML\PHP\Exception\InvalidArgumentException;


class JobQuery implements JobQueryInterface {


  private $jobs = [];


  public function getSourceLang( $id ) {
    return $this->getJob( $id )->sourceLanguage;
  }

  public function getTargetLang( $id ) {
    return $this->getJob( $id )->targetLanguage;
  }


  public function getPreviousAteJobIds( $id ) {
    return $this->getJob( $id )->previousAteJobIds;
  }


  private function fetchPreviousAteJobIds( $rid ) {
    $wpdb = $GLOBALS['wpdb'];

    $previousAteJobIds = $wpdb->get_col(
      $wpdb->prepare(
        "SELECT editor_job_id
        FROM {$wpdb->prefix}icl_translate_job j
        WHERE rid = %d
        AND j.completed_date IS NOT NULL
        AND j.editor_job_id > 0
        ORDER BY j.completed_date DESC
        LIMIT 15",
        $rid
      )
    );

    return array_map( 'intval', $previousAteJobIds );
  }


  public function isAutomatic( $id ) {
    return $this->getJob( $id )->isAutomatic;
  }


  public function getJobItemId( $id ) {
    return $this->getJob( $id )->itemId;
  }


  public function getJobItemType( $id ) {
    return $this->getJob( $id )->itemType;
  }


  public function getContent( $id ) {
    return $this->getJob( $id )->content;
  }


  public function getLifetimeMaxWordsToTranslate( $id ) {
    return \WPML\TM\API\Jobs::computeWordsLifetimeMax( $id );
  }


  public function setLifetimeMaxWordsToTranslate( $id, $count ) {
    \WPML\TM\API\Jobs::setWordsLifetimeMax( $id, $count );
  }


  public function wasCreatedByTEA( $id ) {
    return \WPML\TM\API\Jobs::wasCreatedByTEA( $id );
  }

  private function getJob( $id ) {
    if ( ! isset( $this->jobs[ $id ] ) ) {
      $wpdb = $GLOBALS['wpdb'];

      $job = null;

      if ( function_exists( 'wpml_tm_load_job_factory' ) ) {
        $jobFactory = \wpml_tm_load_job_factory();
        if ( is_object( $jobFactory ) && method_exists( $jobFactory, 'get_translation_job_as_stdclass' ) ) {
          $job = $jobFactory->get_translation_job_as_stdclass( $id );
        }
      }

      if ( ! $job ) {
        throw new InvalidArgumentException(
          sprintf(
            'Job with id %d not found',
            $id
          )
        );
      }

      if (
        ! is_object( $job )
        || ! isset( $job->rid )
        || ! isset( $job->source_language_code )
        || ! isset( $job->language_code )
        || ! isset( $job->automatic )
        || ! isset( $job->original_doc_id )
        || ! isset( $job->original_post_type )
        || ! isset( $job->elements ) || ! is_array ( $job->elements )
      ) {
        throw new InvalidArgumentException(
          sprintf(
            'Job with id %d does not contain all required fields',
            $id
          )
        );
      }


      $content = [];

      foreach ( $job->elements as $element ) {
        if (
          ! is_object( $element )
          || ! isset( $element->field_translate )
          || ! isset( $element->field_type )
          || ! isset( $element->field_data )
          || ! isset( $element->field_format )
        ) {
          throw new InvalidArgumentException(
            sprintf(
              'Job with id %d does not contain all required element fields',
              $id
            )
          );
        }
        if( ! $element->field_translate ) {
          continue;
        }

        $content[] = new TranslatableDTO(
          $element->field_type,
          $element->field_data,
          $element->field_format
        );
      }

      $previousAteJobIds = $this->fetchPreviousAteJobIds( (int) $job->rid );

      $this->jobs[ $id ] = new Job(
        (int) $job->original_doc_id,
        $this->convertType( $job->original_post_type ),
        $job->source_language_code,
        $job->language_code,
        (bool) $job->automatic,
        $previousAteJobIds,
        $content
      );
    }

    return $this->jobs[ $id ];
  }


  private function convertType( $rawType ) {
    if ( strpos( $rawType, 'post_' ) === 0 ) {
      return ProviderPost::TYPE;
    }

    if ( strpos( $rawType, 'st-batch_strings' ) === 0 ) {
      return ProviderStringBatch::TYPE;
    }

    if ( strpos( $rawType, 'package_' ) === 0 ) {
      return ProviderStringPackage::TYPE;
    }

    throw new InvalidArgumentException(
      sprintf(
        'Raw data with type %s is not supported',
        $rawType
      )
    );
  }


}


class Job {  
  public $itemId;

  public $itemType;

  public $sourceLanguage;

  public $targetLanguage;

  public $isAutomatic;

  public $previousAteJobIds;

  public $content = [];


  public function __construct(
    $itemId,
    $itemType,
    $sourceLanguage,
    $targetLanguage,
    $isAutomatic,
    $previousAteJobIds,
    $content
  ) {
    $this->itemId = $itemId;
    $this->itemType = $itemType;
    $this->sourceLanguage = $sourceLanguage;
    $this->targetLanguage = $targetLanguage;
    $this->isAutomatic = $isAutomatic;
    $this->previousAteJobIds = $previousAteJobIds;
    $this->content = $content;
  }


}
