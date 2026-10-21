<?php

namespace WPML\PB\ElementUid;

class Registry {

	const META_KEY = '_wpml_element_uids';

	const FACT = 'element';

	private $knowledge;

	private $folded = [];

	public function __construct( ?Knowledge $knowledge = null ) {
		$this->knowledge = null === $knowledge ? new Knowledge() : $knowledge;
	}

	public function isAvailable() {
		return $this->knowledge->isAvailable();
	}

	private static $neutralRunClock = [];

	private static $builderContentChanged = [];

	public static function resetRequestState() {
		self::$neutralRunClock       = [];
		self::$builderContentChanged = [];
	}

	public static function onPrePostUpdate( $postId, $data = [] ) {
		$postId = (int) $postId;

		if ( ! empty( self::$builderContentChanged[ $postId ] ) ) {
			unset( self::$neutralRunClock[ $postId ] );

			return;
		}

		$post = get_post( $postId );

		$neutral = $post instanceof \WP_Post
			&& array_key_exists( 'post_content', $data )
			&& (string) $data['post_content'] === (string) $post->post_content;

		if ( ! $neutral ) {
			unset( self::$neutralRunClock[ $postId ] );

			return;
		}

		if ( ! isset( self::$neutralRunClock[ $postId ] ) ) {
			self::$neutralRunClock[ $postId ] = self::clockOfPost( $post );
		}
	}

	public static function onBuilderContentChanged( $postId ) {
		$postId = (int) $postId;
		unset( self::$neutralRunClock[ $postId ] );
		self::$builderContentChanged[ $postId ] = true;
	}

	public function update( $postId, array $uidHashes, $source, $time = null, array $addresses = [] ) {
		if ( ! $this->knowledge->isAvailable() ) {
			return;
		}

		$postId  = (int) $postId;
		$now     = $uidHashes ? ( null === $time ? $this->getPostClock( $postId ) : (int) $time ) : 0;
		$stored  = $this->getEntries( $postId );
		$current = [];

		foreach ( $uidHashes as $uid => $hash ) {
			$known = isset( $stored[ $uid ]['hash'], $stored[ $uid ]['source'] ) && $stored[ $uid ]['source'] === $source;

			if ( ! $known ) {
				$entry = [
					'created'  => $now,
					'modified' => $now,
					'hash'     => $hash,
					'source'   => $source,
				];
			} elseif ( $stored[ $uid ]['hash'] !== $hash ) {
				$entry = [
					'created'  => isset( $stored[ $uid ]['created'] ) ? $stored[ $uid ]['created'] : $now,
					'modified' => $now,
					'hash'     => $hash,
					'source'   => $source,
				];
			} else {
				$entry           = $stored[ $uid ];
				$entry['source'] = $source;
			}

			if ( isset( $addresses[ $uid ] ) ) {
				$entry['address'] = (string) $addresses[ $uid ];
			}

			$current[ $uid ] = self::normalize( $entry );
		}

		foreach ( $current as $uid => $entry ) {
			if ( ! isset( $stored[ $uid ] ) || $stored[ $uid ] !== $entry ) {
				$this->knowledge->set( $postId, (string) $uid, self::FACT, wp_json_encode( $entry ) );
			}
		}

		foreach ( $stored as $uid => $entry ) {
			if ( isset( $entry['source'] ) && $entry['source'] === $source && ! isset( $current[ $uid ] ) ) {
				$this->knowledge->delete( $postId, (string) $uid, self::FACT );
			}
		}
	}

	public function rename( $postId, array $renames ) {
		if ( ! $renames || ! $this->knowledge->isAvailable() ) {
			return;
		}

		$postId = (int) $postId;
		$stored = $this->getEntries( $postId );

		foreach ( $renames as $from => $to ) {
			$from = (string) $from;
			$to   = (string) $to;

			if ( $from === $to || ! isset( $stored[ $from ] ) || isset( $stored[ $to ] ) ) {
				continue;
			}

			$this->knowledge->set( $postId, $to, self::FACT, wp_json_encode( $stored[ $from ] ) );
			$this->knowledge->delete( $postId, $from, self::FACT );

			$stored[ $to ] = $stored[ $from ];
			unset( $stored[ $from ] );
		}
	}

