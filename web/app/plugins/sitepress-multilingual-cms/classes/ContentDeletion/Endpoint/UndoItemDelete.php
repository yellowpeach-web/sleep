<?php

namespace WPML\ContentDeletion\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\OperationRecord\Repository;
use WPML\OperationRecord\RestoreSet;

class UndoItemDelete implements IHandler {

	const NOTE_UNDONE = 'undone';

	private $records;

	private $restore;

	public function __construct( ?Repository $records = null, ?RestoreSet $restore = null ) {
		$this->records = $records ? $records : new Repository();
		$this->restore = $restore ? $restore : new RestoreSet();
	}

	public function run( Collection $data ) {
		$id = (int) $data->get( 'record', 0 );

		if ( $id <= 0 ) {
			return Either::left( array( 'error' => 'invalid_record' ) );
		}

		$records = $this->records;
		$record  = $records->get( $id );

		if ( ! is_array( $record ) ) {
			return Either::left( array( 'error' => 'not_found' ) );
		}

		$kind = isset( $record['kind'] ) ? (string) $record['kind'] : '';

		if ( Repository::KIND_ITEM_DELETE !== $kind && Repository::KIND_BULK_DELETE !== $kind ) {
			return Either::left( array( 'error' => 'unsupported_kind' ) );
		}

		if ( Repository::STATUS_FINALIZED !== ( isset( $record['status'] ) ? $record['status'] : '' ) ) {
			return Either::left( array( 'error' => 'not_finished' ) );
		}

		if ( ! isset( $record['actor'] ) || (int) $record['actor'] !== (int) get_current_user_id() ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		if ( Repository::ROUTE_TRASH !== ( isset( $record['route'] ) ? $record['route'] : null ) ) {
			return Either::left( array( 'error' => 'not_undoable' ) );
		}

		if ( self::NOTE_UNDONE === ( isset( $record['note'] ) ? (string) $record['note'] : '' ) ) {
			return Either::right(
				array(
					'restored'  => 0,
					'refused'   => 0,
					'missing'   => 0,
					'remaining' => 0,
					'ids'       => array(),
					'already'   => true,
				)
			);
		}

		$until = isset( $record['undo_until'] ) ? (int) $record['undo_until'] : 0;

		if ( $until <= 0 || $until < time() ) {
			return Either::left( array( 'error' => 'expired' ) );
		}

		$cursor = isset( $record['undo_cursor'] ) ? (int) $record['undo_cursor'] : 0;

		$result = $this->restore->restore( $id, array(), $cursor );

		if ( empty( $result['engine'] ) ) {
			return Either::left( array( 'error' => 'translations_unavailable' ) );
		}

		$remaining = isset( $result['remaining'] ) ? (int) $result['remaining'] : 0;
		$moved     = isset( $result['cursor'] ) ? (int) $result['cursor'] : $cursor;

		if ( $remaining > 0 && $result['restored'] < 1 && $result['missing'] < 1 && $moved <= $cursor ) {
			$remaining           = 0;
			$result['remaining'] = 0;
		}

		$refused = ( isset( $record['refused'] ) ? (int) $record['refused'] : 0 ) + (int) $result['refused'];

		$result['refused'] = $refused;

		$patch = array(
			'undone_at'   => time(),
			'restored'    => ( isset( $record['restored'] ) ? (int) $record['restored'] : 0 ) + (int) $result['restored'],
			'refused'     => $refused,
			'undo_cursor' => $moved,
		);

		if ( $remaining < 1 ) {
			$patch['note'] = self::NOTE_UNDONE;
		}

		$records->patch( $id, $patch );

		unset( $result['engine'], $result['cursor'] );

		return Either::right( array_merge( $result, array( 'already' => false ) ) );
	}
}
