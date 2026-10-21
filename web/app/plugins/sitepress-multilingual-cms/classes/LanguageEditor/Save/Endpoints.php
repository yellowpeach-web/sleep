<?php

namespace WPML\LanguageEditor\Save;

use WPML\LanguageEditor\Save\Endpoint\StartSave;
use WPML\LanguageEditor\Save\Endpoint\AdvanceSave;
use WPML\LanguageEditor\Save\Endpoint\SaveStatus;
use WPML\LanguageEditor\Save\Endpoint\ResumeSave;
use WPML\LanguageEditor\Save\Endpoint\EstimateImpact;
use WPML\LanguageEditor\Save\Endpoint\PauseTranslation;
use WPML\LanguageEditor\Save\Endpoint\ResumeTranslation;
use WPML\LanguageEditor\Save\Endpoint\ListRemovedLanguages;
use WPML\LanguageEditor\Save\Endpoint\MoveRemovedLanguage;
use WPML\LanguageEditor\Save\Endpoint\RecordLanguageRemoval;
use WPML\LanguageEditor\Save\Endpoint\ProbeLanguageDefaults;
use WPML\LanguageEditor\Save\Endpoint\ResetLanguageDefaults;

class Endpoints {

	const ESTIMATE = 'estimateStructuralSaveImpact';
	const START    = 'startStructuralSave';
	const ADVANCE  = 'advanceStructuralSave';
	const STATUS   = 'structuralSaveStatus';
	const RESUME   = 'resumeStructuralSave';

	const PAUSE_TRANSLATION  = 'pauseTranslation';
	const RESUME_TRANSLATION = 'resumeTranslation';

	const LIST_REMOVED_LANGUAGES = 'listRemovedLanguages';
	const MOVE_REMOVED_LANGUAGE  = 'moveRemovedLanguage';

	const RECORD_LANGUAGE_REMOVAL = 'recordLanguageRemoval';

	const PROBE_LANGUAGE_DEFAULTS = 'probeLanguageDefaults';
	const RESET_LANGUAGE_DEFAULTS = 'resetLanguageDefaults';

	public static function get() {
		return [
			self::ESTIMATE => EstimateImpact::class,
			self::START    => StartSave::class,
			self::ADVANCE  => AdvanceSave::class,
			self::STATUS   => SaveStatus::class,
			self::RESUME   => ResumeSave::class,
			self::PAUSE_TRANSLATION  => PauseTranslation::class,
			self::RESUME_TRANSLATION => ResumeTranslation::class,
			self::LIST_REMOVED_LANGUAGES => ListRemovedLanguages::class,
			self::MOVE_REMOVED_LANGUAGE  => MoveRemovedLanguage::class,
			self::RECORD_LANGUAGE_REMOVAL => RecordLanguageRemoval::class,
			self::PROBE_LANGUAGE_DEFAULTS => ProbeLanguageDefaults::class,
			self::RESET_LANGUAGE_DEFAULTS => ResetLanguageDefaults::class,
		];
	}
}
