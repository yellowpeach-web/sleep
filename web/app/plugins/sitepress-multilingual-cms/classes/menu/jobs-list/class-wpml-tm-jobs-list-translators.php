<?php

use \WPML\FP\Fns;
use \WPML\FP\Lst;
use \WPML\Element\API\Languages;
use WPML\Core\SharedKernel\Component\Translator\Domain\Translator;
use function \WPML\FP\flip;
use function \WPML\FP\curryN;

class WPML_TM_Jobs_List_Translators {
	const SELF_TRANSLATOR_PROVIDER_CLASS = 'WPML\\Legacy\\Component\\Translator\\Domain\\Query\\SelfTranslatorProvider';

	private $translator_records;

	private $selfTranslatorProvider;

	public function __construct(
		WPML_Translator_Records $translator_records,
		$selfTranslatorProvider = null
	) {
		$this->translator_records = $translator_records;
		$this->selfTranslatorProvider = $selfTranslatorProvider ?: $this->createSelfTranslatorProvider();
	}


	public function get() {
		$translators = $this->translator_records->get_users_with_capability();

		return array_map( [ $this, 'getTranslatorData' ], $translators );
	}

	public function getWithSelfTranslator() {
		$translators    = $this->get();
		$translatorData = $this->getSelfTranslatorEntry();

		if ( ! $translatorData || $this->hasTranslator( $translators, (int) $translatorData['value'] ) ) {
			return $translators;
		}

		$translators[] = $translatorData;

		return $translators;
	}

	public function getSelfTranslatorEntry() {
		$selfTranslator = $this->getSelfTranslator();

		if ( ! $selfTranslator ) {
			return null;
		}

		$translatorData = $this->getSelfTranslatorData( $selfTranslator );

		return count( $translatorData['languagePairs'] ) ? $translatorData : null;
	}

	private function getTranslatorData( $translator ) {
		return [
			'value'         => $translator->ID,
			'label'         => $translator->display_name,
			'languagePairs' => $this->getLanguagePairs( $translator ),
		];
	}

	private function getLanguagePairs( $translator ) {
		$isValidLanguage       = Lst::includes( Fns::__,  Lst::pluck( 'code', Languages::getAll() ) );
		$sourceIsValidLanguage = flip( $isValidLanguage );
		$getValidTargets       = Fns::filter( $isValidLanguage );

		$makePair = curryN(
			2,
			function ( $source, $target ) {
				return [
					'source' => $source,
					'target' => $target,
				];
			}
		);

		$getAsPair = curryN(
			3,
			function ( $makePair, $targets, $source ) {
				return Fns::map( $makePair( $source ), $targets );
			}
		);

		return \wpml_collect( $translator->language_pairs )
			->filter( $sourceIsValidLanguage )
			->map( $getValidTargets )
			->map( $getAsPair( $makePair ) )
			->flatten( 1 )
			->toArray();
	}

	private function createSelfTranslatorProvider() {
		$providerClass = self::SELF_TRANSLATOR_PROVIDER_CLASS;

		return class_exists( $providerClass ) ? new $providerClass() : null;
	}

	private function getSelfTranslator() {
		if ( ! $this->selfTranslatorProvider || ! is_callable( [ $this->selfTranslatorProvider, 'get' ] ) ) {
			return null;
		}

		$selfTranslator = call_user_func( [ $this->selfTranslatorProvider, 'get' ] );

		return $selfTranslator instanceof Translator ? $selfTranslator : null;
	}

	private function getSelfTranslatorData( Translator $translator ) {
		return [
			'value'         => $translator->getId(),
			'label'         => $translator->getName(),
			'languagePairs' => $this->getSelfTranslatorLanguagePairs( $translator ),
		];
	}

	private function hasTranslator( array $translators, $translatorId ) {
		foreach ( $translators as $translator ) {
			if ( (int) $translator['value'] === $translatorId ) {
				return true;
			}
		}

		return false;
	}

	private function getSelfTranslatorLanguagePairs( Translator $translator ) {
		$languagePairs = [];

		foreach ( $translator->getLanguagePairs() as $languagePair ) {
			foreach ( $languagePair->getTo() as $target ) {
				$languagePairs[] = [
					'source' => $languagePair->getFrom(),
					'target' => $target,
				];
			}
		}

		return $languagePairs;
	}
}
