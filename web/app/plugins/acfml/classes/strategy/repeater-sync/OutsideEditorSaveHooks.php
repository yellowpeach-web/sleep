<?php

namespace ACFML\Repeater\Sync;

use ACFML\Helper\Fields;
use ACFML\Repeater\Shuffle\Resolver;
use ACFML\Repeater\Shuffle\Strategy;
use WPML\FP\Obj;

class OutsideEditorSaveHooks implements \IWPML_Action {

	const PRIORITY_BEFORE_ACF_WRITES = \WPML_ACF_Repeater_Shuffle::PRIORITY_BEFORE_ACF_WRITES;
	const PRIORITY_AFTER_ACF_WROTE   = \WPML_ACF_Repeater_Shuffle::PRIORITY_AFTER_ACF_WROTE;

	private $editorStrategy = false;

	private $runningSaves = [];

	private $translationStatusFactory;

	public function __construct( TranslationStatusFactory $translationStatusFactory ) {
		$this->translationStatusFactory = $translationStatusFactory;
	}

	public function add_hooks() {
		add_action( 'acf/save_post', [ $this, 'startFormSave' ], self::PRIORITY_BEFORE_ACF_WRITES );
		add_action( 'acf/save_post', [ $this, 'finishFormSave' ], self::PRIORITY_AFTER_ACF_WROTE );
		add_filter( 'acf/pre_update_value', [ $this, 'updateValueWithTranslations' ], 10, 4 );
	}

	public function startFormSave( $acfId ) {
		if ( $this->isSavedByEditor( $acfId ) ) {
			return;
		}

		$shuffled = Resolver::forAcfId( $acfId );

		if ( $shuffled && $shuffled->isValidId( $acfId ) ) {
			$this->start( $acfId, OutsideEditorSave::ofForm( $shuffled, $acfId, $this->translationStatusFactory->create( $shuffled ) ) );
		}
	}

	public function finishFormSave( $acfId ) {
		$this->finish( $acfId );
	}

	public function updateValueWithTranslations( $check, $value, $acfId, $field ) {
		if ( null !== $check || $this->runningSaves || doing_action( 'acf/save_post' ) ) {
			return $check;
		}

		$shuffled = Resolver::forAcfId( $acfId );

		if ( ! self::isTranslatedOriginal( $shuffled, $acfId ) ) {
			return $check;
		}

		$status = self::canHoldRows( $field ) ? $this->translationStatusFactory->create( $shuffled ) : null;

		$this->start( $acfId, OutsideEditorSave::ofValue( $shuffled, $acfId, $status ) );

		try {
			return acf_update_value( $value, $acfId, $field );
		} finally {
			$this->finish( $acfId );
		}
	}

	private function start( $acfId, OutsideEditorSave $save ) {
		$this->runningSaves[ (string) $acfId ] = $save;
		$save->start();
	}

	private function finish( $acfId ) {
		$save = $this->runningSaves[ (string) $acfId ] ?? null;

		if ( $save ) {
			unset( $this->runningSaves[ (string) $acfId ] );
			$save->finish();
		}
	}

	private function isSavedByEditor( $acfId ) {
		if ( false === $this->editorStrategy ) {
			$this->editorStrategy = Resolver::getStrategy();
		}

		return $this->editorStrategy && $this->editorStrategy->isValidId( $acfId );
	}

	private static function isTranslatedOriginal( $shuffled, $acfId ) {
		return $shuffled
			&& $shuffled->isValidId( $acfId )
			&& $shuffled->hasTranslations( $acfId )
			&& $shuffled->isOriginal( $acfId );
	}

	private static function canHoldRows( $field ) {
		return Fields::isWrapperOrGroup( $field ) || 'clone' === Obj::prop( 'type', $field );
	}
}
