<?php

namespace WPML\ContentDeletion\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\ContentDeletion\RestoreAnswer;
use WPML\ContentDeletion\Settings;
use WPML\FP\Either;
use WPML\OperationRecord\TrashMarkers;

class PreflightRestore implements IHandler {

	const INTENT_RESTORE = 'restore';

	public function run( Collection $data ) {
		$id = (int) $data->get( 'id', 0 );

		if ( $id <= 0 ) {
			return Either::left( array( 'error' => 'invalid_id' ) );
		}

		if ( ! current_user_can( 'delete_post', $id ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		$post_type = get_post_type( $id );

		if ( ! $post_type ) {
			return Either::left( array( 'error' => 'not_found' ) );
		}

		if ( 'trash' !== (string) get_post_status( $id ) ) {
			return Either::left( array( 'error' => 'not_trashed' ) );
		}

		$markers = new TrashMarkers();
		$record  = (int) $markers->recordOf( $id );
		$walked  = $record > 0 ? array_map( 'intval', $markers->postsOf( $record ) ) : array();
		$others  = $this->restorableSiblings( $walked, $id );
		$asks    = Settings::ASK === ( new Settings() )->originalAction( (string) $post_type );

		return Either::right(
			array(
				'id'         => $id,
				'record'     => $record,
				'intent'     => self::INTENT_RESTORE,
				'postType'   => (string) $post_type,
				'isOriginal' => false,
				'setSize'    => $this->setSize( $markers, $record, $walked, $others ),
				'languages'  => array(),
				'others'     => $others,
				'restoreLimit' => TrashMarkers::POSTS_LIMIT,
				'originalId'        => null,
				'canDeleteOriginal' => false,
				'originalEditUrl'   => null,
				'originalTrashUrl'  => null,
				'originalDeleteUrl' => null,
				'selfTrashUrl'      => null,
				'selfDeleteUrl'     => null,
				'promoteCandidate'  => null,
				'promoteByOrder'    => false,
				'inProgressJobs'    => array(
					'count'        => 0,
					'inProgress'   => 0,
					'chargedWords' => 0,
				),
				'hasStatuses'       => false,
				'isDuplicate'       => false,
				'remembered'        => array(
					'original'    => '',
					'translation' => '',
				),
				'askNeeded'         => (bool) $others && $asks,
				'scopeField'        => RestoreAnswer::FIELD_SCOPE,
				'nonceField'        => RestoreAnswer::FIELD_NONCE,
				'promoteField'      => '',
				'scopeNonce'        => wp_create_nonce( RestoreAnswer::NONCE_ACTION ),
			)
		);
	}

	private function restorableSiblings( array $walked, $id ) {
		$others = array();

		foreach ( $walked as $member ) {
			$member = (int) $member;

			if ( $member === (int) $id ) {
				continue;
			}

			if ( 'trash' !== (string) get_post_status( $member ) ) {
				continue;
			}

			if ( ! current_user_can( 'delete_post', $member ) ) {
				continue;
			}

			$others[] = $member;
		}

		return $others;
	}

	private function setSize( TrashMarkers $markers, $record, array $walked, array $others ) {
		$deliverable = count( $others ) + 1;

		if ( $record < 1 || count( $walked ) < TrashMarkers::POSTS_LIMIT ) {
			return $deliverable;
		}

		return max( $markers->restorableCount( $record ), $deliverable );
	}
}
