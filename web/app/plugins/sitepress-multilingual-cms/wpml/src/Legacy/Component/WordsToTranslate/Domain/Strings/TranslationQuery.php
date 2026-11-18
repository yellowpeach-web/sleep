<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\Strings;

use WPML\Core\Component\WordsToTranslate\Domain\Item;
use WPML\Core\Component\WordsToTranslate\Domain\Strings\Query\TranslationQueryInterface;

use WPML\Translation\TranslationElements\FieldCompression;

use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class TranslationQuery implements TranslationQueryInterface {

  const BATCH_ELEMENT_TYPE = 'st-batch_strings';

  const FIELD_TYPE_PREFIX = 'batch-string-';

  private $decompress;


  public function __construct( $decompress = null ) {
    $this->decompress = $decompress ?: function ( ?string $data ): ?string {
      return FieldCompression::decompress( $data, true );
    };
  }


  public function getLastTranslatedOriginalContent( Item $string, string $lang ) {
    $wpdb = $GLOBALS['wpdb'];

    $translation = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT value, status
        FROM {$wpdb->prefix}icl_string_translations
        WHERE string_id = %d AND language = %s",
        $string->getId(),
        $lang
      )
    );

    if ( ! $translation || $translation->value === null || $translation->value === '' ) {
      return '';
    }

    $jobOriginal = $this->getMatchingJobOriginal( $string, $lang, (string) $translation->value );

    if ( $jobOriginal !== null ) {
      return $jobOriginal;
    }

    if ( (int) $translation->status === TranslationStatus::COMPLETE ) {
      return $string->getContent() ?? '';
    }

    return '';
  }


  private function getMatchingJobOriginal( Item $string, string $lang, string $storedTranslation ) {
    $wpdb = $GLOBALS['wpdb'];

    $row = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT job.job_id,
          field.field_data,
          field.field_data_translated,
          field.field_format
        FROM {$wpdb->prefix}icl_string_batches batch
        INNER JOIN {$wpdb->prefix}icl_translations source
          ON source.element_id = batch.batch_id
          AND source.element_type = '" . self::BATCH_ELEMENT_TYPE . "'
          AND source.source_language_code IS NULL
        INNER JOIN {$wpdb->prefix}icl_translations target
          ON target.trid = source.trid
          AND target.element_type = '" . self::BATCH_ELEMENT_TYPE . "'
          AND target.language_code = %s
        INNER JOIN {$wpdb->prefix}icl_translation_status status
          ON status.translation_id = target.translation_id
        INNER JOIN {$wpdb->prefix}icl_translate_job job
          ON job.rid = status.rid
          AND job.completed_date IS NOT NULL
        LEFT JOIN {$wpdb->prefix}icl_translate field
          ON field.job_id = job.job_id
          AND field.field_type = %s
        WHERE batch.string_id = %d
        ORDER BY job.completed_date DESC, job.job_id DESC
        LIMIT 1",
        $lang,
        self::FIELD_TYPE_PREFIX . $string->getId(),
        $string->getId()
      )
    );

    if ( ! $row || $row->field_data === null || $row->field_data_translated === null ) {
      return null;
    }

    $format     = (string) ( $row->field_format ?? '' );
    $original   = $this->decodeField( $row->field_data, $format );
    $translated = $this->decodeField( $row->field_data_translated, $format );

    if ( $translated !== $storedTranslation && $this->asSaved( $translated, $original ) !== $storedTranslation ) {
      return null;
    }

    return $original;
  }


  private function decodeField( $data, $format ) {
    $data = (string) call_user_func( $this->decompress, $data );

    if ( $format === 'base64' ) {
      return (string) base64_decode( $data );
    }

    if ( $format === 'csv_base64' ) {
      $parts = [];

      foreach ( explode( ',', $data ) as $part ) {
        $parts[] = (string) base64_decode( trim( $part, '"' ) );
      }

      return implode( ',', $parts );
    }

    return $data;
  }


  private function asSaved( $translated, $original ) {
    if ( strpos( $original, "\r\n" ) !== false ) {
      return (string) preg_replace( '/(?<!\r)\n/', "\r\n", $translated );
    }

    if ( strpos( $original, "\n" ) !== false ) {
      return str_replace( "\r\n", "\n", $translated );
    }

    return $translated;
  }


}
