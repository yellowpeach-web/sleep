<?php

namespace WPML\TranslationPolicy\Settings;

use WPML\TranslationPolicy\Policy\Constraints;
use WPML\TranslationPolicy\Policy\PolicyTable;

class Words {

	public static function title(): string {
		/* translators: Name of a section of WPML → Settings, after the switch to the translation policy: its heading, its entry in the settings list and its entry in the settings search. The client chooses which translation editor opens and who translates (the method). */
		return __( 'Translation editor and method', 'sitepress' );
	}

	public static function subtitle(): string {
		/* translators: Subtitle of the "Translation editor and method" section of WPML → Settings. */
		return __( 'Choose the editor that opens when you translate, and who translates. Your choice applies to the whole site. You can change it for a post type below.', 'sitepress' );
	}

	public static function searchDescription(): string {
		/* translators: Description of the "Translation editor and method" section in the search list of WPML → Settings. */
		return __( 'The translation editor and who translates, for the whole site and for each post type.', 'sitepress' );
	}

	public static function editorStep(): string {
		/* translators: Heading of the first step in the "Translation editor and method" section of WPML → Settings, above the table that compares the Advanced and the Classic Translation Editor. */
		return __( 'Editor for the whole site', 'sitepress' );
	}

	public static function methodStep(): string {
		/* translators: Heading and label of the choice of who writes the translations, you or PTC (WPML's automatic translation): in the Language box of the post editor and in the "Translation editor and method" section of WPML → Settings. */
		return __( 'Who translates', 'sitepress' );
	}

	public static function typesStep(): string {
		/* translators: Heading of the third step in the "Translation editor and method" section of WPML → Settings. It opens a table where the client can choose another editor or method for one post type (Posts, Pages, Products...). */
		return __( 'Change it for a post type (optional)', 'sitepress' );
	}

	public static function whoWritesRow(): string {
		/* translators: Row heading in the table of the "Who translates" step of WPML → Settings. The columns are the two choices: "I translate" and "PTC" (WPML's automatic translation). */
		return __( 'Who writes the translation', 'sitepress' );
	}

	public static function whoWritesSelf(): string {
		/* translators: Cell of the "I translate" column, in the row "Who writes the translation" of the "Who translates" step of WPML → Settings. */
		return __( 'You or your translators', 'sitepress' );
	}

	public static function creditsRow(): string {
		/* translators: Row heading in the table of the "Who translates" step of WPML → Settings. PTC (WPML's automatic translation) uses the site's automatic translation credits; "I translate" does not. */
		return __( 'Uses automatic translation credits', 'sitepress' );
	}

	public static function newContentRow(): string {
		/* translators: Row heading in the table of the "Who translates" step of WPML → Settings: WPML sends new content to translation without a click. */
		return __( 'New content is sent automatically', 'sitepress' );
	}

	public static function newContentWithTranslateEverything( string $linkStart, string $linkEnd ): string {
		/* translators: Cell of the "PTC" column, in the row "New content is sent automatically" of the "Who translates" step of WPML → Settings: this happens only when Translate Everything is on. Translate Everything is a separate WPML setting. %1$s and %2$s: the start and the end of a link to Translate Everything. */
		return sprintf( __( 'With %1$sTranslate Everything%2$s', 'sitepress' ), $linkStart, $linkEnd );
	}

	public static function yes(): string {
		/* translators: Meaning that a setting is on or that a row applies: an option in a dropdown, the value in a table cell, and the screen reader name of the tick in the table of the "Who translates" step of WPML → Settings. */
		return __( 'Yes', 'sitepress' );
	}

	public static function ptcTranslateEverythingHint( string $linkStart, string $linkEnd ): string {
		/* translators: Line under the table of the "Who translates" step of WPML → Settings. "+" and "the pencil" are the icons that open a translation in the post list. Translate Everything is a separate WPML setting that sends new content to automatic translation. %1$s and %2$s: the start and the end of a link to Translate Everything. */
		return sprintf( __( 'Clicking + or the pencil sends the translation to PTC. To also send new content automatically, turn on %1$sTranslate Everything%2$s.', 'sitepress' ), $linkStart, $linkEnd );
	}

