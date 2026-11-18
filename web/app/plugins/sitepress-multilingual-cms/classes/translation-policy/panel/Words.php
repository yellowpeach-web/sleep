<?php

namespace WPML\TranslationPolicy\Panel;

use WPML\TranslationPolicy\Policy\PolicyTable;

class Words {

	public static function methodsLabel(): string {
		/* translators: Heading and label of the choice of who writes the translations, you or PTC (WPML's automatic translation): in the Language box of the post editor and in the "Translation editor and method" section of WPML → Settings. */
		return __( 'Who translates', 'sitepress' );
	}

	public static function method( string $method ): string {
		if ( PolicyTable::METHOD_PTC === $method ) {
			/* translators: A choice of who translates, in the Language box of the post editor and in the "Who translates" dropdown of WPML → Settings. PTC is the name of WPML's automatic translation. Keep "PTC" as it is. */
			return __( 'PTC', 'sitepress' );
		}

		/* translators: A choice of who translates, in the Language box of the post editor and in the "Who translates" dropdown of WPML → Settings: the client, or the client's translators, write the translations. */
		return __( 'I translate', 'sitepress' );
	}

	public static function methodLine( string $method ): string {
		if ( PolicyTable::METHOD_PTC === $method ) {
			/* translators: One line under the choice "PTC" in the Language box of the post editor. */
			return __( 'Translates for you. You review.', 'sitepress' );
		}

		/* translators: One line under the choice "I translate" in the Language box of the post editor. */
		return __( 'You or your translators write it.', 'sitepress' );
	}

	public static function sameAsType( string $typeLabel ): string {
		/* translators: Grey line under a choice in the Language box of the post editor: this post follows the setting of its post type. %s: the plural name of the post type, for example "Posts". */
		return sprintf( __( 'Same as %s.', 'sitepress' ), $typeLabel );
	}

	public static function useTypeSetting( string $typeLabel ): string {
		/* translators: Link in the Language box of the post editor: this post stops using its own choice and follows its post type again. %s: the plural name of the post type, for example "Posts". */
		return sprintf( __( 'Use the %s setting', 'sitepress' ), $typeLabel );
	}

	public static function wpmlEditor( string $editor ): string {
		if ( PolicyTable::EDITOR_CTE === $editor ) {
			/* translators: The name of WPML's older translation editor: at the bottom of the Language box of the post editor, in the "Editor" dropdown of WPML → Settings, and in the table that compares the editors. */
			return __( 'Classic Translation Editor', 'sitepress' );
		}

		/* translators: The name of WPML's translation editor: at the bottom of the Language box of the post editor, in the "Editor" dropdown of WPML → Settings, and in the table that compares the editors. */
		return __( 'Advanced Translation Editor', 'sitepress' );
	}

	public static function useWpmlEditor( string $editor ): string {
		if ( PolicyTable::EDITOR_CTE === $editor ) {
			/* translators: Quiet link at the bottom of the Language box of the post editor: translate this post in WPML's older editor. */
			return __( 'Use the Classic Translation Editor', 'sitepress' );
		}

		/* translators: Link at the bottom of the Language box of the post editor: translate this post in WPML's translation editor, which WPML recommends. */
		return __( 'Use the Advanced Translation Editor (recommended)', 'sitepress' );
	}

	public static function wordPressEditor(): string {
		/* translators: The name of the standard WordPress editor, at the bottom of the Language box of the post editor. */
		return __( 'WordPress Editor', 'sitepress' );
	}

	public static function dashboardStatus( string $method, bool $isTranslateEverythingOn ): string {
		if ( PolicyTable::METHOD_PTC !== $method ) {
			/* translators: Status line in the Language box of the post editor: the user, or the user's translators, translate this post. */
			return __( 'You translate this post.', 'sitepress' );
		}

		if ( $isTranslateEverythingOn ) {
			/* translators: Status line in the Language box of the post editor. PTC is the name of WPML's automatic translation; keep "PTC" and "Translate Everything" as they are. */
			return __( 'PTC translates this post. Translate Everything sends it automatically.', 'sitepress' );
		}

		/* translators: Status line in the Language box of the post editor. PTC is the name of WPML's automatic translation; keep "PTC" as it is. "+" is the icon that starts a translation. */
		return __( 'PTC translates this post when you click +.', 'sitepress' );
	}

	public static function translateHeading( string $method ): string {
		if ( PolicyTable::METHOD_PTC === $method ) {
			/* translators: Heading of the list of languages without a translation, in the Language box of the post editor. PTC is the name of WPML's automatic translation. Keep "PTC" as it is. */
			return __( 'Translate with PTC', 'sitepress' );
		}

		/* translators: Verb, imperative: an option in the dropdown that says what happens to a field, the heading of the column of the post editing screen where a translation is started, and the heading of the list of languages without a translation in the post editor's Language box. */
		return __( 'Translate', 'sitepress' );
	}

	public static function translateTo( string $method, string $language ): string {
		if ( PolicyTable::METHOD_PTC === $method ) {
			/* translators: Label of the "+" of one language in the Language box of the post editor. %s: the language, for example "French". PTC is the name of WPML's automatic translation. Keep "PTC" as it is. */
			return sprintf( __( 'Translate to %s with PTC', 'sitepress' ), $language );
		}

		/* translators: Label of the "+" of one language in the Language box of the post editor. %s: the language, for example "French". */
		return sprintf( __( 'Translate to %s yourself', 'sitepress' ), $language );
	}

	public static function updateTranslation( string $method, string $language ): string {
		if ( PolicyTable::METHOD_PTC === $method ) {
			/* translators: Label of the icon of a translation whose original changed, in the list of content and the Language box of the post editor. %s: the language, for example "French". PTC is the name of WPML's automatic translation. Keep "PTC" as it is. */
			return sprintf( __( 'Update the %s translation with PTC', 'sitepress' ), $language );
		}

		/* translators: Label of the icon of a translation whose original changed, in the list of content and the Language box of the post editor. %s: the language, for example "French". */
		return sprintf( __( 'Update the %s translation yourself', 'sitepress' ), $language );
	}

	public static function lockedByPlugin(): string {
		/* translators: Shown under a choice the client cannot change because a plugin decides it, in the Language box of the post editor and under a dropdown in WPML → Settings. */
		return __( 'A plugin locks this choice, so you cannot change it here.', 'sitepress' );
	}

	public static function notSaved(): string {
		/* translators: Error message in the Language box of the post editor, when WPML could not save a choice. */
		return __( 'WPML could not save your choice. Reload the page and try again.', 'sitepress' );
	}
}
