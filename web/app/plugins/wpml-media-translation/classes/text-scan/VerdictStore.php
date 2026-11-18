<?php

namespace WPML\Media\TextScan;

class VerdictStore {

	const META       = '_wpml_media_text_scan';
	const STATE_META = '_wpml_media_scan_state';

	const STATE_QUEUED    = 'queued';
	const STATE_TRANSLATE = 'translate';
	const STATE_SKIP      = 'skip';
	const STATE_UNSURE    = 'unsure';

	const PURPOSE_SCAN   = 'scan';
	const PURPOSE_SINGLE = 'single';

	const LOCAL_MODEL = 'local';

	private $candidates;

	private $clock;

	public function __construct( Candidates $candidates, $clock = null ) {
		$this->candidates = $candidates;
		$this->clock      = $clock ?: function () {
			return microtime( true );
		};
	}

	public function get( $id ) {
		$raw = get_post_meta( (int) $id, self::META, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$record = json_decode( $raw, true );

		return is_array( $record ) ? $record : null;
	}

	public function state( $id ) {
		$state = get_post_meta( (int) $id, self::STATE_META, true );

		return is_string( $state ) && '' !== $state ? $state : null;
	}

	public function setState( $id, $state ) {
		if ( $this->candidates->isOriginal( $id ) ) {
			$this->writeState( (int) $id, $state );
		}
	}

	public function deleteState( $id ) {
		delete_post_meta( (int) $id, self::STATE_META );
	}

	public function writeKey( $id, array $key ) {
		if ( ! $this->candidates->isOriginal( $id ) ) {
			return;
		}

		$record        = $this->record( (int) $id );
		$record['key'] = $key;
		$this->write( (int) $id, $record );
	}

	public function writeGate( $id, $gateVerdict ) {
		if ( ! $this->candidates->isOriginal( $id ) ) {
			return;
		}

		$record         = $this->record( (int) $id );
		$record['gate'] = $this->gateRecord( $gateVerdict );
		$this->write( (int) $id, $record );
	}

	public function finalFromGate( $id, $gateVerdict ) {
		if ( ! $this->candidates->isOriginal( $id ) ) {
			return;
		}

		$record         = $this->record( (int) $id );
		$record['gate'] = $this->gateRecord( $gateVerdict );
		$this->write( (int) $id, $record );

		if ( MetaGate::PASS !== $gateVerdict ) {
			$this->writeState( (int) $id, self::STATE_SKIP );
		}
	}

	public function finalFromRemote( $id, array $result, $purpose ) {
		if ( ! $this->candidates->isOriginal( $id ) ) {
			return;
		}

		$record           = $this->record( (int) $id );
		$verdict          = isset( $result['verdict'] ) ? (string) $result['verdict'] : self::STATE_UNSURE;
		$record['remote'] = [
			'verdict'    => $verdict,
			'reason'     => isset( $result['reason'] ) ? (string) $result['reason'] : '',
			'confidence' => isset( $result['confidence'] ) ? (float) $result['confidence'] : 0.0,
			'model'      => isset( $result['model'] ) ? (string) $result['model'] : '',
			'purpose'    => (string) $purpose,
			'key_sha1'   => isset( $record['key']['sha1'] ) ? (string) $record['key']['sha1'] : null,
			'at'         => $this->now(),
		];
		$record['attempts'] = 0;

		$this->write( (int) $id, $record );
		$this->writeState( (int) $id, $verdict );
	}

	public function finalLocal( $id, $reason ) {
		$this->finalFromRemote(
			$id,
			[
				'verdict'    => self::STATE_UNSURE,
				'reason'     => (string) $reason,
				'confidence' => 0,
				'model'      => self::LOCAL_MODEL,
			],
			self::PURPOSE_SCAN
		);
	}

	public function resetForChange( $id, array $newKey, $gateVerdict ) {
		if ( ! $this->candidates->isOriginal( $id ) ) {
			return;
		}

		$this->deleteState( $id );

		$record               = $this->record( (int) $id );
		$record['remote']     = null;
		$record['gate']       = $this->gateRecord( $gateVerdict );
		$record['key']        = $newKey;
		$record['changed_at'] = $this->now();
		$this->write( (int) $id, $record );
	}

	public function reuseIfUnchanged( $id, array $key ) {
		if ( ! $this->candidates->isOriginal( $id ) ) {
			return false;
		}

		$record = $this->get( $id );
		if ( ! $record || empty( $record['remote'] ) || ! is_array( $record['remote'] ) ) {
			return false;
		}

		$remote  = $record['remote'];
		$sha1    = isset( $key['sha1'] ) ? $key['sha1'] : null;
		$sameSha = null !== $sha1 && isset( $remote['key_sha1'] ) && $remote['key_sha1'] === $sha1;

		if ( ! $sameSha ) {
			$oldKey = isset( $record['key'] ) && is_array( $record['key'] ) ? $record['key'] : null;
			if ( ContentKey::changed( $oldKey, $key ) ) {
				return false;
			}
		}

		$verdict = isset( $remote['verdict'] ) ? (string) $remote['verdict'] : '';
		if ( '' === $verdict ) {
			return false;
		}

		$this->writeState( (int) $id, $verdict );

		return true;
	}

	public function incrementAttempts( $id ) {
		if ( ! $this->candidates->isOriginal( $id ) ) {
			return 0;
		}

		$record             = $this->record( (int) $id );
		$record['attempts'] = (int) $record['attempts'] + 1;
		$this->write( (int) $id, $record );

		return $record['attempts'];
	}

	public function reasonFor( $id ) {
		$record = $this->get( $id );
		if ( ! $record ) {
			return null;
		}

		$remote = isset( $record['remote'] ) && is_array( $record['remote'] ) ? $record['remote'] : null;
		$gate   = isset( $record['gate'] ) && is_array( $record['gate'] ) ? $record['gate'] : null;

		if ( null === $remote && $gate && isset( $gate['verdict'] ) && self::STATE_SKIP === $gate['verdict'] ) {
			return isset( $gate['reason'] ) ? (string) $gate['reason'] : null;
		}

		return $remote && isset( $remote['reason'] ) ? (string) $remote['reason'] : null;
	}

	public function countByState() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value, COUNT(*) AS n FROM {$wpdb->postmeta} WHERE meta_key = %s GROUP BY meta_value",
				self::STATE_META
			)
		);

		$counts = [];
		foreach ( (array) $rows as $row ) {
			if ( is_object( $row ) && isset( $row->meta_value ) ) {
				$counts[ (string) $row->meta_value ] = (int) $row->n;
			}
		}

		return $counts;
	}

	private function record( $id ) {
		$empty = [
			'v'          => 1,
			'key'        => null,
			'gate'       => null,
			'remote'     => null,
			'attempts'   => 0,
			'changed_at' => null,
		];

		$stored = $this->get( $id );

		return $stored ? array_merge( $empty, $stored ) : $empty;
	}

	private function write( $id, array $record ) {
		$json = wp_json_encode( $record );
		if ( ! is_string( $json ) ) {
			return false;
		}

		return (bool) update_post_meta( $id, self::META, wp_slash( $json ) );
	}

	private function writeState( $id, $state ) {
		update_post_meta( $id, self::STATE_META, (string) $state );
	}

	private function gateRecord( $gateVerdict ) {
		return [
			'verdict' => MetaGate::PASS === $gateVerdict ? 'pass' : self::STATE_SKIP,
			'reason'  => MetaGate::reasonOf( $gateVerdict ),
			'at'      => $this->now(),
		];
	}

	private function now() {
		return (int) floor( call_user_func( $this->clock ) );
	}
}
