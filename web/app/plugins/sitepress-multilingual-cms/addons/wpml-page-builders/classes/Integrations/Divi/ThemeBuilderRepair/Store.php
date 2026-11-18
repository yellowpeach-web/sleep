<?php

namespace WPML\Compatibility\Divi\ThemeBuilderRepair;

use WPML\Compatibility\Divi\Hooks\ThemeBuilderOneList;

class Store {

	const ELEMENT_TYPE = 'post_et_theme_builder';

	const LIBRARY_META = '_et_library_theme_builder';

	const TEMPLATES_META = '_et_template';

	const RECORD_META = '_wpml_divi_theme_builder_repair';

	const LAST_OPTION = 'wpml_divi_theme_builder_repair_last';

	const LINKS_OPTION = 'wpml_divi_theme_builder_layout_links';

	const TEMPLATE_KEYS = [
		'_et_use_on',
		'_et_exclude_from',
		'_et_default',
		'_et_header_layout_id',
		'_et_body_layout_id',
		'_et_footer_layout_id',
	];

	private $wpdb;

	private $sitepress;

	public function __construct( $wpdb, $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function defaultLanguage() {
		return (string) $this->sitepress->get_default_language();
	}

	public function publishedThemeBuilderPosts() {
		$wpdb = $this->wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS title, p.post_date AS date, t.language_code AS language
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = %s
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = %s AND p.post_status = %s AND m.meta_id IS NULL
				ORDER BY p.post_date DESC, p.ID DESC",
				self::ELEMENT_TYPE,
				self::LIBRARY_META,
				'et_theme_builder',
				'publish'
			)
		);

		$posts = [];
		foreach ( $rows as $row ) {
			$posts[] = [
				'id'       => (int) $row->id,
				'title'    => (string) $row->title,
				'date'     => (string) $row->date,
				'language' => null === $row->language || '' === $row->language ? null : (string) $row->language,
			];
		}