	public static function automaticPill(): string {
		/* translators: Meaning that nobody set it by hand: the value of a row on the Support screen (WPML picks it by itself), and the small label above the "PTC" column of the "Who translates" table (PTC translates automatically). Adjective. */
		return __( 'Automatic', 'sitepress' );
	}

	public static function siteEditorNotInTable( string $editor ): string {
		/* translators: Help text under the table of translation editors in WPML → Settings, when the whole site uses an editor the table does not show. %s: that editor, for example "WordPress editor or page builder". */
		return sprintf( __( 'The whole site uses the %s now. Choose an editor above to change it.', 'sitepress' ), $editor );
	}

	public static function typesIntro(): string {
		/* translators: Help text at the top of the post type table in the "Translation editor and method" section of WPML → Settings. Some values in the table are grey: those come from the choice for the whole site. */
		return __( 'A post type follows the whole site unless you choose something else for it. Values in grey follow the site.', 'sitepress' );
	}

	public static function typesSummaryAll(): string {
		/* translators: Summary next to the step "Change it for a post type (optional)" in WPML → Settings, when no post type has a choice of its own. */
		return __( 'All post types follow the site', 'sitepress' );
	}

	public static function typesSummaryOwn( int $count ): string {
		/* translators: Summary next to the step "Change it for a post type (optional)" in WPML → Settings. %s: the number of post types that do not follow the choice for the whole site. */
		return sprintf( _n( '%s post type has its own choice', '%s post types have their own choice', $count, 'sitepress' ), number_format_i18n( $count ) );
	}

	public static function typesSummaryLocked( int $count ): string {
		/* translators: Second part of the summary next to the step "Change it for a post type (optional)" in WPML → Settings. %s: the number of post types whose choice a plugin locks. */
		return sprintf( _n( '%s locked by a plugin', '%s locked by a plugin', $count, 'sitepress' ), number_format_i18n( $count ) );
	}

	public static function typesSummaryBoth( string $own, string $locked ): string {
		/* translators: Summary next to the step "Change it for a post type (optional)" in WPML → Settings. %1$s: for example "3 post types have their own choice". %2$s: for example "1 locked by a plugin". */
		return sprintf( __( '%1$s · %2$s', 'sitepress' ), $own, $locked );
	}

	public static function showAll( int $count ): string {
		/* translators: Button under the post type table in WPML → Settings. It shows the post types that follow the choice for the whole site. %s: their number. */
		return sprintf( _n( 'Show all (%s more follows the site)', 'Show all (%s more follow the site)', $count, 'sitepress' ), number_format_i18n( $count ) );
	}

	public static function postTypeColumn(): string {
		/* translators: Column heading in the table of post types in the "Translation editor and method" section of WPML → Settings. */
		return __( 'Post type', 'sitepress' );
	}

	public static function editorColumn(): string {
		/* translators: Column heading and label of the dropdown that chooses which editor opens when you translate, in the "Translation editor and method" section of WPML → Settings. */
		return __( 'Editor', 'sitepress' );
	}

	public static function methodColumn(): string {
		/* translators: Heading and label of the choice of who writes the translations, you or PTC (WPML's automatic translation): in the Language box of the post editor and in the "Translation editor and method" section of WPML → Settings. */
		return __( 'Who translates', 'sitepress' );
	}

	public static function editor( string $editor ): string {
		switch ( $editor ) {
			case PolicyTable::EDITOR_NATIVE:
				/* translators: Option in the "Editor" dropdown of WPML → Settings: translations open in the WordPress editor, or in the page builder the page uses. */
				return __( 'WordPress editor or page builder', 'sitepress' );
			case PolicyTable::EDITOR_CTE:
				/* translators: The name of WPML's older translation editor: at the bottom of the Language box of the post editor, in the "Editor" dropdown of WPML → Settings, and in the table that compares the editors. */
				return __( 'Classic Translation Editor', 'sitepress' );
			case PolicyTable::EDITOR_ATE:
				/* translators: The name of WPML's translation editor: at the bottom of the Language box of the post editor, in the "Editor" dropdown of WPML → Settings, and in the table that compares the editors. */
				return __( 'Advanced Translation Editor', 'sitepress' );
			case PolicyTable::EDITOR_WPML:
				/* translators: Option in the "Editor" dropdown of WPML → Settings, shown only where it is already set: the Classic or the Advanced Translation Editor, whichever the whole site uses. */
				return __( 'WPML editor (follows the site)', 'sitepress' );
		}

		return $editor;
	}

