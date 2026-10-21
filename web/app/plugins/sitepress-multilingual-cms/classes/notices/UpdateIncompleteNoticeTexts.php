<?php

namespace WPML\Notices;

class UpdateIncompleteNoticeTexts implements \WPML_Dependencies_Texts {

	const OLDER_COPY_LOGGED_OPTION = 'wpml_dependencies_older_copy_logged';

	public static function handTo( $dependencies, $log = null ) {
		try {
			$dependencies->set_texts( new self() );

			return true;
		} catch ( \Error $e ) {
			$file = (string) ( new \ReflectionClass( $dependencies ) )->getFileName();
			if ( get_option( self::OLDER_COPY_LOGGED_OPTION ) !== $file ) {
				update_option( self::OLDER_COPY_LOGGED_OPTION, $file, false );
				$message = sprintf(
					'WPML: the "WPML Update is Incomplete" notice shows the wording of an older copy of wpml-lib-dependencies (%s), loaded before core\'s, instead of core\'s translated texts; core needs 5.1.0 or newer (%s).',
					$file,
					$e->getMessage()
				);
				if ( null === $log ) {
					error_log( $message );
				} else {
					call_user_func( $log, $message );
				}
			}

			return false;
		}
	}

	public function get( $key ) {
		switch ( $key ) {
			case 'title':
				return __( 'WPML Update is Incomplete', 'sitepress' );

			case 'header_one':
				/* translators: %s: the name of the one WPML component that is up to date, e.g. "WPML Multilingual CMS". */
				return __( 'You are running updated %s, but the following component is not updated:', 'sitepress' );
			case 'header_many':
				/* translators: 1: the names of the up-to-date WPML components but the last, comma-separated; 2: the last one. */
				return __( 'You are running updated %1$s and %2$s, but the following components are not updated:', 'sitepress' );
			case 'header_none':
				return __( 'The following components are not updated:', 'sitepress' );

			case 'item_installed':
				/* translators: 1: the name of an outdated WPML component, e.g. "WPML Multilingual CMS"; 2: the version of it this site runs, e.g. "4.9.4". */
				return __( '%1$s: %2$s is installed', 'sitepress' );
			case 'item_needs':
				/* translators: 1: the name of a plugin that needs the outdated component, e.g. "WPML SEO"; 2: the lowest version of that component it accepts, e.g. "5.0.0". */
				return __( '%1$s needs %2$s or newer', 'sitepress' );
			case 'stays_off_one':
				/* translators: 1: the name of the one plugin that stays switched off, e.g. "WPML SEO"; 2: the name of the outdated WPML component it waits for. */
				return __( '%1$s stays off until %2$s is updated.', 'sitepress' );
			case 'stays_off_many':
				/* translators: 1: the names of the plugins that stay switched off but the last, comma-separated; 2: the last one; 3: the name of the outdated WPML component they wait for. */
				return __( '%1$s and %2$s stay off until %3$s is updated.', 'sitepress' );
			case 'stays_off_list_intro':
				return __( 'These plugins need a newer version and stay off until it is updated:', 'sitepress' );
			case 'stays_off_list_item':
				/* translators: 1: the name of a plugin that stays switched off, e.g. "WPML SEO"; 2: the lowest version of the outdated WPML component it accepts, e.g. "5.0.0". */
				return __( '%1$s (needs %2$s or newer)', 'sitepress' );

			case 'footer_intro':
				return __( 'Your site will not work as it should in this configuration.', 'sitepress' );
			case 'footer_update':
				/* translators: %s: a link whose text is "WPML → Activate & Update", pointing at that admin screen. */
				return __( 'Update all the components you use from %s.', 'sitepress' );
			case 'footer_update_link_text':
				return __( 'WPML → Activate & Update', 'sitepress' );
			case 'footer_register':
				/* translators: %s: a link whose text is "WPML.org account", pointing at the account's downloads page. */
				return __( 'You get updates from your %s, or automatically once you register WPML.', 'sitepress' );
			case 'account_link_text':
				return __( 'WPML.org account', 'sitepress' );

			case 'footer_no_installer_intro':
				return __( 'Your site will not work as it should in this configuration', 'sitepress' );
			case 'footer_no_installer_update':
				return __( 'Please update all components which you are using.', 'sitepress' );
			case 'footer_no_installer_register':
				/* translators: %s: a link whose text is "WPML.org account", pointing at the account's downloads page. */
				return __( 'For WPML components you can receive updates from your %s or automatically, after you register WPML.', 'sitepress' );
		}

		return '';
	}
}
