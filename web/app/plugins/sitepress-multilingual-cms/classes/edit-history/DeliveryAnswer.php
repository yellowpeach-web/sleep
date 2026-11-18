<?php

namespace WPML\EditHistory;

class DeliveryAnswer {

	const DELIVERY_KEY = 'delivery';
	const DECISION_KEY = 'wpml_edit_decision';

	const OVERWRITE = 'overwrite';
	const UNCLEAR   = 'unclear';

	const REMOVED = 'removed';

	const FLAGS = [
		'overwrite'        => self::OVERWRITE,
		'unclear'          => self::UNCLEAR,
		'do-not-overwrite' => self::UNCLEAR,
	];

	public $flag;

	public $retranslation;

	public $deliveryId;

	public $units;

	public $absent;

	public function __construct( $flag, bool $retranslation, $deliveryId, array $units, array $absent = [] ) {
		$this->flag          = $flag;
		$this->retranslation = $retranslation;
		$this->deliveryId    = $deliveryId;
		$this->units         = $units;
		$this->absent        = $absent;
	}

	public static function read( $answer ): self {
		$answer   = self::asArray( $answer );
		$decision = self::asArray( $answer[ self::DECISION_KEY ] ?? null );
		$delivery = self::asArray( $answer[ self::DELIVERY_KEY ] ?? null );
		$flag     = $decision['flag'] ?? null;
		$id       = (int) ( $delivery['id'] ?? 0 );

		return new self(
			is_string( $flag ) ? ( self::FLAGS[ $flag ] ?? null ) : null,
			! empty( $delivery['retranslation'] ?? $decision['retranslated'] ?? false ),
			$id > 0 ? $id : null,
			array_values( array_filter( (array) ( $decision['units'] ?? $decision['pairing'] ?? [] ), 'is_array' ) )
		);
	}

	public static function keysOf( $item ) {
		$item = self::asArray( $item );
		$keys = array_intersect_key( $item, [ self::DELIVERY_KEY => true, self::DECISION_KEY => true ] );

		return $keys ?: null;
	}

	public function removedUnits(): array {
		$units = array_map( 'strval', array_keys( $this->absent ) );
		foreach ( $this->units as $item ) {
			if ( self::REMOVED === ( $item['result'] ?? $item['decision'] ?? null ) && isset( $item['unit'] ) ) {
				$units[] = (string) $item['unit'];
			}
		}

		return array_values( array_unique( array_filter( $units, fn( string $unit ) => 0 === strpos( $unit, 'package-string-' ) ) ) );
	}

	public function withAbsent( array $absent ): self {
		$answer         = clone $this;
		$answer->absent = $absent;

		return $answer;
	}

	private static function asArray( $value ): array {
		if ( is_object( $value ) ) {
			$value = json_decode( (string) wp_json_encode( $value ), true );
		}

		return is_array( $value ) ? $value : [];
	}
}
