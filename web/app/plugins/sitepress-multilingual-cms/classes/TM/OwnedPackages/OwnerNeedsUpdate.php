<?php

namespace WPML\TM\OwnedPackages;

use WPML\Setup\Option;

class OwnerNeedsUpdate {
	private $sitepress;

	private $wpdb;

	private $updateStatus;

	private $isTranslateEverything;

	private $owners = [];

	private $savedOwners = [];

	private $flaggedTranslations = [];

	private $jobTranslations = [];

	private $flushHooked = false;

	public function __construct( \SitePress $sitepress, \wpdb $wpdb, ?callable $updateStatus = null, ?callable $isTranslateEverything = null ) {
		$this->sitepress            = $sitepress;
		$this->wpdb                 = $wpdb;
		$this->updateStatus         = $updateStatus;
		$this->isTranslateEverything = $isTranslateEverything;
	}

	public static function supported() {
		return true;
	}

	public static function taxonomyHumanMethodsSupported() {
		return true;
	}

	public function add_hooks() {
		add_filter( 'wpml_tm_owned_package_jobs_supported', [ __CLASS__, 'supported' ] );
		add_action( 'wpml_string_package_owner_changed', [ $this, 'queueOwner' ], 10, 3 );
		add_action( 'wpml_updated_translation_status', [ $this, 'recordStatusUpdate' ] );
		add_action( 'edited_term', [ $this, 'markTermSaved' ], 1, 2 );
	}

	public function queueOwner( $elementType, $elementId, $packageId = 0 ) {
		if ( 0 === strpos( (string) $elementType, 'post_' ) ) {
			$this->owners[ 'post:' . (int) $elementId ] = [ 'post', (int) $elementId ];
		} elseif ( 0 === strpos( (string) $elementType, 'tax_' ) ) {
			$this->owners[ 'term:' . (int) $elementId ] = [ 'term', (int) $elementId ];
		}
		$this->hookFlush();
	}

	public function recordStatusUpdate( array $data ) {
		if ( empty( $data['translation_id'] ) ) {
			return;
		}
		$translationId = (int) $data['translation_id'];
		if ( ! empty( $data['needs_update'] ) ) {
			$this->flaggedTranslations[ $translationId ] = true;
		}
		if ( ! empty( $data['rid'] ) && isset( $data['status'] ) ) {
			$this->jobTranslations[ $translationId ] = true;
		}
	}

	public function markTermSaved( $termId, $ttId ) {
		$this->savedOwners[ 'term:' . (int) $ttId ] = true;
	}

	public function flush() {
		$owners            = $this->owners;
		$this->owners      = [];
		$this->flushHooked = false;

		foreach ( $owners as $key => $owner ) {
			if ( empty( $this->savedOwners[ $key ] ) ) {
				$this->flagOwner( $owner[0], $owner[1] );
			}
		}
	}

	public function flagOwner( $kind, $elementId ) {
		$elementId = (int) $elementId;
		if ( 'term' === $kind ) {
			$term = get_term_by( 'term_taxonomy_id', $elementId );
			if ( ! $term || $term instanceof \WP_Error ) {
				return false;
			}
			do_action( 'wpml_taxonomy_term_content_changed', (int) $term->term_id );
			return true;
		}

		$post = 'post' === $kind ? get_post( $elementId ) : null;
		if ( ! $post ) {
			return false;
		}

		$elementType  = 'post_' . $post->post_type;
		$trid         = apply_filters( 'wpml_element_trid', null, $elementId, $elementType );
		$translations = $trid ? (array) apply_filters( 'wpml_get_element_translations', null, $trid, $elementType ) : [];
		$md5          = null;

		foreach ( $translations as $translation ) {
			if ( ! empty( $translation->original ) || empty( $translation->translation_id ) ) {
				continue;
			}
			if ( null === $md5 ) {
				$md5 = apply_filters( 'wpml_tm_element_md5', $elementId );
			}
			$this->flagPostTranslation( (int) $translation->translation_id, (string) $md5, $translation );
		}

		return true;
	}

	private function flagPostTranslation( $translationId, $md5, $translation ) {
		if ( ! empty( $this->flaggedTranslations[ $translationId ] ) || ! empty( $this->jobTranslations[ $translationId ] ) ) {
			return;
		}

		$wpdb = $this->wpdb;
		$rid  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = %d",
				$translationId
			)
		);
		if ( $this->shouldTranslateEverything() && ! $rid ) {
			return;
		}

		$data = [
			'translation_id' => $translationId,
			'needs_update'   => 1,
			'md5'            => $md5,
		];
		if ( ! $rid && ! empty( $translation->element_id ) ) {
			$translatedPost = get_post( (int) $translation->element_id );
			if ( $translatedPost && 'publish' === $translatedPost->post_status ) {
				$data['status'] = ICL_TM_COMPLETE;
			}
		}

		$result = call_user_func( $this->updateStatus(), $data, $rid ?: null );
		if ( ! is_array( $result ) || empty( $result[0] ) ) {
			\WPML\TM\Jobs\JobLog::add( 'owned_package_status_write_failed', [ 'translation_id' => $translationId ] );
		}
	}

	private function updateStatus() {
		if ( null === $this->updateStatus ) {
			$this->updateStatus = function ( array $data, $rid = null ) {
				return wpml_load_core_tm()->update_translation_status( $data, $rid );
			};
		}
		return $this->updateStatus;
	}

	private function shouldTranslateEverything() {
		if ( null === $this->isTranslateEverything ) {
			$this->isTranslateEverything = function () {
				return Option::shouldTranslateEverything();
			};
		}
		return (bool) call_user_func( $this->isTranslateEverything );
	}

	private function hookFlush() {
		if ( ! $this->flushHooked ) {
			$this->flushHooked = true;
			add_action( 'shutdown', [ $this, 'flush' ], 20 );
		}
	}
}
