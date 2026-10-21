<?php

namespace WPML\Upgrade\Commands;

use WPML\Core\Component\LanguageEditor\Domain\EnglishNameQualification;
use WPML\Core\Component\LanguageEditor\Domain\Repository\LanguageRepositoryInterface;

class RepairSuffixedLanguageNames implements \IWPML_Upgrade_Command {

	const SUFFIXED = '/^(.+) (\d+)$/';

	private $languages;

	private $results;

	private $renamed = false;

	public function __construct( array $args = [] ) {
		$this->languages = isset( $args[0] ) && $args[0] instanceof LanguageRepositoryInterface
			? $args[0]
			: new \WPML\LanguageEditor\Adapter\LanguageRepository();
	}

	public function run_admin() {
		$this->renamed = false;

		try {
			$this->repair();
			$this->results = true;
		} catch ( \Throwable $e ) {
			if ( function_exists( '\WPML\PHP\Logger\error' ) ) {
				\WPML\PHP\Logger\error(
					sprintf( 'RepairSuffixedLanguageNames: stopped, will retry on the next admin request: %s', $e->getMessage() )
				);
			}
			$this->results = false;
		} finally {
			if ( $this->renamed ) {
				\WPML\LanguageEditor\Cache::flush();
			}
		}

		return $this->results;
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->results;
	}

	private function repair() {

		foreach ( $this->languages->activeNameRows() as $row ) {
			$base = $this->composedBaseOf( $row );
			if ( null === $base ) {
				continue;
			}

			$owner = $this->languages->englishNameOwner( $base, $row['code'] );

			if ( null !== $owner && ! empty( $owner['active'] ) ) {
				continue;
			}

			if ( null !== $owner && $this->languages->hasRetainedContent( (string) $owner['code'] ) ) {
				continue;
			}

			if ( null !== $owner ) {
				$this->languages->renameEnglishName(
					(int) $owner['id'],
					EnglishNameQualification::yieldedName( $base, $owner, $this->languages )
				);
				$this->renamed = true;
			}

			$this->languages->renameEnglishName( (int) $row['id'], $base );
			$this->renamed = true;
		}
	}

	private function composedBaseOf( array $row ) {
		$matches = [];
		if ( ! preg_match( self::SUFFIXED, $row['english_name'], $matches ) || (int) $matches[2] < 2 ) {
			return null;
		}

		$base = $matches[1];

		$preset = $this->languages->presetOwningCode( $row['code'] );
		if ( null === $preset || ! isset( $preset['english_name'] ) || '' === $preset['english_name'] ) {
			return null;
		}

		$presetName = (string) $preset['english_name'];
		$composed   = EnglishNameQualification::composedName(
			$presetName,
			$row['country'],
			$row['code'],
			$this->languages
		);

		return ( $base === $presetName || $base === $composed ) ? $base : null;
	}
}
