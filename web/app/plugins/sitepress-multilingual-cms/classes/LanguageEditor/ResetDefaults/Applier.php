<?php

namespace WPML\LanguageEditor\ResetDefaults;

use WPML\LanguageEditor\Adapter\LanguageRepository;
use WPML\LanguageEditor\Labels;
use WPML\LanguageEditor\LanguageNames;
use WPML\LanguageEditor\Save\SaveEngine;
use WPML\OperationRecord\Repository;

use function WPML\Container\make;

class Applier {

	private $detector;

	private $languages;

	public function __construct( ?Detector $detector = null, ?LanguageRepository $languages = null ) {
		$this->detector  = $detector ? $detector : new Detector();
		$this->languages = $languages ? $languages : new LanguageRepository();
	}

	public function apply( array $groups ) {
		$wanted      = $this->wantedGroups( $groups );
		$differences = $this->detector->differences();

		$applied = [
			Detector::GROUP_FLAGS   => 0,
			Detector::GROUP_LABELS  => 0,
			Detector::GROUP_LOCALES => 0,
		];
		$codes   = [];
		$task    = null;

		$repository = $this->records();
		$record     = $repository->start(
			Repository::KIND_RESET_DEFAULTS,
			$this->languagesOf( $differences, $wanted )
		);

		$remaining = [
			Detector::GROUP_FLAGS   => 0,
			Detector::GROUP_LABELS  => 0,
			Detector::GROUP_LOCALES => 0,
		];

		if ( in_array( Detector::GROUP_FLAGS, $wanted, true ) ) {
			$applied[ Detector::GROUP_FLAGS ] = $this->resetFlags(
				$this->consented( $differences, Detector::GROUP_FLAGS, $remaining ),
				$codes
			);
		}

		if ( in_array( Detector::GROUP_LABELS, $wanted, true ) ) {
			$applied[ Detector::GROUP_LABELS ] = $this->resetLabels(
				$this->consented( $differences, Detector::GROUP_LABELS, $remaining ),
				$codes
			);
		}

		if ( in_array( Detector::GROUP_LOCALES, $wanted, true ) ) {
			$task = $this->resetLocales(
				$this->consented( $differences, Detector::GROUP_LOCALES, $remaining ),
				$codes,
				$applied
			);
		}

		$repository->finalize(
			$record,
			[
				'reset'     => $applied,
				'remaining' => $remaining,
			]
		);

		return [
			'applied'     => $applied,
			'remaining'   => $remaining,
			'partial'     => (bool) array_sum( $remaining ),
			'codes'       => array_values( array_map( 'strval', array_keys( $codes ) ) ),
			'record'      => (int) $record,
			'task'        => $task,
			'differences' => $differences,
		];
	}

	private function consented( array $differences, $group, array &$remaining ) {
		$languages = (array) $differences['groups'][ $group ]['languages'];
		$listed    = array_slice( $languages, 0, Detector::MAX_LISTED_LANGUAGES );

		$remaining[ $group ] = count( $languages ) - count( $listed );

		return $listed;
	}

	private function languagesOf( array $differences, array $wanted ) {
		$codes = [];

		foreach ( $wanted as $group ) {
			foreach ( (array) $differences['groups'][ $group ]['languages'] as $language ) {
				$code = isset( $language['code'] ) ? (string) $language['code'] : '';

				if ( '' !== $code ) {
					$codes[ $code ] = true;
				}
			}
		}

		return array_values( array_map( 'strval', array_keys( $codes ) ) );
	}

	protected function records() {
		return new Repository();
	}

	private function wantedGroups( array $groups ) {
		$known = [ Detector::GROUP_FLAGS, Detector::GROUP_LABELS, Detector::GROUP_LOCALES ];

		return array_values(
			array_intersect( $known, array_map( 'strval', $groups ) )
		);
	}

	private function resetFlags( array $languages, array &$codes ) {
		$written = 0;

		foreach ( $languages as $language ) {
			$code    = (string) $language['code'];
			$default = (string) $language['default'];

			if ( '' === $code || '' === $default ) {
				continue;
			}

			$this->languages->setFlag( $code, $default );

			$codes[ $code ] = true;
			$written ++;
		}

		return $written;
	}

	private function resetLabels( array $languages, array &$codes ) {
		$sources     = [];
		$displays    = [];
		$replaceable = [];
		$english     = [];
		$cells       = 0;

		foreach ( $languages as $language ) {
			$code = (string) $language['code'];

			if ( '' === $code ) {
				continue;
			}

			foreach ( (array) $language['cells'] as $cell ) {
				$display = (string) $cell['display'];
				$cells ++;

				if ( Detector::ENGLISH_DISPLAY_COLUMN === $display ) {
					$english[ $code ] = (string) $cell['default'];

					$codes[ $code ] = true;
					continue;
				}

				$sources[ $code ]     = true;
				$displays[ $display ] = true;

				if ( null !== $cell['current'] && '' !== (string) $cell['current'] ) {
					$replaceable[ $code ][] = (string) $cell['current'];
				}

				$codes[ $code ] = true;
			}
		}

		if ( $english ) {
			foreach ( $english as $code => $name ) {
				$this->saveEnglishLabel( (string) $code, $name );
			}

			LanguageNames::resetCache();
		}

		if ( $sources && $displays ) {
			$this->seedLabels( array_keys( $sources ), array_keys( $displays ), $replaceable );
		}

		return $cells;
	}

	protected function saveEnglishLabel( $code, $name ) {
		( new Labels() )->save( $code, [ Detector::ENGLISH_DISPLAY_COLUMN => $name ] );
	}

	protected function seedLabels( array $codes, array $displays, array $replaceable ) {
		LanguageNames::seed( $codes, $displays, [], $replaceable );
	}

	private function resetLocales( array $languages, array &$codes, array &$applied ) {
		$changes = [];

		foreach ( $languages as $language ) {
			$code    = (string) $language['code'];
			$default = (string) $language['default'];

			if ( '' === $code || '' === $default ) {
				continue;
			}

			$old = $this->currentLocale( $language, $default );

			if ( $old === $default ) {
				continue;
			}

			$changes[] = [
				'code'   => $code,
				'locale' => [
					'old' => $old,
					'new' => $default,
				],
			];

			$codes[ $code ] = true;
			$applied[ Detector::GROUP_LOCALES ] ++;
		}

		if ( ! $changes ) {
			return null;
		}

		return $this->engine()->start( $changes )->toResponse();
	}

	protected function engine() {
		return make( SaveEngine::class );
	}

	private function currentLocale( array $language, $default ) {
		$rowLocale = (string) $language['rowLocale'];
		$mapLocale = (string) $language['mapLocale'];

		if ( $rowLocale !== $default ) {
			return $rowLocale;
		}

		return $mapLocale;
	}
}
