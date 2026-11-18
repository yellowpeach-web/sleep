<?php

namespace WPML\TranslationPolicy\Ptc;

class Words {

	public static function title(): string {
		/* translators: Title of the dialog shown before a user chooses PTC as who translates. PTC is the name of WPML's automatic translation. Keep "PTC" as it is. */
		return __( 'Translate with PTC?', 'sitepress' );
	}

	public static function send(): string {
		/* translators: In the dialog "Translate with PTC?". "+" is the icon that adds a translation. Keep "+" and "PTC" as they are. */
		return __( 'Clicking + sends the page to PTC. PTC translates it, then the editor opens on the translation.', 'sitepress' );
	}

	public static function update(): string {
		/* translators: In the dialog "Translate with PTC?". The update icon is the icon of a translation that needs an update. */
		return __( 'Clicking the update icon sends the changed texts again.', 'sitepress' );
	}

	public static function usesWords(): string {
		/* translators: In the dialog "Translate with PTC?". "Words" are the prepaid words of automatic translation. */
		return __( 'Each translation uses words from your WPML account.', 'sitepress' );
	}

	public static function wordsLeft( int $words ): string {
		/* translators: In the dialog "Translate with PTC?", after "Each translation uses words from your WPML account." %s: a number of words, for example "12,000". */
		return sprintf( _n( 'You have %s word left.', 'You have %s words left.', $words, 'sitepress' ), number_format_i18n( $words ) );
	}

	public static function translateEverything(): string {
		/* translators: In the dialog "Translate with PTC?". "Translate Everything" is the name of a WPML feature; keep it as it is translated elsewhere in WPML. */
		return __( 'To also send new content automatically, turn on Translate Everything.', 'sitepress' );
	}

	public static function usePtc(): string {
		/* translators: Button of the dialog "Translate with PTC?": confirm the choice. Keep "PTC" as it is. */
		return __( 'Use PTC', 'sitepress' );
	}

	public static function cancel(): string {
		/* translators: Button label that closes a dialog without doing anything, or stops what is going on, for example in the dialog "Translate with PTC?" (keep the previous choice). Verb, imperative, not the noun "a cancellation". */
		return __( 'Cancel', 'sitepress' );
	}

	public static function noAccount(): string {
		/* translators: In the dialog "Translate with PTC?", when the site's automatic translation account is not on. Keep "PTC" as it is. */
		return __( 'Automatic translation is off on this site. Turn it on before you translate with PTC.', 'sitepress' );
	}

	public static function turnOn(): string {
		/* translators: Button of the dialog "Translate with PTC?" when automatic translation is off: it opens the page where the user turns it on. */
		return __( 'Turn on automatic translation', 'sitepress' );
	}

	public static function loading(): string {
		/* translators: In the dialog "Translate with PTC?", while WPML reads the words left in the account. */
		return __( 'Reading your words…', 'sitepress' );
	}
}
