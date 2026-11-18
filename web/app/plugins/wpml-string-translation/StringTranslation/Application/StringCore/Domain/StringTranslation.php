<?php

namespace WPML\StringTranslation\Application\StringCore\Domain;

use WPML\StringTranslation\Application\StringCore\Legacy\OldVersionInMemory;

require_once __DIR__ . '/../Legacy/OldVersionInMemory.php';

if ( OldVersionInMemory::isBeforeNow( OldVersionInMemory::STRING_TRANSLATION_REORDERED ) ) {
	require_once __DIR__ . '/Legacy/StringTranslation352.php';
	class_alias( Legacy\StringTranslation352::class, StringTranslation::class );
} else {
	class StringTranslation {

		private $string;

		private $language;

		private $value;

		public function __construct(
			string $language,
			string $value,
			?StringItem $string = null
		) {
			$this->setString( $string );
			$this->setLanguage( $language );
			$this->setValue( $value );
		}

		public function setString( ?StringItem $string ) {
			$this->string = $string;
		}

		public function getString(): ?StringItem {
			return $this->string;
		}

		public function getLanguage(): string {
			return $this->language;
		}

		public function setLanguage( string $language ) {
			$this->language = $language;
		}

		public function getValue(): string {
			return $this->value;
		}

		public function setValue( string $value ) {
			$this->value = $value;
		}
	}
}