		return $posts;
	}

	public function repairedThemeBuilderPosts() {
		$wpdb = $this->wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS title, p.post_date AS date, m.meta_value AS record
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = %s AND p.post_status = %s
				ORDER BY p.post_date DESC, p.ID DESC",
				self::RECORD_META,
				'et_theme_builder',
				'draft'
			)
		);

		$posts = [];
		foreach ( $rows as $row ) {
			$record   = \WPML\Utils\Unserialize::maybe( $row->record );
			$language = is_array( $record ) && isset( $record['language'] ) && is_string( $record['language'] ) && '' !== $record['language'] ? $record['language'] : null;
			$posts[]  = [
				'id'       => (int) $row->id,
				'title'    => (string) $row->title,
				'date'     => (string) $row->date,
				'language' => $language,
			];
		}

		return $posts;
	}

	public function templateFacts( $templateId ) {
		$wpdb = $this->wpdb;
		$keys = self::TEMPLATE_KEYS;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN (" . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ')',
				array_merge( [ (int) $templateId ], $keys )
			)
		);

		$useOn   = [];
		$exclude = [];
		$single  = [];
		foreach ( $rows as $row ) {
			if ( '_et_use_on' === $row->meta_key ) {
				$useOn[] = (string) $row->meta_value;
			} elseif ( '_et_exclude_from' === $row->meta_key ) {
				$exclude[] = 'exclude:' . $row->meta_value;
			} else {
				$single[ $row->meta_key ] = (string) $row->meta_value;
			}
		}
		sort( $useOn );
		sort( $exclude );

		$trid = $this->sitepress->get_element_trid( (int) $templateId, 'post_et_template' );

		return [
			'trid'       => $trid ? (int) $trid : null,
			'default'    => isset( $single['_et_default'] ) && '1' === $single['_et_default'],
			'conditions' => array_merge( $useOn, $exclude ),
			'layouts'    => [
				'header' => isset( $single['_et_header_layout_id'] ) ? (int) $single['_et_header_layout_id'] : 0,
				'body'   => isset( $single['_et_body_layout_id'] ) ? (int) $single['_et_body_layout_id'] : 0,
				'footer' => isset( $single['_et_footer_layout_id'] ) ? (int) $single['_et_footer_layout_id'] : 0,
			],
		];
	}

	public function isTranslatable( $postType ) {
		return (bool) $this->sitepress->is_translated_post_type( $postType );
	}

	public function layoutRow( $layoutId, $postType ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.post_type AS type, t.trid, t.language_code AS language,
					( SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations g WHERE g.trid = t.trid AND g.element_type = t.element_type ) AS members
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = %s
				WHERE p.ID = %d",
				'post_' . $postType,
				(int) $layoutId
			)
		);

		if ( ! is_object( $row ) || $postType !== $row->type ) {
			return null;
		}

		return [
			'type'     => (string) $row->type,
			'trid'     => $row->trid ? (int) $row->trid : null,
			'language' => null === $row->language || '' === $row->language ? null : (string) $row->language,
			'members'  => (int) $row->members,
		];
	}

	public function translationIn( $trid, $postType, $language ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND element_type = %s AND language_code = %s LIMIT 1",
				(int) $trid,
				'post_' . $postType,
				$language
			)
		);
	}

	public function link( $layoutId, $postType, $trid, $language, $sourceLanguage ) {
		$result = $this->sitepress->set_element_language_details( (int) $layoutId, 'post_' . $postType, (int) $trid, $language, $sourceLanguage );

		do_action( 'wpml_cache_clear' );

		return (bool) $result;
	}

	public function writeLinks( array $links ) {
		$all  = (array) get_option( self::LINKS_OPTION, [] );
		$time = gmdate( 'Y-m-d H:i:s' );
		foreach ( $links as $link ) {
			$all[] = $link + [ 'time' => $time ];
		}

		update_option( self::LINKS_OPTION, $all, false );
	}

	public function templates( $themeBuilderId ) {
		$templates = [];
		foreach ( (array) get_post_meta( $themeBuilderId, self::TEMPLATES_META, false ) as $templateId ) {
			$templateId = (int) $templateId;
			if ( $templateId > 0 ) {
				$templates[] = [
					'id'    => $templateId,
					'title' => (string) get_the_title( $templateId ),
				];
			}
		}

		return $templates;
	}

	public function languageRowCount() {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations WHERE element_type = %s", self::ELEMENT_TYPE )
		);
	}

	public function storedModes() {
		$sync     = (array) $this->sitepress->get_setting( ThemeBuilderOneList::SYNC_OPTION, [] );
		$unlocked = (array) $this->sitepress->get_setting( ThemeBuilderOneList::UNLOCK_OPTION, [] );

		$modes = [];
		foreach ( ThemeBuilderOneList::POST_TYPES as $postType ) {
			$modes[ $postType ] = [
				'mode'     => isset( $sync[ $postType ] ) ? (int) $sync[ $postType ] : ThemeBuilderOneList::MODE_NOT_TRANSLATABLE,
				'unlocked' => ! empty( $unlocked[ $postType ] ),
			];
		}

		return $modes;
	}

	public function unpublish( $themeBuilderId, array $record ) {
		update_post_meta( $themeBuilderId, self::RECORD_META, $record );

		$result = wp_update_post(
			[
				'ID'          => $themeBuilderId,
				'post_status' => 'draft',
			],
			true
		);

		return ! is_wp_error( $result ) && 0 !== $result;
	}

	public function deleteLanguageRows() {
		$deleted = $this->wpdb->delete(
			$this->wpdb->prefix . 'icl_translations',
			[ 'element_type' => self::ELEMENT_TYPE ],
			[ '%s' ]
		);

		do_action( 'wpml_cache_clear' );

		return (int) $deleted;
	}

	public function resetModes() {
		$sync     = (array) $this->sitepress->get_setting( ThemeBuilderOneList::SYNC_OPTION, [] );
		$unlocked = (array) $this->sitepress->get_setting( ThemeBuilderOneList::UNLOCK_OPTION, [] );

		foreach ( ThemeBuilderOneList::POST_TYPES as $postType ) {
			$sync[ $postType ]     = ThemeBuilderOneList::MODE_NOT_TRANSLATABLE;
			$unlocked[ $postType ] = 0;
		}

		$this->sitepress->set_setting( ThemeBuilderOneList::SYNC_OPTION, $sync );
		$this->sitepress->set_setting( ThemeBuilderOneList::UNLOCK_OPTION, $unlocked, true );
	}

	public function writeLast( array $last ) {
		update_option( self::LAST_OPTION, $last, false );
	}

	public function log( $line ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'WPML Divi Theme Builder repair: ' . $line );
		}
	}
}
