<?php

namespace WPML\EditHistory;

class Sweep {

	const AGE = 7776000;

	const PER_RUN = 100;

	private $log;

	private $store;

	private $owner;

	private $now;

	private $perRun;

	public function __construct( EntryLog $log, StateStore $store, callable $owner, $now = null, int $perRun = self::PER_RUN ) {
		$this->log    = $log;
		$this->store  = $store;
		$this->owner  = $owner;
		$this->now    = $now ?? 'time';
		$this->perRun = $perRun;
	}

	public function run( ?Subject $after ) {
		$subjects = $this->log->subjectsWithEntriesBefore( ( $this->now )() - self::AGE, $this->perRun, $after );

		foreach ( $subjects as $subject ) {
			if ( Subject::POST === $subject->kind() ) {
				$this->sweep( $subject );
			}
		}

		return count( $subjects ) < $this->perRun ? null : end( $subjects );
	}

	private function sweep( Subject $subject ) {
		$owner = ( $this->owner )( $subject->id() );
		if ( null === $owner ) {
			$this->log->deleteSubject( $subject );
			$this->store->delete( $subject );

			return;
		}

		[ $language, $originalId ] = $owner;

		$state = $this->store->get( $subject );
		if ( ! $state || ( ! $state->isHeadOf( $language, $originalId ) && null === $state->itemState() ) ) {
			$this->log->deleteSubject( $subject );
		}
	}
}
