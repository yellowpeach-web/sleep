<?php

namespace WPML\Infrastructure\WordPress\Component\Item\Application\Query;

use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Core\Port\Persistence\QueryPrepareInterface;

class HeldOutTranslationCondition {

  const OPTION_NAME = 'wpml-old-jobs-editor';

  const EDITOR_WPML = 'wpml';

  const EDITOR_ATE = 'ate';

  private $options;

  private $queryPrepare;


  public function __construct(
    OptionsInterface $options,
    QueryPrepareInterface $queryPrepare
  ) {
    $this->options      = $options;
    $this->queryPrepare = $queryPrepare;
  }


  public function isActive(): bool {
    return $this->options->get( self::OPTION_NAME, self::EDITOR_WPML ) === self::EDITOR_WPML;
  }


  public function sql( string $statusAlias ): string {
    if ( ! $this->isActive() ) {
      return '';
    }

    $prefix = $this->queryPrepare->prefix();
    $wpml   = self::EDITOR_WPML;
    $ate    = self::EDITOR_ATE;

    return "{$statusAlias}.needs_update = 1 AND IFNULL(
      (
        SELECT jobs.editor FROM {$prefix}icl_translate_job jobs
        WHERE jobs.rid = {$statusAlias}.rid
        ORDER BY jobs.job_id DESC
        LIMIT 1
      ),
      '{$ate}'
    ) = '{$wpml}'";
  }


  public function existsSql( string $tridColumn, string $aliasSuffix = '' ): string {
    if ( ! $this->isActive() ) {
      return '';
    }

    $prefix           = $this->queryPrepare->prefix();
    $translationAlias = 'held_out_t' . $aliasSuffix;
    $statusAlias      = 'held_out_ts' . $aliasSuffix;

    return "EXISTS (
      SELECT 1
      FROM {$prefix}icl_translations {$translationAlias}
      INNER JOIN {$prefix}icl_translation_status {$statusAlias}
              ON {$statusAlias}.translation_id = {$translationAlias}.translation_id
      WHERE {$translationAlias}.trid = {$tridColumn}
        AND {$this->sql( $statusAlias )}
    )";
  }


}
