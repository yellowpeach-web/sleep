<?php

namespace WPML\PB\BeTheme;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class TemplateIndexHooks implements \IWPML_Action {

	const POST_TYPE = 'template';

	const TYPE_META_KEY = 'mfn_template_type';

	const ADMIN_CLASS = '\Mfn_Builder_Admin';

	const GLOBAL_TYPES = [ 'header', 'footer' ];

	const ADDON_TYPES = [ 'popup' ];

	const UNINDEXED_TYPES = [ 'cart', 'checkout', 'thanks', 'search' ];

	const DEPRECATED_TYPES = [ 'shop-archive', 'portfolio', 'blog' ];

	const METHOD_GLOBAL = 'set_global_templates_conditions';

	const METHOD_ADDONS = 'set_addons_templates_conditions';

	const METHOD_POST_TYPES = 'set_post_types_templates_conditions';

	private $adminClass;

	private $logger;

	private $logged = [];

	private $rebuiltTypes = [];

	public function __construct( $adminClass = self::ADMIN_CLASS, $logger = null ) {
		$this->adminClass = $adminClass;
		$this->logger     = is_callable( $logger ) ? $logger : [ $this, 'writeErrorLog' ];
	}

	public function add_hooks() {
		Hooks::onAction( 'wpml_pro_translation_completed', 10, 3 )
			->then( spreadArgs( [ $this, 'rebuildIndex' ] ) );

		Hooks::onAction( 'icl_make_duplicate', 10, 4 )
			->then( spreadArgs( [ $this, 'rebuildIndexForDuplicate' ] ) );

		Hooks::onAction( 'transition_post_status', 10, 3 )
			->then( spreadArgs( [ $this, 'rebuildIndexForStatusChange' ] ) );
	}

	public function rebuildIndexForDuplicate( $masterPostId, $language, $postArray, $duplicateId ) {
		$this->rebuildIndex( $duplicateId );
	}

	public function rebuildIndexForStatusChange( $newStatus, $oldStatus, $post ) {
		if ( $newStatus === $oldStatus || ( 'publish' !== $newStatus && 'publish' !== $oldStatus ) ) {
			return;
		}

		$postId = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
		if ( ! $postId || ! $this->isTemplateTranslation( $postId ) ) {
			return;
		}

		$type = $this->getIndexedType( $postId );
		if ( null === $type || isset( $this->rebuiltTypes[ $type ] ) ) {
			return;
		}

		$this->dispatch( $type );
	}

	private function isTemplateTranslation( $postId ) {
		if ( self::POST_TYPE !== get_post_type( $postId ) ) {
			return false;
		}

		$details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $postId,
				'element_type' => self::POST_TYPE,
			]
		);
		return is_object( $details ) && ! empty( $details->source_language_code );
	}

	public function rebuildIndex( $newPostId, $fields = [], $job = null ) {
		if ( self::POST_TYPE !== get_post_type( $newPostId ) ) {
			return;
		}

		$type = $this->getIndexedType( $newPostId );
		if ( null === $type ) {
			return;
		}

		$this->rebuiltTypes[ $type ] = true;
		$this->dispatch( $type );
	}

	private function getIndexedType( $postId ) {
		$type = get_post_meta( $postId, self::TYPE_META_KEY, true );
		if (
			! is_string( $type )
			|| '' === $type
			|| in_array( $type, self::UNINDEXED_TYPES, true )
			|| in_array( $type, self::DEPRECATED_TYPES, true )
		) {
			return null;
		}

		return $type;
	}

	private function dispatch( $type ) {
		$method = self::getMethod( $type );
		$admin  = $this->getAdmin( $method );
		if ( ! $admin ) {
			return;
		}

		try {
			$admin->$method( $type );
		} catch ( \Throwable $e ) {
			$this->logOnce( 'error-' . $method, sprintf( 'BeTheme %s::%s( %s ) failed after a template translation: %s', ltrim( $this->adminClass, '\\' ), $method, $type, $e->getMessage() ) );
		}
	}

	private static function getMethod( $type ) {
		if ( in_array( $type, self::GLOBAL_TYPES, true ) ) {
			return self::METHOD_GLOBAL;
		}

		if ( in_array( $type, self::ADDON_TYPES, true ) ) {
			return self::METHOD_ADDONS;
		}

		return self::METHOD_POST_TYPES;
	}

	private function getAdmin( $method ) {
		$class = ltrim( $this->adminClass, '\\' );

		if ( ! $this->loadAdminClass( $class ) ) {
			$this->logOnce( 'class', sprintf( 'BeTheme class %s is missing: the template index was not rebuilt after a template translation.', $class ) );

			return null;
		}

		if ( ! method_exists( $class, $method ) ) {
			$this->logOnce( 'method-' . $method, sprintf( 'BeTheme method %s::%s is missing: the template index was not rebuilt after a template translation.', $class, $method ) );

			return null;
		}

		return new $class( true );
	}

	private function loadAdminClass( $class ) {
		if ( class_exists( $class ) ) {
			return true;
		}

		$files = [
			'Mfn_Builder_Helper' => '/functions/builder/class-mfn-builder-helper.php',
			'Mfn_Builder_Admin'  => '/functions/builder/class-mfn-builder-admin.php',
		];

		foreach ( $files as $themeClass => $file ) {
			if ( class_exists( $themeClass ) ) {
				continue;
			}

			$path = get_theme_file_path( $file );
			if ( is_string( $path ) && is_readable( $path ) ) {
				require_once $path;
			}
		}

		return class_exists( $class );
	}

	private function logOnce( $key, $message ) {
		if ( isset( $this->logged[ $key ] ) ) {
			return;
		}

		$this->logged[ $key ] = true;
		call_user_func( $this->logger, 'WPML: ' . $message );
	}

	public function writeErrorLog( $message ) {
		error_log( $message );
	}
}
