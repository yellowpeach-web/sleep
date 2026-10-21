<?php

namespace WPML\OperationRecord;

class RestoreSet {

	private static $running = false;

	private $markers;

	private $untrash;

	public function __construct( ?TrashMarkers $markers = null, $untrash = null ) {
		$this->markers = $markers ? $markers : new TrashMarkers();
		$this->untrash = null === $untrash ? $this->engine() : $untrash;
	}

	public static function isRunning() {
		return self::$running;
	}

	public function restore( $record_id, array $exclude = array(), $after = 0, array $defer = array() ) {
		$after = max( (int) $after, 0 );

		$result = array(
			'restored'  => 0,
			'refused'   => 0,
			'missing'   => 0,
			'remaining' => 0,
			'ids'       => array(),
			'cursor'    => $after,
			'engine'    => null !== $this->untrash,
		);

		if ( null === $this->untrash ) {
			return $result;
		}

		$ids = $this->markers->postsOf( $record_id, $after );

		if ( ! $ids ) {
			return $result;
		}

		$exclude = array_map( 'intval', $exclude );
		$defer   = array_map( 'intval', $defer );

		$inTrashAtStart = array();

		foreach ( $ids as $id ) {
			$inTrashAtStart[ (int) $id ] = 'trash' === (string) get_post_status( (int) $id );
		}

		$was          = self::$running;
		self::$running = true;

		try {
			foreach ( $ids as $id ) {
				$id = (int) $id;

				$result['cursor'] = max( $result['cursor'], $id );

				if ( in_array( $id, $exclude, true ) ) {
					$this->markers->clear( $id );
					continue;
				}

				if ( in_array( $id, $defer, true ) ) {
					continue;
				}

				if ( 'trash' !== (string) get_post_status( $id ) ) {
					if ( ! empty( $inTrashAtStart[ $id ] ) ) {
						$this->markers->clear( $id );

						++$result['restored'];
						$result['ids'][] = $id;
						continue;
					}

					++$result['missing'];
					$this->markers->clear( $id );
					continue;
				}

				if ( ! current_user_can( 'delete_post', $id ) ) {
					++$result['refused'];
					continue;
				}

				call_user_func( $this->untrash, $id );

				$this->markers->clear( $id );

				++$result['restored'];
				$result['ids'][] = $id;
			}
		} finally {
			self::$running = $was;
		}

		$result['remaining'] = $this->markers->restorableCount( $record_id, $result['cursor'] );

		return $result;
	}

	private function engine() {
		global $wpml_post_translations;

		if ( ! is_object( $wpml_post_translations ) || ! method_exists( $wpml_post_translations, 'untrash_translation' ) ) {
			return null;
		}

		return array( $wpml_post_translations, 'untrash_translation' );
	}
}
