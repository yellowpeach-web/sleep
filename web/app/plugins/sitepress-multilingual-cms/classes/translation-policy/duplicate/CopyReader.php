<?php

namespace WPML\TranslationPolicy\Duplicate;

use WPML\EditHistory\EntryLog;
use WPML\EditHistory\InternalWrite;
use WPML\EditHistory\State;
use WPML\EditHistory\StateStore;
use WPML\EditHistory\Subject;

class CopyReader {

	private $store;

	private $log;

	private $placeOf;

	public function __construct( StateStore $store, EntryLog $log, callable $placeOf ) {
		$this->store   = $store;
		$this->log     = $log;
		$this->placeOf = $placeOf;
	}

	public static function create(): self {
		global $wpdb;

		return new self( new \WPML\EditHistory\WpdbStateStore( $wpdb ), new \WPML\EditHistory\WpdbEntryLog( $wpdb ), [ self::class, 'placeInGroup' ] );
	}

	public function isCopy( int $postId ): bool {
		$subject = Subject::post( $postId );
		$state   = $this->store->get( $subject );
		$headId  = $state ? $state->headEntryId() : 0;
		if ( ! $headId || ( State::BASE_APPLIED === $state->baseState() && $state->baseEntryId() >= $headId ) ) {
			return false;
		}

		[ $language, $originalId ] = ( $this->placeOf )( $postId );
		if ( ! $state->belongsTo( (string) $language, (int) $originalId ) ) {
			return false;
		}

		foreach ( $this->log->since( $subject, $state->language(), $headId - 1 ) as $entry ) {
			return (int) $entry->id === $headId && InternalWrite::COPY === $entry->source;
		}

		return false;
	}

	public static function placeInGroup( int $postId ): array {
		$type    = apply_filters( 'wpml_element_type', get_post_type( $postId ) );
		$details = apply_filters( 'wpml_element_language_details', null, [ 'element_id' => $postId, 'element_type' => $type ] );

		return [
			(string) ( $details->language_code ?? '' ),
			(int) apply_filters( 'wpml_original_element_id', null, $postId, $type ),
		];
	}
}
