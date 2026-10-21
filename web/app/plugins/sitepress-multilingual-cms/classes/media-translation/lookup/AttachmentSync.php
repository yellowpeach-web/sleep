<?php

namespace WPML\Media\Lookup;

class AttachmentSync {

	private $wpdb;

	private $table;

	private $hasher;

	public function __construct( \wpdb $wpdb, MediaLookupTable $table, MediaLookupHasher $hasher ) {
		$this->wpdb   = $wpdb;
		$this->table  = $table;
		$this->hasher = $hasher;
	}

	private function languagesFor( $attachment_id ) {
		$wpdb = $this->wpdb;

		return (array) $wpdb->get_col( $wpdb->prepare(
			"SELECT language_code FROM {$wpdb->prefix}icl_translations
			WHERE element_type = 'post_attachment' AND element_id = %d",
			$attachment_id
		) );
	}

	public function syncAttachment( $attachment_id ) {
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return;
		}

		$languages = $this->languagesFor( $post->ID );
		if ( ! $languages ) {
			return;
		}

		$attached_file = (string) get_post_meta( $post->ID, '_wp_attached_file', true );

		foreach ( $languages as $language ) {
			$rows = [
				[
					'hash'          => $this->hasher->hash( $language, $post->guid ),
					'attachment_id' => $post->ID,
					'variant'       => MediaLookupTable::VARIANT_GUID,
					'expires_at'    => 0,
				],
			];

			if ( '' !== $attached_file ) {
				$rows[] = [
					'hash'          => $this->hasher->hash( $language, $attached_file ),
					'attachment_id' => $post->ID,
					'variant'       => MediaLookupTable::VARIANT_ATTACHED_FILE,
					'expires_at'    => 0,
				];
			}

			$this->table->upsertBatch( $rows, $language );
		}
	}

	public function onAttachmentUpdated( $attachment_id, $post_before ) {
		if ( $post_before && $post_before->guid ) {
			$this->deleteValueRows( $attachment_id, $post_before->guid );
		}

		$this->syncAttachment( $attachment_id );
	}

	public function onDeleteAttachment( $attachment_id ) {
		$post = get_post( $attachment_id );
		if ( ! $post ) {
			return;
		}

		$this->deleteValueRows( $attachment_id, $post->guid );

		$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( '' !== $attached_file ) {
			$this->deleteValueRows( $attachment_id, $attached_file );
		}
	}

	public function onAttachedFileChanged( $attachment_id, $meta_value ) {
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type || '' === (string) $meta_value ) {
			return;
		}

		foreach ( $this->languagesFor( $attachment_id ) as $language ) {
			$this->table->upsertBatch(
				[
					[
						'hash'          => $this->hasher->hash( $language, (string) $meta_value ),
						'attachment_id' => $attachment_id,
						'variant'       => MediaLookupTable::VARIANT_ATTACHED_FILE,
						'expires_at'    => 0,
					],
				],
				$language
			);
		}
	}

	private function deleteValueRows( $attachment_id, $value ) {
		foreach ( $this->languagesFor( $attachment_id ) as $language ) {
			$this->table->deleteBatch( [ $this->hasher->hash( $language, $value ) ], $language );
		}
	}
}
