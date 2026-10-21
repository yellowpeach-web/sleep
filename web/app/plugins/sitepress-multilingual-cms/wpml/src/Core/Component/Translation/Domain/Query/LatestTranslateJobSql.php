<?php

namespace WPML\Core\Component\Translation\Domain\Query;

class LatestTranslateJobSql {


  public static function correlatedDerivedTable( string $prefix ): string {
    return "
      SELECT translate_job.*
      FROM {$prefix}icl_translate_job as translate_job
      INNER JOIN (
        " . self::maxJobIdPerRidDerivedTable( $prefix ) . "
      ) latest_job_per_rid
        ON latest_job_per_rid.job_id = translate_job.job_id
    ";
  }


  public static function leftJoinLatestJob( string $prefix, string $ridReference ): string {
    return "
      LEFT JOIN (
        SELECT `translate_job`.*
        FROM `{$prefix}icl_translate_job` AS `translate_job`
        INNER JOIN ( " . self::maxJobIdPerRidDerivedTable( $prefix ) . " ) `latest_job_per_rid`
          ON `latest_job_per_rid`.`job_id` = `translate_job`.`job_id`
      ) AS `job` ON `job`.`rid` = {$ridReference}";
  }


  public static function maxJobIdPerRidDerivedTable( string $prefix ): string {
    return "SELECT rid, MAX(job_id) job_id FROM {$prefix}icl_translate_job GROUP BY rid";
  }


}
