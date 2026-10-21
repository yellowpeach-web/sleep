<?php

namespace WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation;

final class AteResponse {


  public static function getConfirmedJobIds( $response ) {
    if ( is_array( $response ) ) {
      if ( ! array_key_exists( 'jobs', $response ) ) {
        return null;
      }

      $jobs = $response['jobs'];
    } elseif ( is_object( $response ) ) {
      if ( ! property_exists( $response, 'jobs' ) && ! isset( $response->jobs ) ) {
        return null;
      }

      $jobs = $response->jobs;
    } else {
      return null;
    }

    if ( ! is_array( $jobs ) ) {
      return null;
    }

    return array_values( array_unique( array_filter( array_map( 'intval', $jobs ) ) ) );
  }


  public static function getReportedJobIds( $response ) {
    $hidden = self::getConfirmedJobIds( $response );

    if ( null === $hidden ) {
      return null;
    }

    $released    = self::getReleasedJobs( $response );
    $notReleased = self::getNotReleasedJobs( $response );

    if ( null === $released && null === $notReleased ) {
      return null;
    }

    $ids = $hidden;

    foreach ( [ $released, $notReleased ] as $keyedByJobId ) {
      if ( is_array( $keyedByJobId ) ) {
        $ids = array_merge( $ids, array_keys( $keyedByJobId ) );
      }
    }

    $confirmed = self::readListField( $response, 'confirmed' );

    if ( is_array( $confirmed ) ) {
      foreach ( $confirmed as $row ) {
        $ids[] = is_scalar( $row ) ? $row : self::readInt( $row, 'job_id' );
      }
    }

    $ids = array_unique( array_filter( array_map( 'intval', $ids ) ) );
    sort( $ids );

    return $ids;
  }


  public static function getReleasedJobs( $response ) {
    $rows = self::readListField( $response, 'released' );

    if ( null === $rows ) {
      return null;
    }

    $released = [];
    foreach ( $rows as $row ) {
      $jobId = self::readInt( $row, 'job_id' );

      if ( $jobId <= 0 ) {
        continue;
      }

      $ledgerId = self::readField( $row, 'ledger_id' );

      $released[ $jobId ] = [
        'words'     => self::readInt( $row, 'words' ),
        'credits'   => self::readInt( $row, 'credits' ),
        'ledger_id' => is_scalar( $ledgerId ) ? (string) $ledgerId : null,
      ];
    }

    return $released;
  }


  public static function getNotReleasedJobs( $response ) {
    $rows = self::readListField( $response, 'not_released' );

    if ( null === $rows ) {
      return null;
    }

    $notReleased = [];
    foreach ( $rows as $row ) {
      $jobId = self::readInt( $row, 'job_id' );

      if ( $jobId <= 0 ) {
        continue;
      }

      $reason = self::readField( $row, 'reason' );

      $notReleased[ $jobId ] = is_scalar( $reason ) ? (string) $reason : 'unknown';
    }

    return $notReleased;
  }


  private static function readListField( $response, string $field ) {
    $value = self::readField( $response, $field );

    if ( ! is_array( $value ) ) {
      return null;
    }

    return array_values( $value );
  }


  private static function readInt( $subject, string $field ): int {
    $value = self::readField( $subject, $field );

    return is_numeric( $value ) ? (int) $value : 0;
  }


  private static function readField( $subject, string $field ) {
    if ( is_array( $subject ) ) {
      return $subject[ $field ] ?? null;
    }

    if ( is_object( $subject ) ) {
      return $subject->{$field} ?? null;
    }

    return null;
  }


}
