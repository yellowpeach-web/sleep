<?php

namespace ACFML\Helper;

interface ContentType {

	public function getInternalPostType();

	public function getLabelTranslationsPackageSlug();

	public function getObjectSlug( $internalPostId );

	public function getWpmlSyncOptionKey();

	public function getEditorScreenSlug();

	public function isEditorScreen();

	public function isListingScreen();

	public function getTranslationInfoLabel();

	public function getLabelsTranslationInfoLabel();

	public function getTranslationSettingsUrl();

}
