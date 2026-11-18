<?php

namespace WPML\Media\TextScan;

class Hooks implements \IWPML_Action {

	const PRIORITY    = 20;
	const AUTO_FILTER = 'wpml_media_text_scan_auto_enqueue';

	private $candidates;
	private $gate;
	private $keys;
	private $store;
	private $outdated;
	private $queue;
	private $routes;

	private $sha1Memo = [];

	public function __construct(
		Candidates $candidates,
		MetaGate $gate,
		ContentKey $keys,
		VerdictStore $store,
		OutdatedMarker $outdated,
		ScanQueue $queue,
		RestRoutes $routes
	) {
		$this->candidates = $candidates;
		$this->gate       = $gate;
		$this->keys       = $keys;
		$this->store      = $store;
		$this->outdated   = $outdated;
		$this->queue      = $queue;
		$this->routes     = $routes;
	}

	public function add_hooks() {
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'onGenerate' ], self::PRIORITY, 3 );
		add_filter( 'wp_update_attachment_metadata', [ $this, 'onUpdate' ], self::PRIORITY, 2 );
		add_action( 'added_post_meta', [ $this, 'onAttachedFileMeta' ], self::PRIORITY, 4 );
		add_action( 'updated_post_meta', [ $this, 'onAttachedFileMeta' ], self::PRIORITY, 4 );
		add_action( ScanQueue::HOOK, [ $this->queue, 'onCron' ] );

		$this->routes->add_hooks();
	}

	public function onGenerate( $metadata, $attachmentId, $context = 'create' ) {
		if ( 'create' !== $context ) {
			$this->onUpdate( $metadata, $attachmentId );

			return $metadata;
		}

		$id = (int) $attachmentId;
		if ( ! $this->isOriginalImage( $id ) ) {
			return $metadata;
		}

		$sha1 = $this->keys->sha1( $id );
		if ( null === $sha1 ) {
			return $metadata;
		}

		$passed = is_array( $metadata ) ? $metadata : null;
		$record = $this->store->get( $id );

		if ( $sha1 === $this->recordedSha1( $record ) ) {
			if ( isset( $record['key']['src'] ) && ContentKey::SRC_NONE === $record['key']['src'] ) {
				$key = $this->keys->compute( $id, $passed );
				if ( null !== $key ) {
					$this->store->writeKey( $id, $key );
				}
			}
			$this->store->writeGate( $id, $this->gate->verdict( $id, $passed ) );
			$this->maybeAutoEnqueue( $id );

			return $metadata;
		}

		$key = $this->keys->compute( $id, $passed );
		if ( null === $key ) {
			return $metadata;
		}

		if ( $this->hasKey( $record ) ) {
			$this->applyNewBytes( $id, $record['key'], $key, $passed );

			return $metadata;
		}

		$this->store->writeKey( $id, $key );
		$this->store->writeGate( $id, $this->gate->verdict( $id, $passed ) );
		$this->maybeAutoEnqueue( $id );

		return $metadata;
	}

	public function onUpdate( $data, $attachmentId ) {
		$id = (int) $attachmentId;
		if ( ! $this->isOriginalImage( $id ) ) {
			return $data;
		}

		$sha1 = $this->memoisedSha1( $id );
		if ( null === $sha1 ) {
			return $data;
		}

		$passed = is_array( $data ) ? $data : null;
		$record = $this->store->get( $id );

		if ( ! $this->hasKey( $record ) ) {
			$key = $this->keys->compute( $id, $passed );
			if ( null !== $key ) {
				$this->store->writeKey( $id, $key );
				$this->store->writeGate( $id, $this->gate->verdict( $id, $passed ) );
			}

			return $data;
		}

		if ( $sha1 === $this->recordedSha1( $record ) ) {
			return $data;
		}

		$key = $this->keys->compute( $id, $passed );
		if ( null === $key ) {
			return $data;
		}

		$this->applyNewBytes( $id, $record['key'], $key, $passed );

		return $data;
	}

	private function applyNewBytes( $id, array $oldKey, array $key, $passed ) {
		if ( ContentKey::changed( $oldKey, $key ) ) {
			$this->store->resetForChange( $id, $key, $this->gate->verdict( $id, $passed ) );
			$this->outdated->markTranslations( $id, $key['sha1'] );
			$this->maybeAutoEnqueue( $id );
		} else {
			$this->store->writeKey( $id, $key );
		}
	}

	private function hasKey( $record ) {
		return is_array( $record ) && isset( $record['key'] ) && is_array( $record['key'] );
	}

	public function onAttachedFileMeta( $metaId, $objectId, $metaKey, $metaValue ) {
		if ( '_wp_attached_file' !== $metaKey ) {
			return;
		}

		$id = (int) $objectId;
		if ( 'attachment' !== get_post_type( $id ) || $this->candidates->isOriginal( $id ) ) {
			return;
		}

		$this->outdated->clear( $id );
	}

	private function isOriginalImage( $id ) {
		return $this->candidates->isOriginal( $id ) && $this->candidates->isImage( $id );
	}

	private function memoisedSha1( $id ) {
		$file = get_attached_file( $id );
		if ( ! is_string( $file ) || '' === $file ) {
			return null;
		}

		clearstatcache( true, $file );
		if ( ! is_file( $file ) ) {
			return null;
		}

		$memoKey = $id . '|' . filesize( $file ) . '|' . filemtime( $file );
		if ( ! array_key_exists( $memoKey, $this->sha1Memo ) ) {
			$sha1 = $this->keys->sha1( $id );
			if ( null === $sha1 ) {
				return null;
			}
			$this->sha1Memo[ $memoKey ] = $sha1;
		}

		return $this->sha1Memo[ $memoKey ];
	}

	private function recordedSha1( $record ) {
		if ( is_array( $record ) && isset( $record['key']['sha1'] ) && is_string( $record['key']['sha1'] ) ) {
			return $record['key']['sha1'];
		}

		return null;
	}

	private function maybeAutoEnqueue( $id ) {
		if ( true !== (bool) apply_filters( self::AUTO_FILTER, false ) ) {
			return;
		}

		if ( Mode::OFF === Mode::get() ) {
			return;
		}

		$this->queue->enqueueOne( $id );
	}
}