	public static function method( string $method ): string {
		switch ( $method ) {
			case PolicyTable::METHOD_SELF:
				/* translators: A choice of who translates, in the Language box of the post editor and in the "Who translates" dropdown of WPML → Settings: the client, or the client's translators, write the translations. */
				return __( 'I translate', 'sitepress' );
			case PolicyTable::METHOD_PTC:
				/* translators: A choice of who translates, in the Language box of the post editor and in the "Who translates" dropdown of WPML → Settings. PTC is the name of WPML's automatic translation. Keep "PTC" as it is. */
				return __( 'PTC', 'sitepress' );
		}

		return $method;
	}

	public static function sameAsSite( string $siteValue ): string {
		/* translators: First option in the "Editor" and "Who translates" dropdowns of a post type in WPML → Settings: the post type uses the choice of the whole site. %s: that choice, for example "Advanced Translation Editor" or "I translate". */
		return sprintf( __( 'Same as the site (%s)', 'sitepress' ), $siteValue );
	}

	public static function siteDefault(): string {
		/* translators: Help text under a dropdown in WPML → Settings: the value shown is WPML's default, because nobody chose a value yet. */
		return __( 'This is the default. Nobody chose it yet.', 'sitepress' );
	}

	public static function earlierDefault(): string {
		/* translators: Help text under a dropdown in WPML → Settings: the value shown is a default that comes from the settings the site had before this version of WPML. */
		return __( 'This is the default, from your earlier settings.', 'sitepress' );
	}

	public static function pluginDefault(): string {
		/* translators: Help text under a dropdown in WPML → Settings: a plugin (for example WooCommerce Multilingual) set the value shown as the default. The client can change it. */
		return __( 'A plugin set this as the default. You can change it.', 'sitepress' );
	}

	public static function lock( string $source, string $reason ): string {
		$label = Constraints::label( $source, $reason, 0 );
		if ( '' !== $label ) {
			return $label;
		}

		/* translators: Shown under a choice the client cannot change because a plugin decides it, in the Language box of the post editor and under a dropdown in WPML → Settings. */
		return __( 'A plugin locks this choice, so you cannot change it here.', 'sitepress' );
	}

	public static function save(): string {
		/* translators: Button label that keeps what was entered, for example in the Translation delivery box's "Settings for support" and in the "Translation editor and method" section of WPML → Settings. Verb, imperative. */
		return __( 'Save', 'sitepress' );
	}

	public static function saved(): string {
		/* translators: Short message next to a choice in the "Translation editor and method" section of WPML → Settings, after WPML saved it. Each change saves at once. */
		return __( 'Saved', 'sitepress' );
	}

	public static function saving(): string {
		/* translators: Message while WPML saves the choices in the "Translation editor and method" section of WPML → Settings. */
		return __( 'Saving…', 'sitepress' );
	}

	public static function notSaved(): string {
		/* translators: Error message in the "Translation editor and method" section of WPML → Settings, when WPML could not save the choices. */
		return __( 'WPML could not save your choices. Reload the page and try again.', 'sitepress' );
	}

	public static function expired(): string {
		/* translators: Error message in the "Translation editor and method" section of WPML → Settings, when the security token of the page is too old. */
		return __( 'This page is too old to save. Reload the page and try again.', 'sitepress' );
	}

	public static function notAllowed(): string {
		/* translators: Error message in the "Translation editor and method" section of WPML → Settings, when the user may not change these settings. */
		return __( 'You are not allowed to change these choices. Ask an administrator.', 'sitepress' );
	}

	public static function lockRefused( string $lock ): string {
		/* translators: Error message in the "Translation editor and method" section of WPML → Settings, when the client tries to change a choice a plugin locks. %s: the plugin's own explanation of the lock, a full sentence. */
		return sprintf( __( 'WPML did not save your choices, because a plugin locks one of them. %s', 'sitepress' ), $lock );
	}
}
