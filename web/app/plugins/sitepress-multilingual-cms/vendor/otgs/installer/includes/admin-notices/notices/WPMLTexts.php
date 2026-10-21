<?php

namespace OTGS\Installer\AdminNotices\Notices;

class WPMLTexts extends Texts {

	protected static $repo = 'wpml';
	protected static $product = 'WPML';
	protected static $productURL = 'WPML.org';
	protected static $apiHost = 'wpml.org';
	protected static $communicationDetailsLink = '/admin.php?page=otgs-installer-support';
	protected static $supportLink = 'https://app.wpml.org/support';
	protected static $publishLink = 'https://app.wpml.org/account/sites?publish=';
	protected static $learnMoreDevKeysLink = 'https://wpml.org/troubleshooting/site-keys/';
	protected static $renewPath = 'account/subscription';

	const WPML_INSTALLER_LOG_LINK = '/admin.php?page=sitepress-multilingual-cms/menu/support.php&tool=installer-log';

	protected static function communicationDetailsLink() {
		return static::wpmlHasSupportTools() ? self::WPML_INSTALLER_LOG_LINK : static::$communicationDetailsLink;
	}

	protected static function wpmlHasSupportTools() {
		return defined( 'ICL_SITEPRESS_VERSION' ) && version_compare( ICL_SITEPRESS_VERSION, '5.0.0', '>=' );
	}
}
