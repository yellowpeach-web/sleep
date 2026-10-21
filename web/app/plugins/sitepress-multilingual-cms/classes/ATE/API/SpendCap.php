<?php

namespace WPML\TM\ATE\API;

use WPML\FP\Obj;

class SpendCap {

	const REASON = 'site_spend_cap_reached';

	private $capWords;

	private $usedWords;

	private $resetsOn;

	private $detectedAt;

	private $atPause;

	private $resumedAt;

	private $sighted = false;

	private function __construct( $capWords, $usedWords, $resetsOn = null, $detectedAt = null, $atPause = null, $resumedAt = null ) {
		$this->capWords   = (int) $capWords;
		$this->usedWords  = (int) $usedWords;
		$this->resetsOn   = $resetsOn;
		$this->detectedAt = $detectedAt;
		$this->atPause    = null !== $detectedAt
			? ( $atPause ?: [ 'cap' => (int) $capWords, 'used' => (int) $usedWords ] )
			: null;
		$this->resumedAt  = null !== $detectedAt ? $resumedAt : null;
	}

	public static function fromCredits( $credits ) {
		$block = is_array( $credits ) ? Obj::propOr( null, 'site_spend_cap', $credits ) : null;
		if ( ! is_array( $block ) ) {
			return null;
		}

		$capWords = self::intOrNull( Obj::propOr( null, 'cap_words', $block ) );
		if ( null === $capWords || $capWords <= 0 ) {
			return null;
		}

		$resetsOn = Obj::propOr( null, 'resets_on', $block );

		return new self(
			$capWords,
			max( 0, (int) self::intOrNull( Obj::propOr( 0, 'used_words', $block ) ) ),
			is_string( $resetsOn ) && '' !== $resetsOn ? $resetsOn : null
		);
	}

	public static function fromHttpResult( $result ) {
		if ( ! is_array( $result ) || ! isset( $result['body'] ) || ! is_string( $result['body'] ) ) {
			return null;
		}

		$body = json_decode( $result['body'], true );
		if ( ! is_array( $body ) ) {
			return null;
		}

		$candidates = [ [ $body, false ], [ Obj::propOr( null, 'data', $body ), false ] ];

		foreach ( [ 'errors', 'items', 'jobs' ] as $listKey ) {
			$list = Obj::propOr( null, $listKey, $body );
			if ( ! is_array( $list ) ) {
				continue;
			}
			foreach ( $list as $entry ) {
				$entry        = (array) $entry;
				$candidates[] = [ $entry, false ];
				$candidates[] = [ Obj::propOr( null, 'stop_reason', $entry ), true ];
				$candidates[] = [ Obj::propOr( null, 'data', $entry ), false ];
			}
		}

		foreach ( $candidates as $candidate ) {
			$cap = is_array( $candidate[0] ) ? self::fromRefusalEntry( $candidate[0] ) : null;
			if ( $cap ) {
				$cap->sighted = $candidate[1];

				return $cap;
			}
		}

		return null;
	}

	private static function fromRefusalEntry( array $entry ) {
		if ( self::REASON !== Obj::propOr( '', 'reason', $entry ) ) {
			return null;
		}

		return new self(
			(int) self::intOrNull( Obj::propOr( 0, 'cap_words', $entry ) ),
			(int) self::intOrNull( Obj::propOr( 0, 'used_words', $entry ) ),
			null,
			time()
		);
	}

	public static function fromArray( array $data ) {
		$resetsOn = Obj::propOr( null, 'resetsOn', $data );

		$atPause = Obj::propOr( null, 'atPause', $data );

		return new self(
			(int) self::intOrNull( Obj::propOr( 0, 'capWords', $data ) ),
			(int) self::intOrNull( Obj::propOr( 0, 'usedWords', $data ) ),
			is_string( $resetsOn ) && '' !== $resetsOn ? $resetsOn : null,
			self::intOrNull( Obj::propOr( null, 'detectedAt', $data ) ),
			is_array( $atPause ) && isset( $atPause['cap'], $atPause['used'] )
				? [ 'cap' => (int) $atPause['cap'], 'used' => (int) $atPause['used'] ]
				: null,
			self::intOrNull( Obj::propOr( null, 'resumedAt', $data ) )
		);
	}

	public function toArray() {
		return [
			'capWords'   => $this->capWords,
			'usedWords'  => $this->usedWords,
			'resetsOn'   => $this->getResetsOn(),
			'reached'    => $this->isReached(),
			'detectedAt' => $this->detectedAt,
			'atPause'    => $this->atPause,
			'resumedAt'  => $this->resumedAt,
		];
	}

	public function readingMovedSincePause( SpendCap $fresh ) {
		$base = $this->atPause ?: [ 'cap' => $this->capWords, 'used' => $this->usedWords ];

		return $fresh->capWords > $base['cap'] || $fresh->usedWords < $base['used'];
	}

	public function isReached() {
		return null !== $this->detectedAt
			|| ( $this->capWords > 0 && $this->usedWords >= $this->capWords );
	}

	public function getCapWords() {
		return $this->capWords;
	}

	public function getUsedWords() {
		return $this->usedWords;
	}

	public function getResetsOn( $now = null ) {
		if ( null !== $this->resetsOn ) {
			return $this->resetsOn;
		}

		$now = null === $now ? time() : $now;

		return gmdate( 'Y-m-d', (int) gmmktime( 0, 0, 0, (int) gmdate( 'n', $now ) + 1, 1, (int) gmdate( 'Y', $now ) ) );
	}

	public function asRefused() {
		return new self( $this->capWords, $this->usedWords, $this->resetsOn, time() );
	}

	public function asResumed() {
		return new self( $this->capWords, $this->usedWords, $this->resetsOn, $this->detectedAt, $this->atPause, time() );
	}

	public function unresumed() {
		return new self( $this->capWords, $this->usedWords, $this->resetsOn, $this->detectedAt, $this->atPause );
	}

	public function isResumed() {
		return null !== $this->resumedAt;
	}

	public function merge( SpendCap $fresher ) {
		if ( $fresher->sighted && null !== $this->detectedAt && $this->isRecordOfThisPause( $fresher ) ) {
			return $this;
		}

		return new self(
			$fresher->capWords,
			$fresher->usedWords,
			null !== $fresher->resetsOn ? $fresher->resetsOn : $this->resetsOn,
			null !== $fresher->detectedAt ? $fresher->detectedAt : $this->detectedAt,
			null !== $fresher->detectedAt ? $fresher->atPause : $this->atPause,
			null !== $fresher->detectedAt ? null : $this->resumedAt
		);
	}

	public function isSighting() {
		return $this->sighted;
	}

	public function holds( $now = null ) {
		if ( ! $this->isReached() || $this->isResumed() ) {
			return false;
		}

		$now = null === $now ? time() : (int) $now;
		if ( null !== $this->detectedAt && gmdate( 'Y-m', $this->detectedAt ) !== gmdate( 'Y-m', $now ) ) {
			return false;
		}

		return ! ( $this->atPause && $this->capWords > $this->atPause['cap'] );
	}

	private function isRecordOfThisPause( SpendCap $record ) {
		return $this->atPause
			&& $record->capWords === $this->atPause['cap']
			&& $record->usedWords === $this->atPause['used'];
	}

	private static function intOrNull( $value ) {
		return is_numeric( $value ) ? (int) $value : null;
	}
}
