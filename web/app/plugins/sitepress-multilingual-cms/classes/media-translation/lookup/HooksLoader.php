<?php

namespace WPML\Media\Lookup;

class HooksLoader implements \IWPML_Action {

	const SYNC_PRIORITY = 999;

	private $sync;

	public function __construct( AttachmentSync $sync ) {
		$this->sync = $sync;
	}

	public function add_hooks() {
		add_filter( 'pre_attachment_url_to_postid', [ $this, 'preUrlToPostId' ], 10, 2 );

		add_action( 'add_attachment', [ $this, 'onAddAttachment' ], self::SYNC_PRIORITY );
		add_action( 'attachment_updated', [ $this, 'onAttachmentUpdated' ], self::SYNC_PRIORITY, 3 );
		add_action( 'delete_attachment', [ $this, 'onDeleteAttachment' ], 10 );
		add_action( 'added_post_meta', [ $this, 'onPostMetaChanged' ], self::SYNC_PRIORITY, 4 );
		add_action( 'updated_post_meta', [ $this, 'onPostMetaChanged' ], self::SYNC_PRIORITY, 4 );
	}

	public function onAddAttachment( $attachment_id ) {
		$this->sync->syncAttachment( $attachment_id );
	}

	public function onAttachmentUpdated( $attachment_id, $post_after, $post_before ) {
		$this->sync->onAttachmentUpdated( $attachment_id, $post_before );
	}

	public function onDeleteAttachment( $attachment_id ) {
		$this->sync->onDeleteAttachment( $attachment_id );
	}

	public function onPostMetaChanged( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( '_wp_attached_file' === $meta_key ) {
			$this->sync->onAttachedFileChanged( (int) $post_id, (string) $meta_value );
		}
	}

	public function preUrlToPostId( $pre, $url ) {
		if ( null !== $pre ) {
			return $pre;
		}

		$service = MediaLookupServiceFactory::service();
		if ( ! $service || ! $service->isAvailable() ) {
			return $pre;
		}

		$uploads = wp_get_upload_dir();
		$path    = $url;
		if ( 0 === strpos( $path, $uploads['baseurl'] . '/' ) ) {
			$path = substr( $path, strlen( $uploads['baseurl'] . '/' ) );
		}

		$languages = array_keys( (array) apply_filters( 'wpml_active_languages', [] ) );
		if ( ! $languages ) {
			return $pre;
		}

		$current = (string) apply_filters( 'wpml_current_language', null );
		if ( in_array( $current, $languages, true ) ) {
			$languages = array_values( array_unique( array_merge( [ $current ], $languages ) ) );
		}

		$queries = [];
		foreach ( $languages as $language ) {
			$queries[] = [
				'language' => $language,
				'value'    => $path,
				'variant'  => MediaLookupTable::VARIANT_ATTACHED_FILE,
			];
		}

		foreach ( $service->decideMany( $queries ) as $decision ) {
			if ( MediaLookupService::STATUS_FOUND === $decision['status'] ) {
				return $decision['id'];
			}
		}

		return $pre;
	}
}
