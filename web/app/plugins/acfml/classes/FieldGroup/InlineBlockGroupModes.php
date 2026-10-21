<?php

namespace ACFML\FieldGroup;

use ACFML\Options;
use ACFML\Upgrade\CommandsProvider;
use ACFML\Upgrade\Upgrade;

class InlineBlockGroupModes {

	const OPTION = 'inline-block-group-modes';

	private $isFirstRequestOnExistingSite;

	public function getOrStore( string $groupKey ): string {
		$storedModes = Options::get( self::OPTION );

		if ( is_array( $storedModes ) && in_array( $storedModes[ $groupKey ] ?? null, Mode::ALL, true ) ) {
			return $storedModes[ $groupKey ];
		}

		if ( null === $storedModes && self::isUpgradePendingOnExistingSite() ) {
			return Mode::ADVANCED;
		}

		$mode = $this->isFirstRequestOnExistingSite( $storedModes ) ? Mode::ADVANCED : Mode::TRANSLATION;

		Options::set( self::OPTION, array_merge( (array) $storedModes, [ $groupKey => $mode ] ) );

		return $mode;
	}

	public function startStoringAfterUpgrade(): void {
		if ( ! self::isUpgradePendingOnExistingSite() ) {
			self::startStoring();
		}
	}

	public static function startStoringOnNewSite(): void {
		if ( ! self::isExistingSite() ) {
			self::startStoring();
		}
	}

	private static function startStoring(): void {
		if ( null === Options::get( self::OPTION ) ) {
			Options::set( self::OPTION, [] );
		}
	}

	private function isFirstRequestOnExistingSite( $storedModes ): bool {
		if ( null === $this->isFirstRequestOnExistingSite ) {
			$this->isFirstRequestOnExistingSite = null === $storedModes && self::isExistingSite();
		}

		return $this->isFirstRequestOnExistingSite;
	}

	private static function isUpgradePendingOnExistingSite(): bool {
		return self::isExistingSite() && CommandsProvider::getHash() !== Options::get( Upgrade::KEY_LAST_MIGRATION_HASH );
	}

	private static function isExistingSite(): bool {
		return null !== Options::get( Upgrade::KEY_LAST_MIGRATION_HASH );
	}
}
