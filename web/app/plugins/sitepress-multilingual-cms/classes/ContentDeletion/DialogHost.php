<?php

namespace WPML\ContentDeletion;

use WPML\Core\WP\App\Resources;

class DialogHost implements \IWPML_Backend_Action {

	const APP = 'deletion-dialog';

	const HANDLE = 'wpml-deletion-dialog-ui';

	const GLOBAL_NAME = 'wpmlDeletionDialog';

	const MOUNT_ID = 'wpml-deletion-dialog-root';

	const RESTORE_SELECTOR = 'span.untrash a';

	private static function documentWords() {
		return array(
			'post'       => array( /* translators: The name of a content type, used inside sentences of the dialog shown before content is deleted, as in "This post has 3 translations". Singular, in lower case. */ __( 'post', 'sitepress' ), /* translators: The name of a content type, used inside sentences of the dialog shown before content is deleted, as in "3 posts moved to the Trash". Plural, in lower case. */ __( 'posts', 'sitepress' ) ),
			'page'       => array( /* translators: The name of a content type, used inside sentences of the dialog shown before content is deleted, as in "This page has 3 translations". Singular, in lower case. */ __( 'page', 'sitepress' ), /* translators: The name of a content type, used inside sentences of the dialog shown before content is deleted, as in "3 pages deleted". Plural, in lower case. */ __( 'pages', 'sitepress' ) ),
			'attachment' => array( /* translators: The name of a content type, used inside sentences of the dialog shown before content is deleted, as in "This image has 3 translations". Singular, in lower case. */ __( 'image', 'sitepress' ), /* translators: The name of a content type, used inside sentences of the dialog shown before content is deleted, as in "3 images deleted". Plural, in lower case. */ __( 'images', 'sitepress' ) ),
			'product'    => array( /* translators: The name of a content type, used inside sentences of the dialog shown before content is deleted, as in "This product has 3 translations". Singular, in lower case. */ __( 'product', 'sitepress' ), /* translators: The name of a content type, used inside sentences of the dialog shown before content is deleted, as in "3 products deleted". Plural, in lower case. */ __( 'products', 'sitepress' ) ),
		);
	}

	private static function taxonomyWords() {
		return array(
			'category' => array( /* translators: The name of a kind of term, used inside sentences of the dialog shown before content is deleted, as in "This category exists in French and German". Singular, in lower case. */ __( 'category', 'sitepress' ), /* translators: The name of a kind of term, used inside sentences of the dialog shown before content is deleted, as in "3 categories deleted". Plural, in lower case. */ __( 'categories', 'sitepress' ) ),
			'post_tag' => array( /* translators: The name of a kind of term, used inside sentences of the dialog shown before content is deleted, as in "This tag exists in French and German". Singular, in lower case. */ __( 'tag', 'sitepress' ), /* translators: The name of a kind of term, used inside sentences of the dialog shown before content is deleted, as in "3 tags deleted". Plural, in lower case. */ __( 'tags', 'sitepress' ) ),
			'nav_menu' => array( /* translators: The name of a kind of term, used inside sentences of the dialog shown before content is deleted, as in "This menu exists in French and German". Singular, in lower case. */ __( 'menu', 'sitepress' ), /* translators: The name of a kind of term, used inside sentences of the dialog shown before content is deleted, as in "3 menus deleted". Plural, in lower case. */ __( 'menus', 'sitepress' ) ),
		);
	}

	private static function surfaces() {
		return array(
			'edit.php'      => array(
				'surface'       => 'edit',
				'type'          => Endpoint\PreflightDeletion::TYPE_POST,
				'selector'      => 'a.submitdelete',
				'nativeConfirm' => false,
				'restore'       => true,
			),
			'post.php'      => array(
				'surface'       => 'post',
				'type'          => Endpoint\PreflightDeletion::TYPE_POST,
				'selector'      => 'a.submitdelete',
				'nativeConfirm' => false,
				'restore'       => false,
			),
			'upload.php'    => array(
				'surface'       => 'media',
				'type'          => Endpoint\PreflightDeletion::TYPE_POST,
				'selector'      => 'a.submitdelete',
				'nativeConfirm' => true,
				'restore'       => true,
			),
			'edit-tags.php' => array(
				'surface'       => 'terms',
				'type'          => Endpoint\PreflightDeletion::TYPE_TERM,
				'selector'      => 'a.delete-tag',
				'nativeConfirm' => true,
				'restore'       => false,
			),
			'nav-menus.php' => array(
				'surface'       => 'menus',
				'type'          => Endpoint\PreflightDeletion::TYPE_TERM,
				'selector'      => 'a.menu-delete',
				'nativeConfirm' => true,
				'restore'       => false,
			),
		);
	}

