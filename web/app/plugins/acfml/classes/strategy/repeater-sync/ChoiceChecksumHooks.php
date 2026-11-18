<?php

namespace ACFML\Repeater\Sync;

use ACFML\Repeater\Shuffle\Post;

class ChoiceChecksumHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	private $wpdb;

	private $status;

	private $checksum;

	private $matches;

	private $level = [];

	public function __construct( $wpdb = null, ?TranslationStatus $status = null, ?callable $checksum = null, ?callable $matches = null ) {
		if ( null === $wpdb ) {
			global $wpdb;
		}
		$this->wpdb     = $wpdb;
		$this->status   = $status;
		$this->checksum = $checksum ?: function ( $postId ) {
			return ( new \WPML_TM_Action_Helper() )->post_md5( $postId );
		};
		$this->matches  = $matches;
	}

	public function add_hooks() {
		if ( ! class_exists( \WPML_TM_Action_Helper::class ) ) {
			return;
		}

		add_filter( 'pre_update_option_' . CheckboxOption::SYNCHRONISE_WP_OPTION_NAME, [ $this, 'noteLevelTranslations' ], 10, 2 );
		add_action( 'add_option_' . CheckboxOption::SYNCHRONISE_WP_OPTION_NAME, [ $this, 'writeChecksumsInTheNewForm' ], 20 );
		add_action( 'update_option_' . CheckboxOption::SYNCHRONISE_WP_OPTION_NAME, [ $this, 'writeChecksumsInTheNewForm' ], 20 );
	}

	public function noteLevelTranslations( $value, $oldValue = [] ) {
		$this->level = [];
		$matches     = null;

		foreach ( self::changedTrids( $value, $oldValue ) as $trid ) {
			$original = $this->originalPostOf( $trid );
			if ( ! $original ) {
				continue;
			}

			foreach ( $this->checksummedTranslationsOf( $trid ) as $translationId => $storedMd5 ) {
				if ( ! $this->status()->isLevelWithOriginal( (object) [ 'translation_id' => $translationId ] ) ) {
					continue;
				}
				$matches = $matches ?: $this->matcher();
				if ( $matches( $original, $storedMd5 ) ) {
					$this->level[ $original ][] = $translationId;
				}
			}
		}

		return $value;
	}

	public function writeChecksumsInTheNewForm() {
		$level       = $this->level;
		$this->level = [];

		foreach ( $level as $original => $translationIds ) {
			$md5 = (string) call_user_func( $this->checksum, $original );
			if ( '' === $md5 ) {
				continue;
			}

			foreach ( $translationIds as $translationId ) {
				$this->wpdb->update(
					$this->wpdb->prefix . 'icl_translation_status',
					[ 'md5' => $md5 ],
					[ 'translation_id' => $translationId ]
				);
			}
		}
	}

	private static function changedTrids( $value, $oldValue ) {
		$value    = is_array( $value ) ? $value : [];
		$oldValue = is_array( $oldValue ) ? $oldValue : [];
		$changed  = [];

		foreach ( array_keys( $value + $oldValue ) as $trid ) {
			$on    = true === ( $value[ $trid ] ?? false );
			$wasOn = true === ( $oldValue[ $trid ] ?? false );
			if ( $on !== $wasOn && (int) $trid > 0 ) {
				$changed[] = (int) $trid;
			}
		}

		return $changed;
	}

	private function originalPostOf( $trid ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}icl_translations
				WHERE trid = %d AND source_language_code IS NULL AND element_type LIKE %s
				LIMIT 1",
				$trid,
				'post\_%'
			)
		);
	}

	private function checksummedTranslationsOf( $trid ) {
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.translation_id, s.md5 FROM {$wpdb->prefix}icl_translations t
				INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.translation_id = t.translation_id
				WHERE t.trid = %d AND t.source_language_code IS NOT NULL AND s.md5 <> ''",
				$trid
			)
		);

		$md5s = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$md5s[ (int) $row->translation_id ] = (string) $row->md5;
		}

		return $md5s;
	}

	private function matcher() {
		if ( $this->matches ) {
			return $this->matches;
		}

		$helper  = new \WPML_TM_Action_Helper();
		$current = [];

		return function ( $postId, $md5 ) use ( $helper, &$current ) {
			if ( ! isset( $current[ $postId ] ) ) {
				$current[ $postId ] = $helper->post_md5( $postId );
			}

			return $helper->post_md5_matches( $postId, $md5, $current[ $postId ] );
		};
	}

	private function status() {
		if ( null === $this->status ) {
			$this->status = TranslationStatusFactory::fromGlobals()->create( new Post() );
		}

		return $this->status;
	}
}