	public function getEntries( $postId, $source = null ) {
		if ( ! $this->knowledge->isAvailable() ) {
			return [];
		}

		$postId  = (int) $postId;
		$entries = [];

		foreach ( $this->knowledge->records( $postId, self::FACT ) as $row ) {
			if ( ! isset( $row['part'], $row['value'] ) || '' === (string) $row['part'] ) {
				continue;
			}

			$entry = json_decode( (string) $row['value'], true );

			if ( is_array( $entry ) ) {
				$entries[ (string) $row['part'] ] = self::normalize( $entry );
			}
		}

		$entries = $this->foldInLegacyMeta( $postId, $entries );

		if ( null === $source ) {
			return $entries;
		}

		$ofSource = [];

		foreach ( $entries as $uid => $entry ) {
			if ( isset( $entry['source'] ) && $entry['source'] === $source ) {
				$ofSource[ $uid ] = $entry;
			}
		}

		return $ofSource;
	}

	public function getHashes( $postId, $source = null ) {
		$hashes = [];

		foreach ( $this->getEntries( $postId, $source ) as $uid => $entry ) {
			if ( isset( $entry['hash'] ) ) {
				$hashes[ $uid ] = $entry['hash'];
			}
		}

		return $hashes;
	}

	public function getTimestamps( $postId ) {
		$timestamps = [];

		foreach ( $this->getEntries( $postId ) as $uid => $entry ) {
			if ( isset( $entry['created'], $entry['modified'] ) ) {
				$timestamps[ $uid ] = [
					'created'  => (int) $entry['created'],
					'modified' => (int) $entry['modified'],
				];
			}
		}

		return $timestamps;
	}

	private function foldInLegacyMeta( $postId, array $entries ) {
		if ( isset( $this->folded[ $postId ] ) ) {
			return $entries;
		}

		$this->folded[ $postId ] = true;

		$stored = get_post_meta( $postId, self::META_KEY, true );

		if ( ! is_array( $stored ) || ! $stored ) {
			return $entries;
		}

		foreach ( $stored as $uid => $entry ) {
			if ( is_array( $entry ) && isset( $entry['hash'] ) && ! isset( $entries[ $uid ] ) ) {
				$entry           = self::normalize( $entry );
				$entries[ $uid ] = $entry;

				$this->knowledge->set( $postId, (string) $uid, self::FACT, wp_json_encode( $entry ) );
			}
		}

		delete_post_meta( $postId, self::META_KEY );

		return $entries;
	}

	private static function normalize( array $entry ) {
		$normalized = [];

		foreach ( [ 'created', 'modified', 'hash', 'source', 'address' ] as $key ) {
			if ( isset( $entry[ $key ] ) ) {
				$normalized[ $key ] = $entry[ $key ];
			}
		}

		return $normalized;
	}

	private function getPostClock( $postId ) {
		if ( isset( self::$neutralRunClock[ $postId ] ) ) {
			return min( self::$neutralRunClock[ $postId ], self::postClockOf( $postId ) );
		}

		return self::postClockOf( $postId );
	}

	private static function postClockOf( $postId ) {
		return self::clockOfPost( get_post( $postId ) );
	}

	private static function clockOfPost( $post ) {
		if ( $post instanceof \WP_Post
			&& ! empty( $post->post_modified_gmt )
			&& '0000-00-00 00:00:00' !== $post->post_modified_gmt
		) {
			$timestamp = strtotime( $post->post_modified_gmt . ' UTC' );

			if ( $timestamp > 0 ) {
				return $timestamp;
			}
		}

		return time();
	}
}
