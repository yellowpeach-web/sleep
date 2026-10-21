<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\Updates;

use WPML\Core\Port\Persistence\OptionsInterface;
use function WPML\PHP\Logger\error as logError;
use function WPML\PHP\Logger\notice as logNotice;

class Repository {
  const OPTION = 'wpml-updates-log';
  const OPTION_KEY_UPDATES = 'updates';

  const UPDATES_KEY_STATUS = 'status';
  const UPDATES_KEY_STARTED = 'started';
  const UPDATES_KEY_ATTEMPTS = 'attempts';
  const UPDATES_KEY_ERROR = 'error';

  const STATUS_IN_PROGRESS = 1;
  const STATUS_COMPLETED = 2;
  const STATUS_FAILED = 3;

  const STATUS_TRY_ONLY_ONCE_STUCK = 4;
  const STATUS_TRY_ONLY_ONCE_FAILED = 5;

  const STATUS_GAVE_UP = 6;

  const IN_PROGRESS_EXPIRY_SECONDS = 300;
  const RETRY_DELAY_SECONDS = 300;
  const MAX_ATTEMPTS = 3;
  const ERROR_MAX_LENGTH = 255;

  const ERROR_DID_NOT_FINISH = 'Did not finish: the request ended during the run.';

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function getUpdatesToPerform( $allUpdates ) {
    $log = $this->getLog();

    $updatesToPerform = [];

    foreach ( $allUpdates as $update ) {
      if ( ! $update instanceof Update ) {
        continue;
      }

      $entry = $this->entryFromLog( $log, $update->id() );

      if ( ! $this->shouldPerform( $update, $entry ) ) {
        continue;
      }

      $updatesToPerform[ $update->id() ] = $update;
    }

    return $updatesToPerform;
  }


  public function setUpdateInProgress( $update ) {
    $this->startUpdate( $update, self::STATUS_IN_PROGRESS );
  }


  public function setUpdateComplete( $update ) {
    $this->finishUpdate( $update, self::STATUS_COMPLETED );
  }


  public function setUpdateFailed( $update, $error = '' ) {
    $this->finishUpdate( $update, self::STATUS_FAILED, $error );
  }


  public function setUpdateTryOnlyOnceStuck( $update ) {
    $this->startUpdate( $update, self::STATUS_TRY_ONLY_ONCE_STUCK );
  }


  public function setUpdateTryOnlyOnceFailed( $update, $error = '' ) {
    $this->finishUpdate( $update, self::STATUS_TRY_ONLY_ONCE_FAILED, $error );
  }


  protected function now() {
    return time();
  }


  private function shouldPerform( $update, $entry ) {
    if ( $entry === null ) {
      return true;
    }

    switch ( $this->status( $entry ) ) {
      case self::STATUS_COMPLETED:
      case self::STATUS_TRY_ONLY_ONCE_STUCK:
      case self::STATUS_TRY_ONLY_ONCE_FAILED:
      case self::STATUS_GAVE_UP:
        return false;

      case self::STATUS_IN_PROGRESS:
        if ( ! $this->isExpired( $entry ) ) {
          return false;
        }
        break;

      case self::STATUS_FAILED:
        if (
          $this->attempts( $entry ) < self::MAX_ATTEMPTS
          && ! $this->isRetryDue( $entry )
        ) {
          return false;
        }
        break;

      default:
        return true;
    }

    if ( $this->attempts( $entry ) < self::MAX_ATTEMPTS ) {
      return true;
    }

    $this->giveUp( $update, $entry );

    return false;
  }


  private function isExpired( $entry ) {
    return $this->startedAtLeastSecondsAgo( $entry, self::IN_PROGRESS_EXPIRY_SECONDS );
  }


  private function isRetryDue( $entry ) {
    return $this->startedAtLeastSecondsAgo( $entry, self::RETRY_DELAY_SECONDS );
  }


  private function startedAtLeastSecondsAgo( $entry, $seconds ) {
    $started = $entry[ self::UPDATES_KEY_STARTED ] ?? null;

    if ( ! is_int( $started ) ) {
      return true;
    }

    $now = $this->now();

    return $now - $started >= $seconds
      || $started > $now + $seconds;
  }


  private function status( $entry ) {
    $status = $entry[ self::UPDATES_KEY_STATUS ] ?? null;

    return is_numeric( $status ) ? (int) $status : null;
  }