	private $armed = false;

	public function add_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_footer', array( $this, 'printMount' ) );
	}

	public function enqueue( $hook_suffix = '' ) {
		$surface = $this->armedSurface( (string) $hook_suffix );

		if ( null === $surface ) {
			return;
		}

		$this->armed = true;

		$this->enqueueElement();

		$enqueue = Resources::enqueueAppWithoutVendor( self::APP );
		$enqueue();

		wp_add_inline_script(
			self::HANDLE,
			'window.' . self::GLOBAL_NAME . ' = ' . (string) wp_json_encode( $this->bootstrap( $surface ) ) . ';',
			'before'
		);
	}

	public function printMount() {
		if ( ! $this->armed ) {
			return;
		}

		echo '<div id="' . esc_attr( self::MOUNT_ID ) . '"></div>';
	}

	private function armedSurface( $hook_suffix ) {
		$surfaces = self::surfaces();

		if ( ! isset( $surfaces[ $hook_suffix ] ) ) {
			return null;
		}

		global $sitepress;

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'is_translated_post_type' ) ) {
			return null;
		}

		if ( ! class_exists( Rows::class ) || ! class_exists( Endpoints::class ) ) {
			return null;
		}

		$languages = method_exists( $sitepress, 'get_active_languages' )
			? (array) $sitepress->get_active_languages()
			: array();
		if ( count( $languages ) <= 1 ) {
			return null;
		}

		$surface = $surfaces[ $hook_suffix ];

		return Endpoint\PreflightDeletion::TYPE_TERM === $surface['type']
			? $this->armedTaxonomy( $surface, $hook_suffix )
			: $this->armedPostType( $surface, $hook_suffix );
	}

	private function armedPostType( array $surface, $hook_suffix ) {
		global $sitepress;

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! is_object( $screen ) || empty( $screen->post_type ) ) {
			return null;
		}

		$post_type = (string) $screen->post_type;

		if ( 'upload.php' === $hook_suffix ) {
			$post_type = 'attachment';
		}

		if ( ! $sitepress->is_translated_post_type( $post_type ) ) {
			return null;
		}

		$words = $this->documentWordsFor( $post_type );

		return array_merge(
			$surface,
			array(
				'documentType' => $post_type,
				'elementKey'   => Settings::postKey( $post_type ),
				'taxonomy'     => '',
				'typeSingular' => $words[0],
				'typePlural'   => $words[1],
			)
		);
	}

	private function armedTaxonomy( array $surface, $hook_suffix ) {
		global $sitepress;

		if ( ! method_exists( $sitepress, 'is_translated_taxonomy' ) ) {
			return null;
		}

		if ( 'nav-menus.php' === $hook_suffix ) {
			$taxonomy = 'nav_menu';
		} else {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( ! is_object( $screen ) || empty( $screen->taxonomy ) ) {
				return null;
			}

			$taxonomy = (string) $screen->taxonomy;
		}

		if ( ! $sitepress->is_translated_taxonomy( $taxonomy ) ) {
			return null;
		}

		$words = $this->taxonomyWordsFor( $taxonomy );

		return array_merge(
			$surface,
			array(
				'documentType' => $taxonomy,
				'elementKey'   => Settings::termKey( $taxonomy ),
				'taxonomy'     => $taxonomy,
				'typeSingular' => $words[0],
				'typePlural'   => $words[1],
			)
		);
	}

	private function bootstrap( array $surface ) {
		$endpoints = array();
		foreach ( Endpoints::get() as $action => $class ) {
			$endpoints[ $action ] = array(
				'endpoint' => $class,
				'nonce'    => \WPML\LIB\WP\Nonce::create( $class ),
			);
		}

		$remember = $this->rememberData( (string) $surface['elementKey'] );
		$can_remember = current_user_can( 'wpml_manage_languages' );

		return array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'endpoints'       => $endpoints,
			'preflightAction' => Endpoints::PREFLIGHT,
			'saveAction'      => Endpoints::SAVE,
			'restoreAction'   => Endpoints::PREFLIGHT_RESTORE,
			'undoAction'      => Endpoints::UNDO,
			'restore'         => empty( $surface['restore'] )
				? null
				: array( 'linkSelector' => self::RESTORE_SELECTOR ),
			'mountId'         => self::MOUNT_ID,
			'surface'         => (string) $surface['surface'],
			'type'            => (string) $surface['type'],
			'taxonomy'        => (string) $surface['taxonomy'],
			'linkSelector'    => (string) $surface['selector'],
			'nativeConfirm'   => (bool) $surface['nativeConfirm'],
			'postType'        => (string) $surface['documentType'],
			'elementKey'      => (string) $surface['elementKey'],
			'typeSingular'    => (string) $surface['typeSingular'],
			'typePlural'      => (string) $surface['typePlural'],
			'escalationRefusal' => __(
				'Nothing was deleted. The original can no longer be deleted from this view — it may have been removed, or this account may not have permission. Reload the page to see the current state.',
				'sitepress'
			),
			'rememberLabel'   => $can_remember ? $remember['label'] : '',
			'rememberMembers' => $can_remember ? $remember['members'] : array(),
			'canRemember'     => $can_remember && '' !== $remember['label'],
			'rememberTable'   => $can_remember ? $remember['table'] : null,
		);
	}

	private function documentWordsFor( $post_type ) {
		$words = self::documentWords();

		if ( isset( $words[ $post_type ] ) ) {
			return $words[ $post_type ];
		}

		$object = function_exists( 'get_post_type_object' ) ? get_post_type_object( $post_type ) : null;

		$singular = is_object( $object ) && ! empty( $object->labels->singular_name )
			? (string) $object->labels->singular_name
			: $post_type;
		$plural   = is_object( $object ) && ! empty( $object->labels->name )
			? (string) $object->labels->name
			: $post_type;

		return array( $singular, $plural );
	}

	private function taxonomyWordsFor( $taxonomy ) {
		$words = self::taxonomyWords();

		if ( isset( $words[ $taxonomy ] ) ) {
			return $words[ $taxonomy ];
		}

		$object = function_exists( 'get_taxonomy' ) ? get_taxonomy( $taxonomy ) : null;

		$singular = is_object( $object ) && ! empty( $object->labels->singular_name )
			? (string) $object->labels->singular_name
			: $taxonomy;
		$plural   = is_object( $object ) && ! empty( $object->labels->name )
			? (string) $object->labels->name
			: $taxonomy;

		return array( $singular, $plural );
	}

	private function rememberData( $key ) {
		$table   = array(
			Settings::ORIGINALS    => array(),
			Settings::TRANSLATIONS => array(),
		);
		$label   = '';
		$members = array();

		foreach ( Rows::withValues() as $row ) {
			if ( ! empty( $row['locked'] ) ) {
				continue;
			}

			$row_members = array_map( 'strval', (array) $row['members'] );

			foreach ( $row_members as $member ) {
				$table[ Settings::ORIGINALS ][ $member ]    = (string) $row['original'];
				$table[ Settings::TRANSLATIONS ][ $member ] = (string) $row['translation'];

				if ( $member === $key ) {
					$label   = (string) $row['label'];
					$members = $row_members;
				}
			}
		}

		return array(
			'table'   => $table,
			'label'   => $label,
			'members' => $members,
		);
	}

	private function enqueueElement() {
		$base = defined( 'WPML_PUBLIC_DIR' ) ? WPML_PUBLIC_DIR : __FILE__;

		wp_enqueue_script(
			'wpml-node-modules',
			plugins_url( 'public/js/node-modules.js', $base ),
			array(),
			ICL_SITEPRESS_VERSION,
			true
		);
		wp_enqueue_script(
			'wpml-wc-deletion-dialog',
			plugins_url( 'public/js/wc-deletion-dialog.js', $base ),
			array( 'wpml-node-modules', 'wp-i18n' ),
			ICL_SITEPRESS_VERSION,
			true
		);
		wp_set_script_translations(
			'wpml-wc-deletion-dialog',
			'wpml',
			defined( 'WPML_ROOT_DIR' ) ? WPML_ROOT_DIR . '/languages/' : ''
		);
		wp_enqueue_style(
			'wpml-wc-deletion-dialog',
			plugins_url( 'public/css/wc-deletion-dialog.css', $base ),
			array(),
			ICL_SITEPRESS_VERSION
		);
	}
}
