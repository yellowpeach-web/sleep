<?php

namespace WPML\TranslationPolicy\Settings;

use WPML\Setup\Option;
use WPML\TranslationPolicy\Policy\PolicyTable;
use WPML\WP\OptionManager;

class SetupChoice {

	const KEY_TRANSLATE_SOME_METHOD = 'translate-some-method';

	private $saveSettings;

	private $translateSomeMethod;

	public function __construct( SaveSettings $saveSettings, string $translateSomeMethod ) {
		$this->saveSettings        = $saveSettings;
		$this->translateSomeMethod = $translateSomeMethod;
	}

	public static function create(): self {
		return new self( Section::saveSettings(), self::translateSomeMethodAtFinish() );
	}

	public static function translateSomeMethodAtFinish(): string {
		$saved = self::translateSomeMethod();
		if ( '' !== $saved ) {
			return $saved;
		}

		return self::preselection( \TranslationProxy::has_preferred_translation_service(), \WPML_TM_ATE_Status::is_account_usable() );
	}

	public static function preselection( bool $hasPreferredService, bool $isAteUsable ): string {
		return ! $hasPreferredService && $isAteUsable ? PolicyTable::METHOD_PTC : PolicyTable::METHOD_SELF;
	}

	public static function translateSomeMethod(): string {
		$method = ( new OptionManager() )->get( Option::OPTION_GROUP, self::KEY_TRANSLATE_SOME_METHOD, '' );

		return in_array( $method, PolicyTable::METHODS, true ) ? $method : '';
	}

	public static function saveTranslateSomeMethod( string $method ): bool {
		if ( ! in_array( $method, PolicyTable::METHODS, true ) ) {
			return false;
		}

		( new OptionManager() )->set( Option::OPTION_GROUP, self::KEY_TRANSLATE_SOME_METHOD, $method );

		return true;
	}

	public function write( bool $translateEverything ) {
		$values = [
			PolicyTable::COLUMN_EDITOR => PolicyTable::EDITOR_ATE,
			PolicyTable::COLUMN_METHOD => $translateEverything ? PolicyTable::METHOD_PTC : $this->translateSomeMethod,
		];

		if ( $this->save( $values ) ) {
			return;
		}

		foreach ( $values as $column => $value ) {
			$this->save( [ $column => $value ] );
		}
	}

	private function save( array $values ): bool {
		try {
			$this->saveSettings->save( [ array_merge( [ 'scope' => PolicyTable::SCOPE_SITE ], $values ) ], SaveSettings::REASON_SETUP );
		} catch ( LockedChoice $e ) {
			return false;
		} catch ( \RuntimeException $e ) {
			return true;
		}

		return true;
	}
}
