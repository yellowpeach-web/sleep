<?php

namespace WPML\Legacy\Component\Translation\Sender\Taxonomy;

final class TaxonomyTermJobCleanup {

  private $wpdb;


  public function __construct( \wpdb $wpdb ) {
    $this->wpdb = $wpdb;
  }


  public function release( array $preparedJobs ): int {
    $restored = 0;

    foreach ( $preparedJobs as $prepared ) {
      if ( $this->hasRemoteMapping( $prepared ) ) {
        continue;
      }

      $this->deleteOwnedJobRow( $prepared );
      $this->restoreSupersededGeneration( $prepared );

      if ( $this->ridHasRemoteJobSince( $prepared->getRid(), $prepared->getJobId() ) ) {
        continue;
      }

      $before = $prepared->getStatusBefore();
      if ( ! empty( $before['created'] ) ) {
        $restored += (int) $this->wpdb->update(
          $this->wpdb->prefix . 'icl_translation_status',
          [
            'status'              => $this->notTranslatedStatus(),
            'needs_update'        => 0,
            'translation_service' => '',
            'translator_id'       => 0,
          ],
          [
            'rid'                 => $prepared->getRid(),
            'status'              => $this->inProgressStatus(),
            'translation_service' => 'local',
            'translator_id'       => 0,
            'needs_update'        => 0,
          ],
          [ '%d', '%d', '%s', '%d' ],
          [ '%d', '%d', '%s', '%d', '%d' ]
        );
        continue;
      }

      $restored += (int) $this->wpdb->update(
        $this->wpdb->prefix . 'icl_translation_status',
        [
          'status'              => $before['status'] ?? $this->notTranslatedStatus(),
          'needs_update'        => $before['needs_update'] ?? 0,
          'translation_service' => $before['service'] ?? '',
          'translator_id'       => $before['translator'] ?? 0,
          '_prevstate'          => null,
        ],
        [
          'rid'                 => $prepared->getRid(),
          'status'              => $this->inProgressStatus(),
          'translation_service' => 'local',
          'translator_id'       => 0,
          'needs_update'        => 0,
        ],
        [ '%d', '%d', '%s', '%d', '%s' ],
        [ '%d', '%d', '%s', '%d', '%d' ]
      );
    }

    return $restored;
  }


  private function hasRemoteMapping( PreparedTaxonomyTermJob $prepared ): bool {
    $wpdb = $this->wpdb;

    $editorJobId = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT editor_job_id FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d AND rid = %d",
        $prepared->getJobId(),
        $prepared->getRid()
      )
    );

    return (int) $editorJobId > 0;
  }


  private function ridHasRemoteJobSince( int $rid, int $jobId ): bool {
    $wpdb = $this->wpdb;

    $editorJobId = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT editor_job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid = %d AND job_id >= %d AND editor_job_id > 0 LIMIT 1",
        $rid,
        $jobId
      )
    );

    return (int) $editorJobId > 0;
  }


  private function restoreSupersededGeneration( PreparedTaxonomyTermJob $prepared ): void {
    if ( ! $prepared->getInsertedJobId() ) {
      return;
    }

    $wpdb = $this->wpdb;

    $newest = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT job_id, editor_job_id, translated, revision FROM {$wpdb->prefix}icl_translate_job WHERE rid = %d ORDER BY job_id DESC LIMIT 1",
        $prepared->getRid()
      ),
      ARRAY_A
    );

    if (
      is_array( $newest )
      && null !== $newest['revision']
      && ( 1 === (int) $newest['translated'] || empty( $newest['editor_job_id'] ) )
    ) {
      $wpdb->update(
        $wpdb->prefix . 'icl_translate_job',
        [ 'revision' => null ],
        [ 'job_id' => (int) $newest['job_id'] ]
      );
    }
  }


  private function deleteOwnedJobRow( PreparedTaxonomyTermJob $prepared ): void {
    if ( ! $prepared->getInsertedJobId() ) {
      return;
    }

    $wpdb = $this->wpdb;

    $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d AND rid = %d AND ( editor_job_id IS NULL OR editor_job_id = 0 )",
        $prepared->getInsertedJobId(),
        $prepared->getRid()
      ) ?? ''
    );
  }


  private function inProgressStatus(): int {
    return 2;
  }


  private function notTranslatedStatus(): int {
    return 0;
  }

}
