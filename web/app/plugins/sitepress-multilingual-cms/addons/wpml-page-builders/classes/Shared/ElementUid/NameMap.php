<?php

namespace WPML\PB\ElementUid;

class NameMap {

	const META_KEY = '_wpml_string_uid_map';

	const FACT = 'string_uid';

	const SOURCES = [ 'block', 'node', 'shortcode' ];

	private $knowledge;

	private $folded = [];

	public function __construct( ?Knowledge $knowledge = null ) {
		$this->knowledge = null === $knowledge ? new Knowledge() : $knowledge;
	}

	public static function add( array &$map, $name, $uid ) {
		if ( ! isset( $map[ $name ] ) ) {
			$map[ $name ] = $uid;
		} elseif ( is_array( $map[ $name ] ) ) {
			if ( ! in_array( $uid, $map[ $name ], true ) ) {
				$map[ $name ][] = $uid;
			}
		} elseif ( $map[ $name ] !== $uid ) {
			$map[ $name ] = [ $map[ $name ], $uid ];
		}
	}

	public function save( $postId, array $map, $source ) {
		if ( ! $this->knowledge->isAvailable() ) {
			return;
		}

		$postId = (int) $postId;
		$stored = $this->getSections( $postId );
		$kept   = [];

		foreach ( $map as $name => $uids ) {
			$uids = self::uidList( $uids );

			if ( ! $uids ) {
				continue;
			}

			$isStored = isset( $stored[ $source ] ) && array_key_exists( $name, $stored[ $source ] );

			$kept[ $name ] = true;

			if ( ! $isStored || $stored[ $source ][ $name ] !== $uids ) {
				$this->knowledge->set( $postId, self::partOf( $source, $name ), self::FACT, wp_json_encode( $uids ) );
			}
		}

		if ( ! isset( $stored[ $source ] ) ) {
			return;
		}

		foreach ( $stored[ $source ] as $name => $uids ) {
			if ( ! isset( $kept[ $name ] ) ) {
				$this->knowledge->delete( $postId, self::partOf( $source, $name ), self::FACT );
			}
		}
	}

	public function get( $postId ) {
		$sections = $this->getSections( (int) $postId );
		$map      = [];

		foreach ( self::SOURCES as $source ) {
			if ( ! isset( $sections[ $source ] ) ) {
				continue;
			}

			foreach ( $sections[ $source ] as $name => $uids ) {
				$map[ $name ] = 1 === count( $uids ) ? $uids[0] : $uids;
			}
		}

		return $map;
	}

	private function getSections( $postId ) {
		if ( ! $this->knowledge->isAvailable() ) {
			return [];
		}

		$sections = [];

		foreach ( $this->knowledge->records( $postId, self::FACT ) as $row ) {
			if ( ! isset( $row['part'], $row['value'] ) ) {
				continue;
			}

			$address = explode( '/', (string) $row['part'], 2 );
			$uids    = json_decode( (string) $row['value'], true );
			$uids    = is_array( $uids ) ? self::uidList( $uids ) : [];

			if ( 2 === count( $address ) && $uids ) {
				$sections[ $address[0] ][ $address[1] ] = $uids;
			}
		}

		return $this->foldInLegacyMeta( $postId, $sections );
	}

	private function foldInLegacyMeta( $postId, array $sections ) {
		if ( isset( $this->folded[ $postId ] ) ) {
			return $sections;
		}

		$this->folded[ $postId ] = true;

		$stored = get_post_meta( $postId, self::META_KEY, true );

		if ( ! is_array( $stored ) || ! $stored ) {
			return $sections;
		}

		foreach ( $stored as $source => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			foreach ( $section as $name => $uids ) {
				if ( isset( $sections[ $source ] ) && array_key_exists( $name, $sections[ $source ] ) ) {
					continue;
				}

				$uids = self::uidList( $uids );

				if ( ! $uids ) {
					continue;
				}

				$sections[ $source ][ $name ] = $uids;

				$this->knowledge->set( $postId, self::partOf( $source, $name ), self::FACT, wp_json_encode( $uids ) );
			}
		}

		delete_post_meta( $postId, self::META_KEY );

		return $sections;
	}

	private static function partOf( $source, $name ) {
		return $source . '/' . $name;
	}

	private static function uidList( $uids ) {
		$list = [];

		foreach ( (array) $uids as $uid ) {
			if ( is_scalar( $uid ) && '' !== (string) $uid ) {
				$list[] = (string) $uid;
			}
		}

		return $list;
	}
}
