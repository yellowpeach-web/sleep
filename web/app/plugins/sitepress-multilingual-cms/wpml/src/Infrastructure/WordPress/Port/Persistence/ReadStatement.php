<?php

namespace WPML\Infrastructure\WordPress\Port\Persistence;

final class ReadStatement {


  public static function assert( string $sql ): void {
    if ( ! preg_match( '~^(?:\s+|\(|/\*.*?\*/|--[^\n]*)*(?:SELECT|WITH|SHOW|DESCRIBE|DESC|EXPLAIN)\b~is', $sql ) ) {
      throw new \InvalidArgumentException( 'QueryHandler runs read statements only; a write goes through DatabaseWriteInterface::execute().' );
    }
  }


}
