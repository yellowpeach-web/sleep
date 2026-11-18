<?php

namespace WPML\Legacy\Component\WordsToTranslate\Domain\Post;

use WPML\Core\Component\WordsToTranslate\Domain\Post\FieldKey;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Post;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Term\Term;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Term\TermContent;
use WPML\Core\Component\WordsToTranslate\Domain\Post\Query\TranslationQueryInterface;

use WPML\Legacy\Component\WordsToTranslate\Domain\BareUrl;
use WPML\Translation\TranslationElements\FieldCompression;

use WPML\Core\Port\Persistence\Exception\DatabaseErrorException;
use WPML\Core\Port\Persistence\QueryHandlerInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;
use WPML\Core\SharedKernel\Component\Translation\Application\Query\TranslationToSendConditionInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class TranslationQuery implements TranslationQueryInterface {

  private $toSend;

  private $queryPrepare;

  private $queryHandler;

  private $jobs = [];

  private $trids = [];

  private $isTermTranslatable = [];

  private $termTranslationCache = [];

  private $translatedCompletely = [];

  private $alreadyTranslated = [];

  private $_isTermRetranslationAllowed;


  public function __construct(
    TranslationToSendConditionInterface $toSend,
    QueryPrepareInterface $queryPrepare,
    QueryHandlerInterface $queryHandler
  ) {
    $this->toSend       = $toSend;
    $this->queryPrepare = $queryPrepare;
    $this->queryHandler = $queryHandler;
  }


  public function getLastTranslatedOriginalContentForPost(
    $post,
    $lang,
    $fieldsToTranslate
  ) {
    return trim(
      implode(
        ' ',
        $this->getLastTranslatedOriginalFieldContentsForPost(
          $post,
          $lang,
          $fieldsToTranslate
        )
      )
    );
  }


  public function getLastTranslatedOriginalFieldContentsForPost(
    $post,
    $lang,
    $fieldsToTranslate
  ) {
    $job = $this->getJobByPost( $post, $lang );

    return $this->appendCompletePackageStrings(
      $job && $job->elements ? $this->getJobFieldContents( $job, $fieldsToTranslate ) : [],
      $post,
      $lang
    );
  }


  private function getJobFieldContents( LegacyJob $job, $fieldsToTranslate ) {
    $fieldContents = [];

    foreach ( $job->elements as $element ) {
      if ( ! $this->isElementToTranslate( $element, $fieldsToTranslate ) ) {
        continue;
      }

      $content = trim(
        FieldCompression::decompress( $element->field_data ) ?? ''
      );

      if ( ! $content ) {
        continue;
      }

      $fieldKey = FieldKey::groupKey( $element->field_type );

      $fieldContents[ $fieldKey ] = isset( $fieldContents[ $fieldKey ] )
        ? $fieldContents[ $fieldKey ] . ' ' . $content
        : $content;
    }

    return $fieldContents;
  }


  private function appendCompletePackageStrings( array $fieldContents, Post $post, $lang ) {
    if ( ! defined( 'WPML_ST_VERSION' ) ) {
      return $fieldContents;
    }

    $prefix = $this->queryPrepare->prefix();
    $sql    = "SELECT s.value AS source, st.status AS status, st.value AS translation
         FROM {$prefix}icl_string_packages p
         JOIN {$prefix}icl_strings s ON s.string_package_id = p.ID
         JOIN {$prefix}icl_string_translations st ON st.string_id = s.id AND st.language = %s
         WHERE p.post_id = %d
         ORDER BY p.ID, s.id";

    try {
      $rows = $this->queryHandler->query(
        $this->queryPrepare->prepare( $sql, $lang, $post->getId() )
      )->getResults();
    } catch ( DatabaseErrorException $e ) {
      return $fieldContents;
    }

    $sources = [];
    foreach ( $rows as $row ) {
      $source = trim( (string) $row['source'] );
      if (
        $source === ''
        || (int) $row['status'] !== TranslationStatus::COMPLETE
        || (string) $row['translation'] === ''
        || ! $this->isCountedText( (string) $row['source'] )
      ) {
        continue;
      }
      $sources[] = $source;
    }

    if ( ! $sources ) {
      return $fieldContents;
    }

    $key = FieldKey::PACKAGE_STRINGS_GROUP_KEY;

    $fieldContents[ $key ] = trim( ( $fieldContents[ $key ] ?? '' ) . ' ' . implode( ' ', $sources ) );

    return $fieldContents;
  }


  private function isCountedText( string $value ): bool {
    if ( BareUrl::matches( $value ) ) {
      return false;
    }

    return ! (
      class_exists( \WPML_String_Functions::class )
      && \WPML_String_Functions::is_not_translatable( $value )
    );
  }


  private function isElementToTranslate( $element, $fieldsToTranslate ) {
    if ( ! $element->field_translate ) {
      return false;
    }

    if (
      strpos( $element->field_type, FieldKey::PACKAGE_STRING_FIELD_PREFIX ) !== 0 &&
      ! in_array( $element->field_type, $fieldsToTranslate, true )
    ) {
      return false;
    }

    if (
      strpos( $element->field_type, 't_' ) === 0
      || strpos( $element->field_type, 'tdesc_' ) === 0
      || strpos( $element->field_type, 'tfield' ) === 0
    ) {
      return false;
    }

    return true;
  }


  public function isTranslatedCompletely( $post, $lang ) {
    $cacheKey = $post->getType() . $post->getId() . $lang;
    if ( isset( $this->translatedCompletely[ $cacheKey ] ) ) {
      return $this->translatedCompletely[ $cacheKey ];
    }

    $trid = $this->getJobTridByPost( $post, $lang );
    if ( ! $trid ) {
      return $this->translatedCompletely[ $cacheKey ] = false;
    }

    $prefix = $this->queryPrepare->prefix();
    $sql    = "SELECT t.element_id AS element_id, t.source_language_code AS src, s.status AS status, s.needs_update AS needs_update,
           ( {$this->toSend->sql( 's' )} {$this->toSend->notDiscardedSql( 's' )} ) AS will_be_sent
         FROM {$prefix}icl_translations t
         LEFT JOIN {$prefix}icl_translation_status s
           ON s.translation_id = t.translation_id
         {$this->toSend->joinSql( 's' )}
         WHERE t.trid = %d AND t.language_code = %s
         LIMIT 1;";

    try {
      $row = $this->queryHandler->queryOne(
        $this->queryPrepare->prepare( $sql, $this->toSend->joblessCutoff(), $trid, $lang )
      );
    } catch ( DatabaseErrorException $e ) {
      $row = null;
    }

    if ( ! is_array( $row ) ) {
      if ( $this->toSend->isRefusedPair( $post->getSourceLang(), $lang ) ) {
        return $this->translatedCompletely[ $cacheKey ] = true;
      }

      return $this->translatedCompletely[ $cacheKey ] = false;
    }

    if ( $row['src'] === null ) {
      return $this->translatedCompletely[ $cacheKey ] = true;
    }

    if ( $row['src'] !== null && $this->toSend->isRefusedPair( $row['src'], $lang ) ) {
      return $this->translatedCompletely[ $cacheKey ] = true;
    }

    if ( ! (int) $row['will_be_sent'] ) {
      return $this->translatedCompletely[ $cacheKey ] = true;
    }

    if ( $row['status'] === null && $this->isObservablyComplete( $row['element_id'] ) ) {
      return $this->translatedCompletely[ $cacheKey ] = true;
    }

    return $this->translatedCompletely[ $cacheKey ] = false;
  }


  private function isObservablyComplete( $translatedPostId ) {
    if ( empty( $translatedPostId ) ) {
      return false;
    }

    $translatedPost = get_post( (int) $translatedPostId );
    if ( ! is_object( $translatedPost ) || 'publish' !== $translatedPost->post_status ) {
      return false;
    }

    return ! \WPML\TranslationPolicy\Duplicate\DuplicateReaders::isCopy( (int) $translatedPostId );
  }


  public function isAlreadyTranslated( $post, $lang ): bool {
    $cacheKey = $post->getType() . $post->getId() . $lang;
    if ( isset( $this->alreadyTranslated[ $cacheKey ] ) ) {
      return $this->alreadyTranslated[ $cacheKey ];
    }

    $trid = $this->getJobTridByPost( $post, $lang );
    if ( ! $trid ) {
      return $this->alreadyTranslated[ $cacheKey ] = false;
    }

    $prefix = $this->queryPrepare->prefix();
    $sql    = "SELECT t.source_language_code AS src, s.status AS status, s.needs_update AS needs_update
         FROM {$prefix}icl_translations t
         LEFT JOIN {$prefix}icl_translation_status s
           ON s.translation_id = t.translation_id
         WHERE t.trid = %d AND t.language_code = %s
         LIMIT 1;";

    try {
      $row = $this->queryHandler->queryOne( $this->queryPrepare->prepare( $sql, $trid, $lang ) );
    } catch ( DatabaseErrorException $e ) {
      $row = null;
    }

    if ( ! is_array( $row ) ) {
      return $this->alreadyTranslated[ $cacheKey ] = false;
    }

    if ( $row['src'] === null ) {
      return $this->alreadyTranslated[ $cacheKey ] = true;
    }

    return $this->alreadyTranslated[ $cacheKey ] =
      $row['status'] !== null
      && (int) $row['status'] === TranslationStatus::COMPLETE
      && (int) $row['needs_update'] === 0;
  }


  private function getJobByPost( Post $post, string $lang ) {
    $jobKey = $post->getType() . $post->getId() . $lang;
    if ( isset( $this->jobs[ $jobKey ] ) ) {
      return $this->jobs[ $jobKey ];
    }

    $trid = $this->getJobTridByPost( $post, $lang );

    if ( ! $trid ) {
      $this->jobs[ $jobKey ] = null;
      return null;
    }

    global $wpdb;

    $jobId = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT j.job_id
         FROM {$wpdb->prefix}icl_translate_job j
         JOIN {$wpdb->prefix}icl_translation_status s ON j.rid = s.rid
         JOIN {$wpdb->prefix}icl_translations t ON s.translation_id = t.translation_id
         WHERE t.trid = %d
         AND ( j.completed_date IS NOT NULL OR ( j.editor = 'wp' AND j.translated = 1 ) )
         AND j.editor in ('ate', 'wpml', 'wp')
         AND t.language_code = %s
         ORDER BY j.job_id DESC
         LIMIT 1;",
         $trid,
         $lang
      )
    );

    if ( $jobId === null ) {
      $this->jobs[ $jobKey ] = null;
      return null;
    }

    $elements = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT translate.*
			  FROM {$wpdb->prefix}icl_translate translate
			  WHERE job_id = %d",
        $jobId
      )
    );

    if ( ! $elements ) {
      $this->jobs[ $jobKey ] = null;
      return null;
    }

    $job = new LegacyJob();
    $job->elements = $elements;

    $this->jobs[ $jobKey ] = $job;
    return $this->jobs[ $jobKey ];
  }


  private function getJobTridByPost( Post $post, string $lang ) {
    $jobKey = $post->getType() . $post->getId() . $lang;
    if ( isset( $this->trids[ $jobKey ] ) ) {
      return $this->trids[ $jobKey ];
    }

    $sitepress = $GLOBALS['sitepress'];
    $trid = $sitepress->get_element_trid( $post->getId(), 'post_' );

    if ( ! $trid ) {
      $this->trids[ $jobKey ] = null;
      return null;
    }

    $this->trids[ $jobKey ] = $trid;
    return $trid;
  }


  public function isTermTranslatable( Term $term, string $lang ) {
    if ( \WPML_Element_Translation_Package::isTermCoveredInLanguage( $term->getId(), $lang ) ) {
      return false;
    }

    if ( $this->isTermRetranslationAllowed() ) {
      return true;
    }

    $idTerm = $term->getId();
    $cache = $lang . $idTerm;

    if ( isset( $this->isTermTranslatable[ $cache ] ) ) {
      return $this->isTermTranslatable[ $cache ];
    }

    global $wpdb;
    $translation = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT
          {$wpdb->prefix}terms.term_id original_term_id,
          translation.trid translation_trid
        FROM {$wpdb->prefix}terms
        INNER JOIN {$wpdb->prefix}icl_translations original_translation
          ON original_translation.element_id = %d
          AND original_translation.element_type LIKE 'tax\_%%'
        INNER JOIN {$wpdb->prefix}icl_translations translation
          ON translation.trid = original_translation.trid
          AND translation.language_code = '%s'
        WHERE {$wpdb->prefix}terms.term_id = %d LIMIT 1;",
        $idTerm,
        $lang,
        $idTerm
      )
    );

    return $this->isTermTranslatable[ $cache ] = count( $translation ) === 0;
  }


  public function getLastTranslatedOriginalContentForTermContent( Term $term, TermContent $termContent, string $langCode ) {
    $cacheKey = $term->getId() . $langCode;
    $isMeta = ! in_array( $termContent->getType(), [ 'description', 'name' ] );

    if ( isset( $this->termTranslationCache[ $cacheKey ] ) ) {
      if ( $isMeta ) {
        $metaData = $this->termTranslationCache[ $cacheKey ]['meta_data'] ?? [];

        if ( ! is_array( $metaData ) ) {
          return '';
        }

        if ( ! isset( $metaData[ $termContent->getType() ][0] ) ) {
          return '';
        }

        return $metaData[ $termContent->getType() ][0];
      } elseif ( isset( $this->termTranslationCache[ $cacheKey ][ $termContent->getType() ] ) ) {
        $value = $this->termTranslationCache[ $cacheKey ][ $termContent->getType() ];
        return is_string( $value ) ? $value : '';
      }
      return '';
    }

    $this->termTranslationCache[ $cacheKey ] = [];

    $wpdb = $GLOBALS['wpdb'];

    $translations = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT
          t.term_id,
          t.name,
          t.slug,
          tt.description,
          i.language_code
        FROM {$wpdb->prefix}terms AS t
        JOIN {$wpdb->prefix}term_taxonomy AS tt ON tt.term_id = t.term_id
        JOIN {$wpdb->prefix}icl_translations AS i ON i.element_id = tt.term_taxonomy_id
        INNER JOIN (
					SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type LIKE 'tax\_%%' AND source_language_code IS NULL LIMIT 1
        ) lm on lm.trid = i.trid
        WHERE t.term_id != %d",
        $term->getId(),
        $term->getId()
      )
    );

    $sitepress = $GLOBALS['sitepress'];
		$setting_factory = $sitepress->core_tm()->settings_factory();

		foreach ( $translations as $translation ) {
			$meta_data = get_term_meta( $translation->term_id );

      if ( ! is_array( $meta_data ) ) {
        continue;
      }

			foreach ( $meta_data as $meta_key => $meta_data ) {
        if (
          in_array(
            $setting_factory->term_meta_setting( $meta_key )->status(),
            [ WPML_TRANSLATE_CUSTOM_FIELD, WPML_COPY_ONCE_CUSTOM_FIELD ],
            true
          )
        ) {
					$translation->meta_data[ $meta_key ] = $meta_data;
				}
			}
		}

    foreach ( $translations as $translation ) {
      $this->termTranslationCache[ $term->getId() . $translation->language_code ] = (array) $translation;
    }

    return $this->getLastTranslatedOriginalContentForTermContent( $term, $termContent, $langCode );
  }


  private function isTermRetranslationAllowed() {
    if ( $this->_isTermRetranslationAllowed === null ) {
      $sitepress = $GLOBALS['sitepress'];
      $this->_isTermRetranslationAllowed = ! $sitepress->get_setting( 'tm_block_retranslating_terms' );
    }

    return $this->_isTermRetranslationAllowed;
  }


}


class LegacyJob {

  public $elements = [];

}


class LegacyElement {

  public $field_translate = false;

  public $field_type = '';

  public $field_data = '';

}
