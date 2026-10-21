<?php

namespace WPML\LanguageEditor;

use WPML\LanguageEditor\Endpoint\SaveLanguages;
use WPML\LanguageEditor\Endpoint\GetAutomaticTranslationInfo;
use WPML\LanguageEditor\Endpoint\CountLanguageContent;
use WPML\LanguageEditor\Endpoint\ListLanguageContent;
use WPML\LanguageEditor\Endpoint\InvalidateCache;
use WPML\LanguageEditor\Endpoint\AddLanguageTranslationInfo;
use WPML\LanguageEditor\Endpoint\ActivateLanguageTranslation;
use WPML\LanguageEditor\Endpoint\GetLanguageLabels;
use WPML\LanguageEditor\Endpoint\UploadFlag;
use WPML\LanguageEditor\Endpoint\CheckCatalogueFreshness;
use WPML\LanguageEditor\Endpoint\SyncCatalogue;
use WPML\LanguageEditor\Endpoint\GetAteLanguages;

class Endpoints {

	const SAVE_LANGUAGES = 'saveLanguages';
	const GET_AUTOMATIC_TRANSLATION_INFO = 'getAutomaticTranslationInfo';
	const COUNT_LANGUAGE_CONTENT = 'countLanguageContent';
	const LIST_LANGUAGE_CONTENT = 'listLanguageContent';
	const INVALIDATE_CACHE = 'invalidateLanguagesCache';
	const ADD_LANGUAGE_TRANSLATION_INFO = 'getAddLanguageTranslationInfo';
	const ACTIVATE_LANGUAGE_TRANSLATION = 'activateLanguageTranslation';
	const GET_LANGUAGE_LABELS = 'getLanguageLabels';
	const UPLOAD_FLAG = 'uploadFlag';
	const CHECK_CATALOGUE_FRESHNESS = 'checkCatalogueFreshness';
	const SYNC_CATALOGUE = 'syncCatalogue';
	const GET_ATE_LANGUAGES = 'getAteLanguages';

	public static function get() {
		return [
			self::SAVE_LANGUAGES        => SaveLanguages::class,
			self::GET_AUTOMATIC_TRANSLATION_INFO => GetAutomaticTranslationInfo::class,
			self::COUNT_LANGUAGE_CONTENT => CountLanguageContent::class,
			self::LIST_LANGUAGE_CONTENT  => ListLanguageContent::class,
			self::INVALIDATE_CACHE       => InvalidateCache::class,
			self::ADD_LANGUAGE_TRANSLATION_INFO => AddLanguageTranslationInfo::class,
			self::ACTIVATE_LANGUAGE_TRANSLATION => ActivateLanguageTranslation::class,
			self::GET_LANGUAGE_LABELS   => GetLanguageLabels::class,
			self::UPLOAD_FLAG           => UploadFlag::class,
			self::CHECK_CATALOGUE_FRESHNESS => CheckCatalogueFreshness::class,
			self::SYNC_CATALOGUE            => SyncCatalogue::class,
			self::GET_ATE_LANGUAGES         => GetAteLanguages::class,
		];
	}
}