  private function attempts( $entry ) {
    if ( $entry === null ) {
      return 0;
    }

    $attempts = $entry[ self::UPDATES_KEY_ATTEMPTS ] ?? null;
    if ( is_int( $attempts ) && $attempts > 0 ) {
      return $attempts;
    }

    return in_array( $this->status( $entry ), [ self::STATUS_IN_PROGRESS, self::STATUS_FAILED ], true )
      ? 1
      : 0;
  }


  private function error( $entry ) {
    if ( $entry === null ) {
      return '';
    }

    $error = $entry[ self::UPDATES_KEY_ERROR ] ?? '';

    return is_string( $error ) ? $error : '';
  }


  private function giveUp( $update, $entry ) {
    $attempts = $this->attempts( $entry );

    $error = $this->error( $entry );
    if ( $error === '' ) {
      $error = self::ERROR_DID_NOT_FINISH;
    }

    $entry[ self::UPDATES_KEY_STATUS ] = self::STATUS_GAVE_UP;
    $entry[ self::UPDATES_KEY_ERROR ] = $error;

    $this->saveEntry( $update->id(), $entry );

    logError(
      $this->logPrefix( $update )
      . ' gave up after ' . $attempts . ' attempts.'
      . ' Last error: ' . $error
    );
  }


  private function startUpdate( $update, $status ) {
    $previous = $this->getEntry( $update->id() );
    $attempts = $this->attempts( $previous ) + 1;

    $entry = [
      self::UPDATES_KEY_STATUS   => $status,
      self::UPDATES_KEY_STARTED  => $this->now(),
      self::UPDATES_KEY_ATTEMPTS => $attempts,
    ];

    $previousError = $this->error( $previous );
    if ( $previousError !== '' ) {
      $entry[ self::UPDATES_KEY_ERROR ] = $previousError;
    }

    $this->saveEntry( $update->id(), $entry );

    if ( $previous !== null && $this->status( $previous ) === self::STATUS_IN_PROGRESS ) {
      logNotice(
        $this->logPrefix( $update )
        . ' did not finish last time; retrying (attempt ' . $attempts . ').'
      );
    }
  }


  private function finishUpdate( $update, $status, $error = '' ) {
    $entry = $this->getEntry( $update->id() ) ?? [];
    $entry[ self::UPDATES_KEY_STATUS ] = $status;

    $error = mb_substr( $error, 0, self::ERROR_MAX_LENGTH, 'UTF-8' );

    if ( $error === '' ) {
      unset( $entry[ self::UPDATES_KEY_ERROR ] );
    } else {
      $entry[ self::UPDATES_KEY_ERROR ] = $error;
    }

    $this->saveEntry( $update->id(), $entry );

    if ( $status !== self::STATUS_COMPLETED ) {
      logError( $this->logPrefix( $update ) . ' failed: ' . $error );
    }
  }


  private function logPrefix( $update ) {
    return 'WPML update ' . $update->id() . ' (' . $update->handlerClassName() . ')';
  }


  private function getLog() {
    $option = $this->options->get( self::OPTION, false );
    if (
      ! is_array( $option )
      || array_key_exists( 'updated_to', $option )
      || ! array_key_exists( self::OPTION_KEY_UPDATES, $option )
    ) {
      return $this->freshLog();
    }

    return $option;
  }


  private function entryFromLog( $log, $updateId ) {
    $updates = $log[ self::OPTION_KEY_UPDATES ] ?? null;
    $entry = is_array( $updates ) ? ( $updates[ $updateId ] ?? null ) : null;

    return is_array( $entry ) ? $entry : null;
  }


  private function getEntry( $updateId ) {
    $log = $this->options->get( self::OPTION, [] );

    return $this->entryFromLog( is_array( $log ) ? $log : [], $updateId );
  }


  private function saveEntry( $updateId, $entry ) {
    $log = $this->options->get( self::OPTION, [] );
    $log = is_array( $log ) ? $log : [];

    if (
      ! isset( $log[ self::OPTION_KEY_UPDATES ] )
      || ! is_array( $log[ self::OPTION_KEY_UPDATES ] )
    ) {
      $log[ self::OPTION_KEY_UPDATES ] = [];
    }

    $log[ self::OPTION_KEY_UPDATES ][ $updateId ] = $entry;

    $this->options->save( self::OPTION, $log, true );
  }


  private function freshLog() {
    $freshLog = [
      self::OPTION_KEY_UPDATES => [],
    ];

    $this->options->save(
      self::OPTION,
      $freshLog,
      true
    );

    return $freshLog;
  }


}
