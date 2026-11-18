<?php

namespace WPML\Templates;

use WPML\LanguageSwitcher\FolderIdentity;

class PhpTwinRenderer {

	const TWIG_EXTENSION = '.twig';
	const PHP_EXTENSION  = '.php';

	private $paths;

	private $roots;

	private $found = array();

	public function __construct( array $paths, ?array $roots = null ) {
		$this->paths = array_values( array_filter( array_map( 'strval', $paths ), 'strlen' ) );
		$this->roots = $roots;
	}

	public static function wpmlRoots() {
		$roots = array();

		foreach ( array( 'WPML_PLUGIN_PATH', 'WPML_TM_PATH', 'WPML_ST_PATH', 'WPML_MEDIA_PATH' ) as $constant ) {
			if ( defined( $constant ) && is_string( constant( $constant ) ) && '' !== constant( $constant ) ) {
				$roots[] = rtrim( constant( $constant ), '/\\' ) . '/templates';
			}
		}

		return array_values( array_unique( $roots ) );
	}

	public function find( $template ) {
		if ( ! is_string( $template ) ) {
			return null;
		}

		if ( ! array_key_exists( $template, $this->found ) ) {
			$this->found[ $template ] = $this->locate( $template );
		}

		return $this->found[ $template ];
	}

	private function locate( $template ) {
		$length = strlen( self::TWIG_EXTENSION );

		if ( substr( $template, -$length ) !== self::TWIG_EXTENSION || false !== strpos( $template, '..' ) || false !== strpos( $template, "\0" ) ) {
			return null;
		}

		$twin = substr( $template, 0, -$length ) . self::PHP_EXTENSION;

		foreach ( $this->paths as $path ) {
			$dir  = rtrim( $path, '/\\' );
			$file = $dir . '/' . ltrim( $twin, '/\\' );

			if ( is_file( $file ) ) {
				$real = realpath( $file );

				return false !== $real && FolderIdentity::inside( $real, null === $this->roots ? self::wpmlRoots() : $this->roots, true )
					? $real
					: null;
			}

			if ( is_file( $dir . '/' . ltrim( $template, '/\\' ) ) ) {
				return null;
			}
		}

		return null;
	}

	public function has( $template ) {
		return null !== $this->find( $template );
	}

	public function render( $template, array $model ) {
		$file = $this->find( $template );

		if ( null === $file ) {
			throw new \RuntimeException( sprintf( 'The view %s has no PHP twin in a WPML templates folder.', esc_html( is_string( $template ) ? $template : gettype( $template ) ) ) );
		}

		return self::capture(
			function () use ( $file, $model ) {
				self::include_file( $file, $model, $this );
			}
		);
	}

	public function show( $template, $model ) {
		if ( ! $this->has( $template ) ) {
			return null;
		}

		try {
			return $this->render( $template, is_array( $model ) ? $model : array() );
		} catch ( \Throwable $e ) {
			self::log_failure( $template, $e );

			return null;
		}
	}

	public function partial( $template, array $model = array() ) {
		$file = $this->find( $template );

		if ( null === $file ) {
			throw new \RuntimeException( sprintf( 'The partial %s has no PHP twin in a WPML templates folder.', esc_html( is_string( $template ) ? $template : gettype( $template ) ) ) );
		}

		self::include_file( $file, $model, $this );
	}

	private static function capture( callable $print ) {
		$level = ob_get_level();

		ob_start();

		try {
			$print();
		} catch ( \Throwable $e ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}

			throw $e;
		}

		return (string) ob_get_clean();
	}

	private static function include_file( $wpml_view_file, array $model, PhpTwinRenderer $wpml_view ) {
		extract( $model, EXTR_SKIP );
		include $wpml_view_file;
	}

	private static function log_failure( $template, \Throwable $e ) {
		static $logged = array();

		if ( isset( $logged[ $template ] ) ) {
			return;
		}

		$logged[ $template ] = true;

		( new \WPML_WP_API() )->error_log(
			sprintf( 'WPML: the PHP version of the view %s failed (%s); its Twig version is used.', $template, $e->getMessage() )
		);
	}
}
