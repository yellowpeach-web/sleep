<?php

namespace WPML\TranslationPolicy\Migration;

use WPML\Core\SharedKernel\Component\Post\Domain\TranslationEditorPreference as Keys;
use WPML\Setup\Option;
use WPML\TranslationPolicy\Policy\PolicyTable;

class LegacyEditorStores {

	const TM_KEYS = [
		Keys::TM_KEY_GLOBAL_EDITOR            => null,
		Keys::TM_KEY_FOR_POST_TYPE_EDITOR     => [],
		Keys::TM_KEY_GLOBAL_USE_NATIVE        => null,
		Keys::TM_KEY_FOR_POST_TYPE_USE_NATIVE => [],
		Keys::TM_KEY_GLOBAL_USE_WPML          => null,
		Keys::TM_KEY_FOR_POST_TYPE_USE_WPML   => [],
		'doc_translation_method'              => '',
	];

	private $wpdb;

	private $sitepress;

	public function __construct( \wpdb $wpdb, \SitePress $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function tmSettings(): array {
		$settings = [];
		foreach ( self::TM_KEYS as $key => $default ) {
			$settings[ $key ] = wpml_get_tm_sub_setting( $key, $default );
		}

		return $settings;
	}

	public function sinceDates(): array {
		return Option::getTranslateEverythingPostsSinceDates();
	}

	public function isTranslateEverythingOn(): bool {
		return (bool) Option::shouldTranslateEverything();
	}

	public function translatableTypes(): array {
		$modes = $this->sitepress->get_setting( 'custom_posts_sync_option', [] );
		$modes = is_array( $modes ) ? $modes : [];

		$translationModes = new \WPML_Translation_Modes();
		$types            = [];
		foreach ( $modes as $type => $mode ) {
			if ( $translationModes->is_translatable_mode( $mode ) ) {
				$types[] = (string) $type;
			}
		}

		$types = array_merge( $types, array_map( 'strval', array_keys( $this->sinceDates() ) ) );
		sort( $types );

		return array_values( array_unique( $types ) );
	}

	public function wpmlEditor(): string {
		return PolicyTable::fallbackEditor();
	}

	public function postKeys( int $afterId, int $limit ): array {
		$wpdb = $this->wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT `post_id` FROM {$wpdb->postmeta}
				WHERE `meta_key` IN ( %s, %s, %s ) AND `post_id` > %d
				ORDER BY `post_id`
				LIMIT %d",
				Keys::POST_META_KEY_EDITOR,
				Keys::POST_META_KEY_USE_NATIVE,
				Keys::POST_META_KEY_USE_WPML,
				$afterId,
				$limit
			)
		);
		if ( ! $ids ) {
			return [ 0, [] ];
		}

		$lastId = (int) end( $ids );
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.`post_id`, p.`post_type`, pm.`meta_key`, pm.`meta_value`
				FROM {$wpdb->postmeta} pm
				JOIN {$wpdb->posts} p ON p.`ID` = pm.`post_id`
				LEFT JOIN {$wpdb->prefix}icl_translations t
					ON t.`element_id` = p.`ID` AND t.`element_type` = CONCAT( 'post_', p.`post_type` )
				WHERE pm.`meta_key` IN ( %s, %s, %s )
					AND pm.`post_id` > %d AND pm.`post_id` <= %d
					AND t.`source_language_code` IS NULL
				ORDER BY pm.`meta_id`",
				Keys::POST_META_KEY_EDITOR,
				Keys::POST_META_KEY_USE_NATIVE,
				Keys::POST_META_KEY_USE_WPML,
				$afterId,
				$lastId
			),
			ARRAY_A
		);

		$columns = [
			Keys::POST_META_KEY_EDITOR     => 'editor',
			Keys::POST_META_KEY_USE_NATIVE => 'native',
			Keys::POST_META_KEY_USE_WPML   => 'wpml',
		];

		$posts = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$postId = (int) $row['post_id'];
			$column = $columns[ $row['meta_key'] ] ?? null;
			if ( ! $column ) {
				continue;
			}
			$posts[ $postId ]         = $posts[ $postId ] ?? [ 'type' => (string) $row['post_type'] ];
			$posts[ $postId ][ $column ] = $posts[ $postId ][ $column ] ?? (string) $row['meta_value'];
		}

		return [ $lastId, $posts ];
	}
}
