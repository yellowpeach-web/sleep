<?php

namespace WPML\PB\Cornerstone\Hooks;

use WPML\LIB\WP\Hooks;
use WPML\Utils\DebugBackTrace;

class SuppressFilters implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	const CORNERSTONE_PRIORITY           = 10;
	const PRIORITY_BEFORE_CORNERSTONE    = 9;
	const CORNERSTONE_TWIG_CACHE_PATH_FN = 'cs_twig_cache_directory';

	private $debugBackTrace;

	private $isWrapped = false;

	private $cornerstonePaths;

	public function __construct( DebugBackTrace $debugBackTrace ) {
		$this->debugBackTrace = $debugBackTrace;
	}

	public function add_hooks() {
		Hooks::onAction( 'pre_get_posts', self::PRIORITY_BEFORE_CORNERSTONE )
			->then( [ $this, 'wrapCornerstoneCallback' ] );
	}

	public function wrapCornerstoneCallback() {
		if ( $this->isWrapped ) {
			return;
		}

		$callback = $this->findCornerstoneCallback();

		if ( ! $callback ) {
			return;
		}

		add_action(
			'pre_get_posts',
			function ( $query ) use ( $callback ) {
				$this->runCornerstoneCallback( $callback, $query );
			},
			self::CORNERSTONE_PRIORITY
		);
		remove_action( 'pre_get_posts', $callback, self::CORNERSTONE_PRIORITY );

		$this->isWrapped = true;
	}

	public function runCornerstoneCallback( $callback, $query ) {
		if ( ! $query instanceof \WP_Query ) {
			call_user_func( $callback, $query );

			return;
		}

		$suppressFilters = $query->get( 'suppress_filters' );

		call_user_func( $callback, $query );

		if ( $suppressFilters && ! $query->get( 'suppress_filters' ) && ! $this->isCalledByCornerstone() ) {
			$query->set( 'suppress_filters', $suppressFilters );
		}
	}

	private function findCornerstoneCallback() {
		$callbacks = $GLOBALS['wp_filter']['pre_get_posts']->callbacks[ self::CORNERSTONE_PRIORITY ] ?? [];

		foreach ( $callbacks as $callback ) {
			if ( $this->isCornerstoneCallback( $callback['function'] ) ) {
				return $callback['function'];
			}
		}

		return null;
	}

	private function isCornerstoneCallback( $callback ): bool {
		return is_array( $callback )
			&& isset( $callback[0], $callback[1] )
			&& is_a( $callback[0], 'Themeco\Cornerstone\Services\Wpml' )
			&& 'pre_get_posts' === $callback[1];
	}

	private function isCalledByCornerstone(): bool {
		foreach ( $this->debugBackTrace->get_backtrace() as $frame ) {
			if ( ! isset( $frame['file'] ) ) {
				continue;
			}

			$file = wp_normalize_path( $frame['file'] );

			if ( ! $this->isBetweenQueryAndCaller( $file ) ) {
				return $this->isCornerstoneFile( $file );
			}
		}

		return false;
	}

	private function isBetweenQueryAndCaller( string $file ): bool {
		return wp_normalize_path( __FILE__ ) === $file
			|| 0 === strpos( $file, wp_normalize_path( ABSPATH . WPINC . '/' ) );
	}

	private function isCornerstoneFile( string $file ): bool {
		foreach ( $this->getCornerstonePaths() as $path ) {
			if ( 0 === strpos( $file, $path ) ) {
				return true;
			}
		}

		return false;
	}

	private function getCornerstonePaths(): array {
		if ( null === $this->cornerstonePaths ) {
			$paths = [ constant( 'CS_ROOT_PATH' ) ];

			if ( function_exists( self::CORNERSTONE_TWIG_CACHE_PATH_FN ) ) {
				$paths[] = call_user_func( self::CORNERSTONE_TWIG_CACHE_PATH_FN );
			}

			$this->cornerstonePaths = [];

			foreach ( array_filter( $paths, 'is_string' ) as $path ) {
				if ( '' !== trim( $path, '/\\' ) ) {
					$this->cornerstonePaths[] = trailingslashit( wp_normalize_path( $path ) );
				}
			}
		}

		return $this->cornerstonePaths;
	}
}
