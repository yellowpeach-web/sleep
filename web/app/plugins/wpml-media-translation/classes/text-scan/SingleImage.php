<?php

namespace WPML\Media\TextScan;

class SingleImage {

	const TIMEOUT = 15;

	private $candidates;
	private $gate;
	private $keys;
	private $store;
	private $thumbnail;
	private $client;

	public function __construct(
		Candidates $candidates,
		MetaGate $gate,
		ContentKey $keys,
		VerdictStore $store,
		Thumbnail $thumbnail,
		ClassifierClient $client
	) {
		$this->candidates = $candidates;
		$this->gate       = $gate;
		$this->keys       = $keys;
		$this->store      = $store;
		$this->thumbnail  = $thumbnail;
		$this->client     = $client;
	}

	public function classify( $id ) {
		$id = (int) $id;

		if ( ! $this->candidates->isOriginal( $id ) ) {
			return $this->error(
				'wpml_text_scan_not_original',
				__( 'Only images in their original language can be checked for text.', 'wpml-media' ),
				422
			);
		}

		if ( ! $this->candidates->isImage( $id ) ) {
			return $this->formatError();
		}

		$gateVerdict = $this->gate->verdict( $id );
		if ( MetaGate::SKIP_FORMAT === $gateVerdict ) {
			return $this->formatError();
		}

		$key = $this->keys->compute( $id );
		if ( null === $key ) {
			return $this->unreadableError();
		}

		$record = $this->store->get( $id );
		$oldKey = is_array( $record ) && isset( $record['key'] ) && is_array( $record['key'] ) ? $record['key'] : null;
		if ( ContentKey::changed( $oldKey, $key ) ) {
			$this->store->resetForChange( $id, $key, $gateVerdict );
		} else {
			$this->store->writeKey( $id, $key );
		}

		$item = $this->thumbnail->classifierItem( $id );
		if ( null === $item ) {
			return $this->unreadableError();
		}

		try {
			$raw = $this->client->classify( [ $item ], VerdictStore::PURPOSE_SINGLE, self::TIMEOUT );
		} catch ( ClassifierUnavailable $e ) {
			if ( ClassifierUnavailable::BLOCKED === $e->reason() ) {
				return $this->unavailableError(
					[
						'reason'       => ClassifierUnavailable::BLOCKED,
						'blocked_host' => $e->host(),
					]
				);
			}

			return $this->unavailableError();
		}

		$valid = HttpClassifierClient::validResults( $raw, [ $id ] );
		if ( ! isset( $valid[ $id ] ) ) {
			return $this->unavailableError();
		}

		$this->store->finalFromRemote( $id, $valid[ $id ], VerdictStore::PURPOSE_SINGLE );

		return [
			'id'         => $id,
			'verdict'    => $valid[ $id ]['verdict'],
			'reason'     => $valid[ $id ]['reason'],
			'confidence' => $valid[ $id ]['confidence'],
			'model'      => $valid[ $id ]['model'],
		];
	}

	private function formatError() {
		return $this->error(
			'wpml_text_scan_format',
			__( 'This file type cannot be checked for text.', 'wpml-media' ),
			422
		);
	}

	private function unreadableError() {
		return $this->error(
			'wpml_text_scan_unreadable',
			__( 'The image file could not be read.', 'wpml-media' ),
			422
		);
	}

	private function unavailableError( array $data = [] ) {
		return new \WP_Error(
			'wpml_text_scan_unavailable',
			__( 'The image check is not available right now. Please try again later.', 'wpml-media' ),
			[ 'status' => 503 ] + $data
		);
	}

	private function error( $code, $message, $status ) {
		return new \WP_Error( $code, $message, [ 'status' => $status ] );
	}
}
