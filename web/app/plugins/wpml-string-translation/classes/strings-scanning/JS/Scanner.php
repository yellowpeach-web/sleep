<?php

namespace WPML\ST\StringsScanning\JS;

class Scanner {

	public function scan( $file, $componentDomain, $storeResults ) {
		try {
			$xdebugMaxNestingLevel = (string) ini_get( 'xdebug.max_nesting_level' );
			ini_set( 'xdebug.max_nesting_level', '1024' );

			$calls = ( new GettextCalls() )->find( (string) file_get_contents( $file ) );

			list( $handle, $textdomain ) = ScriptRegistry::getHandleAndTextdomainByPath( $file );

			if ( ! $handle ) {
				return;
			}

			$textdomain = $textdomain ?? $componentDomain;
			$jedDomain  = \WPML_ST_JED_Domain::get( $textdomain, $handle );

			$stored = [];

			foreach ( $calls as $call ) {
				$context = (string) $call['context'];
				$id      = $context . "\004" . $call['text'];

				if ( isset( $stored[ $id ] ) ) {
					continue;
				}

				$stored[ $id ] = true;
				$storeResults( $call['text'], $jedDomain, $context, $file, $call['line'], $textdomain );
			}

			ini_set( 'xdebug.max_nesting_level', $xdebugMaxNestingLevel );
		} catch ( \Throwable $e ) {
			error_log( 'Failure in JS string scanning: ' . $e->getMessage() );
		}
	}
}
