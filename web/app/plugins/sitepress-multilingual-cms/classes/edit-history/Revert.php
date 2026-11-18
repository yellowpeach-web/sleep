<?php

namespace WPML\EditHistory;

class Revert {

	const DONE   = 'done';
	const STALE  = 'stale';
	const LOCKED = 'locked';
	const FAILED = 'failed';

	const LOG_REVERTED = 'edit_history_reverted';
	const LOG_FAILED   = 'edit_history_revert_failed';

	private $store;

	private $items;

	private $copyOf;

	private $write;

	private $lockedBy;

	private $roots;

	private $report;

	private $jobLog;

	public function __construct( StateStore $store, PanelItems $items, callable $copyOf, callable $write, callable $lockedBy, callable $roots, callable $report, callable $jobLog ) {
		$this->store    = $store;
		$this->items    = $items;
		$this->copyOf   = $copyOf;
		$this->write    = $write;
		$this->lockedBy = $lockedBy;
		$this->roots    = $roots;
		$this->report   = $report;
		$this->jobLog   = $jobLog;
	}

	public function run( int $translationId, string $version ): string {
		$subject = Subject::post( $translationId );
		$state   = $this->store->get( $subject );
		if ( ! $state || PanelItem::version( $state ) !== $version || ! $this->items->current( $state ) ) {
			return self::STALE;
		}
		if ( ( $this->lockedBy )( $translationId ) ) {
			return self::LOCKED;
		}

		$copy   = $state->copy();
		$before = ( $this->copyOf )( $translationId, $state->originalId() );
		if ( null === $copy || null === $before ) {
			return $this->failed( $state, 'the saved copy or the page could not be read' );
		}

		$error = $this->write( $translationId, $copy, true );
		if ( null !== $error ) {
			$this->write( $translationId, $before, false );

			return $this->failed( $state, $error );
		}

		$base            = (array) $state->base();
		$base['deleted'] = $this->record( $state->deletedElements(), $translationId, $state->originalId() );
		$this->store->reverted( $subject, $base );

		( $this->jobLog )( self::LOG_REVERTED, [ 'post_id' => $translationId, 'ate_job_id' => $state->baseAteJobId(), 'deleted' => count( $base['deleted'] ) ] );
		try {
			( $this->report )( (int) $state->baseAteJobId(), $state->baseDeliveryId() );
		} catch ( \Throwable $error ) {
			( $this->jobLog )( RevertReport::LOG, [ 'ate_job_id' => $state->baseAteJobId(), 'sent' => false, 'reason' => $error->getMessage() ] );
		}

		return self::DONE;
	}

	public static function rootsOf( int $postId ): array {
		$content = (string) get_post_field( 'post_content', $postId );
		$uids    = (array) apply_filters( 'wpml_pb_block_uids', [], $postId, $content );
		$roots   = [];
		foreach ( parse_blocks( $content ) as $path => $block ) {
			$uid = (string) ( $uids[ $path ] ?? '' );
			if ( '' !== $uid ) {
				$roots[ $path ] = [
					'uid'   => $uid,
					'block' => (string) ( $block['blockName'] ?? '' ),
				];
			}
		}

		return $roots;
	}

	private function write( int $postId, array $copy, bool $asChoice ) {
		$write = fn() => InternalWrite::run( InternalWrite::REVERT, fn() => ( $this->write )( $postId, $copy ) );

		try {
			$saved = $asChoice ? PanelChoice::run( PanelChoice::REVERT, $write ) : $write();
		} catch ( \Throwable $error ) {
			return $error->getMessage();
		}

		return true === $saved ? null : 'the post was not saved';
	}

	private function record( array $record, int $translationId, int $originalId ): array {
		$written  = array_flip( array_column( ( $this->roots )( $translationId ), 'uid' ) );
		$original = ( $this->roots )( $originalId );

		$kept  = array_values( array_filter( $record, fn( array $element ) => ! isset( $written[ (string) ( $element['uid'] ?? '' ) ] ) ) );
		$known = array_flip( array_map( 'strval', array_column( $kept, 'uid' ) ) );
		if ( ! array_intersect_key( $written, array_flip( array_column( $original, 'uid' ) ) ) ) {
			return $kept;
		}

		foreach ( $original as $path => $root ) {
			if ( ! isset( $written[ $root['uid'] ] ) && ! isset( $known[ $root['uid'] ] ) ) {
				$kept[] = [
					'block'   => $root['block'],
					'path'    => (string) $path,
					'strings' => [],
					'uid'     => $root['uid'],
				];
			}
		}

		return $kept;
	}

	private function failed( State $state, string $reason ): string {
		( $this->jobLog )( self::LOG_FAILED, [ 'post_id' => $state->subject()->id(), 'ate_job_id' => $state->baseAteJobId(), 'reason' => $reason ] );

		return self::FAILED;
	}
}
