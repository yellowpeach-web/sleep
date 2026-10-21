<?php

namespace WPML\Core\PostTranslation;

use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\FP\Lst;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Review\ReviewStatus;

class SyncTranslationDocumentStatus implements \IWPML_Action, \IWPML_DIC_Action, \IWPML_Backend_Action, \IWPML_REST_Action {
	const SETTING = 'translated_document_status_sync';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'transition_post_status', [$this, 'onPostStatusChange'], 10, 3 );
	}

	public static function isOn() {
		global $sitepress;

		return $sitepress instanceof \SitePress && self::isOnFor( $sitepress );
	}

	private static function isOnFor( \SitePress $sitepress ) {
		return (bool) $sitepress->get_setting( self::SETTING );
	}

	public function onPostStatusChange( $newStatus, $oldStatus, $post ) {
		if ( ! StatusSyncRule::isSyncedChange( $oldStatus, $newStatus ) ) {
			return;
		}

		if ( ! self::isOnFor( $this->sitepress ) ) {
			return;
		}

		$elementType = 'post_' . $post->post_type;
		$trid        = $this->sitepress->get_element_trid( $post->ID, $elementType );
		if ( ! $trid ) {
			return;
		}

		$group    = (array) $this->sitepress->get_element_translations( $trid, $elementType, false, true, true );
		$isSaved  = function ( $row ) use ( $post ) {
			return (int) Obj::prop( 'element_id', $row ) === (int) $post->ID;
		};
		$savedRow = Lst::nth( 0, array_values( Fns::filter( $isSaved, $group ) ) );

		if ( ! $savedRow || ! Obj::prop( 'original', $savedRow ) ) {
			return;
		}

		foreach ( Fns::reject( $isSaved, $group ) as $data ) {
			$translation = $data->element_id ? get_post( (int) $data->element_id ) : null;
			if ( ! $translation ) {
				continue;
			}

			$status = StatusSyncRule::target(
				$oldStatus,
				$newStatus,
				$translation->post_status,
				(bool) get_post_meta( $translation->ID, '_icl_lang_duplicate_of', true ),
				StatusSyncRule::goesLive( $newStatus ) && $this->needsReview( $translation, $data->language_code ),
				$translation->post_date_gmt !== $post->post_date_gmt
			);

			if ( $status ) {
				$this->applyStatus( $translation, $status, $post );
			}
		}
	}

	private function needsReview( \WP_Post $translation, $langCode ) {
		return (bool) ReviewStatus::doesJobNeedReview(
			Jobs::getPostJob( $translation->ID, $translation->post_type, $langCode )
		);
	}

	private function applyStatus( \WP_Post $translation, $status, \WP_Post $original ) {
		global $wpdb;

		$data = [
			'post_status'       => $status,
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', true ),
		];

		if ( 'future' === $status ) {
			$data['post_date']     = $original->post_date;
			$data['post_date_gmt'] = $original->post_date_gmt;
		} elseif ( in_array( $status, [ 'publish', 'private' ], true ) && '0000-00-00 00:00:00' === $translation->post_date_gmt ) {
			$data['post_date']     = $data['post_modified'];
			$data['post_date_gmt'] = $data['post_modified_gmt'];
		}

		if ( '' === $translation->post_name && ! in_array( $status, [ 'draft', 'pending' ], true ) ) {
			$data['post_name'] = wp_unique_post_slug(
				sanitize_title( $translation->post_title, (string) $translation->ID ),
				$translation->ID,
				$status,
				$translation->post_type,
				$translation->post_parent
			);
		}

		$wpdb->update( $wpdb->posts, $data, [ 'ID' => $translation->ID ] );
		clean_post_cache( $translation->ID );

		if ( 'future' === $translation->post_status || 'future' === $status ) {
			wp_clear_scheduled_hook( 'publish_future_post', [ $translation->ID ] );
		}

		$updated = get_post( $translation->ID );
		if ( $updated ) {
			wp_transition_post_status( $status, $translation->post_status, $updated );
		}
	}
}
