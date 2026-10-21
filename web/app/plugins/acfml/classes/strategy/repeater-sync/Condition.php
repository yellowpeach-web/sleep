<?php

namespace ACFML\Repeater\Sync;

use ACFML\FieldGroup\Mode;
use ACFML\Repeater\Shuffle\Strategy;
use WPML\FP\Obj;

class Condition {

	public static function isActiveFor( Strategy $shuffled, $entityId ) {
		if ( ! $shuffled->isValidId( $entityId ) || ! $shuffled->isOriginal( $entityId ) ) {
			return false;
		}

		if ( Mode::TRANSLATION === Mode::getForFieldableEntity( $shuffled->getEntityType(), $entityId ) ) {
			return true;
		}

		return self::isSelectedInExpertMode( $shuffled, $entityId );
	}

	private static function isSelectedInExpertMode( Strategy $shuffled, $entityId ) {
		$trid = $shuffled->getTrid( $entityId );

		return $trid && true === Obj::propOr( false, $trid, CheckboxOption::get() );
	}
}
