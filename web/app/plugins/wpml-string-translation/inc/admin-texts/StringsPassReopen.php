<?php

namespace WPML\ST\AdminTexts;

use WPML\Setup\Option;
use WPML\StringTranslation\Infrastructure\TranslateEverything\UntranslatedStringsFactory;

class StringsPassReopen {

	private $isTranslateEverythingOn;

	private $reopenStringsKind;

	public function __construct( ?callable $isTranslateEverythingOn = null, ?callable $reopenStringsKind = null ) {
		$this->isTranslateEverythingOn = $isTranslateEverythingOn ?: [ self::class, 'translateEverythingIsOn' ];
		$this->reopenStringsKind       = $reopenStringsKind ?: [ self::class, 'markStringsKindUncompleted' ];
	}

	public function isTranslateEverythingOn(): bool {
		return (bool) call_user_func( $this->isTranslateEverythingOn );
	}

	public function afterRegistration(): bool {
		if ( ! $this->isTranslateEverythingOn() ) {
			return false;
		}

		call_user_func( $this->reopenStringsKind );

		return true;
	}

	public static function translateEverythingIsOn(): bool {
		return class_exists( Option::class ) && (bool) Option::shouldTranslateEverything();
	}

	public static function markStringsKindUncompleted() {
		( new UntranslatedStringsFactory() )->create()->markEverythingAsUncompleted();
	}
}
