<?php

namespace WPML\EditHistory;

class PanelPage {

	private $languageName;

	public function __construct( callable $languageName ) {
		$this->languageName = $languageName;
	}

	public function render( array $items, string $result ) {
		echo '<div class="wrap wpml-translation-updates">';
		/* translators: Menu item in the WPML menu, and the heading of its screen: the history of the updates applied to translations the client had edited. */
		echo '<h1>' . esc_html__( 'Translation updates', 'sitepress' ) . '</h1>';
		$this->notice( $result );
		/* translators: Introduction at the top of the "Translation updates" screen, first line. */
		echo '<p>' . esc_html__( 'This is the history of updates to translations that you edited. WPML applies every update and saves your version first.', 'sitepress' ) . '</p>';
		/* translators: Introduction at the top of the "Translation updates" screen, second line. "Revert to previous" is the name of a button on this screen. */
		echo '<p>' . esc_html__( 'You do not need to do anything here. If you prefer your version, use Revert to previous.', 'sitepress' ) . '</p>';

		if ( ! $items ) {
			/* translators: Shown on the "Translation updates" screen when the history is empty. */
			echo '<p>' . esc_html__( 'No updates yet.', 'sitepress' ) . '</p>';
		}
		foreach ( $items as $item ) {
			$this->item( $item );
		}
		echo '</div>';
	}

	private function notice( string $result ) {
		$notices = [
			/* translators: Confirmation on the "Translation updates" screen after the client's version of a translation was put back. */
			Revert::DONE   => [ 'success', 'status', __( 'Your version of the translation is back.', 'sitepress' ) ],
			/* translators: Shown on the "Translation updates" screen when a Revert was refused because the translation changed after the screen was opened. */
			Revert::STALE  => [ 'warning', 'alert', __( 'This translation changed after you opened this screen. WPML did not revert it. The list shows it as it is now.', 'sitepress' ) ],
			/* translators: Shown on the "Translation updates" screen when a Revert was refused because another user has the page open in the editor. */
			Revert::LOCKED => [ 'warning', 'alert', __( 'Another user is editing this page. WPML did not revert it. Try again when they finish.', 'sitepress' ) ],
			/* translators: Shown on the "Translation updates" screen when a Revert failed. The page was left as it was. */
			Revert::FAILED => [ 'error', 'alert', __( 'WPML could not revert this translation. The translation did not change. Try again.', 'sitepress' ) ],
		];
		if ( ! isset( $notices[ $result ] ) ) {
			return;
		}

		[ $type, $role, $text ] = $notices[ $result ];
		echo '<div class="notice notice-' . esc_attr( $type ) . '" role="' . esc_attr( $role ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}

	private function item( PanelItem $item ) {
		$language = ( $this->languageName )( $item->language );
		$original = (string) get_post_field( 'post_title', $item->originalId );

		echo '<div class="wpml-edit-update" id="' . esc_attr( self::anchor( $item->translationId ) ) . '" data-state="' . esc_attr( $item->current ? 'current' : 'stale' ) . '">';
		echo '<h2>' . esc_html( (string) get_post_field( 'post_title', $item->translationId ) ) . '</h2>';
		/* translators: Line under a page's title on the "Translation updates" screen. %1$s: the language name, %2$s: the title of the page in the original language. */
		echo '<p>' . esc_html( sprintf( __( '%1$s translation of "%2$s"', 'sitepress' ), $language, $original ) ) . '</p>';

		if ( Delivery::REASON_UNCLEAR === $item->reason ) {
			/* translators: Mark on an update in the "Translation updates" screen: ATE was not sure what the client wanted. Adjective, one word. */
			echo '<p class="wpml-edit-update-unclear"><strong>' . esc_html__( 'Unclear', 'sitepress' ) . '</strong></p>';
		}
		echo '<p>' . esc_html( self::reason( $item->reason ) ) . '</p>';
		$this->report( $item );
		$this->changes( $item );

		if ( $item->current ) {
			$this->revertForm( $item, $language, $original );
		} else {
			/* translators: Shown on the "Translation updates" screen for an update that can no longer be reverted, because the translation changed after it (for example, the client edited it again). */
			echo '<p>' . esc_html__( 'The translation changed after this update. You can no longer revert it.', 'sitepress' ) . '</p>';
		}
		echo '</div>';
	}

	private function report( PanelItem $item ) {
		echo '<ul>';
		if ( $item->appliedAt ) {
			/* translators: Line on the "Translation updates" screen. %s: the date and time WPML applied the update. */
			echo '<li>' . esc_html( sprintf( __( 'Updated on %s.', 'sitepress' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->appliedAt ) ) ) . '</li>';
		}
		/* translators: Line on the "Translation updates" screen: WPML kept the client's version of the page before it applied the update. */
		echo '<li>' . esc_html__( 'WPML saved your version before the update.', 'sitepress' ) . '</li>';
		if ( $item->removed > 0 ) {
			/* translators: Line on the "Translation updates" screen. %s: how many parts of the page (paragraphs, images, groups) the update left out. */
			echo '<li>' . esc_html( sprintf( _n( 'The update left out %s part of the page.', 'The update left out %s parts of the page.', $item->removed, 'sitepress' ), number_format_i18n( $item->removed ) ) ) . '</li>';
		}
		echo '</ul>';
	}

	private function changes( PanelItem $item ) {
		if ( ! $item->rows ) {
			return;
		}

		echo '<table class="widefat striped"><thead><tr>';
		/* translators: Column heading in the table of changes on the "Translation updates" screen: which part of the page (title, text, slug). */
		echo '<th scope="col">' . esc_html__( 'Part', 'sitepress' ) . '</th>';
		/* translators: Column heading in the table of changes on the "Translation updates" screen: the client's own text, saved before the update. */
		echo '<th scope="col">' . esc_html__( 'Your version', 'sitepress' ) . '</th>';
		/* translators: Column heading in the table of changes on the "Translation updates" screen: the text of the update, which is on the page now. */
		echo '<th scope="col">' . esc_html__( 'New translation', 'sitepress' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $item->rows as $row ) {
			echo '<tr><td>' . esc_html( self::part( $row['key'] ) ) . '</td>';
			echo '<td lang="' . esc_attr( $item->language ) . '">' . esc_html( $row['client'] ?? self::absent( true ) ) . '</td>';
			echo '<td lang="' . esc_attr( $item->language ) . '">' . esc_html( $row['ate'] ?? self::absent( false ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function revertForm( PanelItem $item, string $language, string $original ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( PanelHooks::ACTION ) . '" />';
		echo '<input type="hidden" name="translation" value="' . esc_attr( (string) $item->translationId ) . '" />';
		echo '<input type="hidden" name="version" value="' . esc_attr( $item->version ) . '" />';
		wp_nonce_field( PanelHooks::ACTION );
		/* translators: Accessible label (screen readers) of the "Revert to previous" button. %1$s: the language name, %2$s: the title of the page in the original language. */
		$label = sprintf( __( 'Revert to previous: %1$s translation of "%2$s"', 'sitepress' ), $language, $original );
		/* translators: Button label on the "Translation updates" screen: verb, imperative. Puts back the client's own version of the translation, saved before ATE's update. */
		echo '<p><button type="submit" class="button button-primary wpml-button base-btn" aria-label="' . esc_attr( $label ) . '">' . esc_html__( 'Revert to previous', 'sitepress' ) . '</button></p>';
		echo '</form>';
	}

	public static function anchor( int $translationId ): string {
		return 'wpml-edit-update-' . $translationId;
	}

	private static function reason( string $reason ): string {
		switch ( $reason ) {
			case Delivery::REASON_UNCLEAR:
				/* translators: History entry on the "Translation updates" screen. ATE is the automatic translation service. */
				return __( 'ATE updated this translation after you edited it. ATE was not sure if you wanted to keep your edits.', 'sitepress' );
			case Delivery::REASON_OVERWRITE:
				/* translators: History entry on the "Translation updates" screen. ATE is the automatic translation service. */
				return __( 'ATE updated this translation after you edited it.', 'sitepress' );
			case Delivery::REASON_LATE:
				/* translators: History entry on the "Translation updates" screen: a translator or reviewer finished the job after the client edited the page. */
				return __( 'You edited this translation while it was in a translation job. WPML applied the finished job after your edits.', 'sitepress' );
			case FromScratch::SOURCE:
				/* translators: History entry on the "Translation updates" screen. "Translate from scratch" is the name of an action on the Translations Dashboard. */
				return __( 'Someone used Translate from scratch. The new translation replaced your edits.', 'sitepress' );
			default:
				/* translators: History entry on the "Translation updates" screen. */
				return __( 'A new translation arrived after you edited this one.', 'sitepress' );
		}
	}

	private static function part( string $key ): string {
		switch ( $key ) {
			case '':
				/* translators: In the table of changes on the "Translation updates" screen: a text of the page's content (a paragraph, a heading). Noun. */
				return __( 'Text', 'sitepress' );
			case 'title':
				/* translators: Column heading and field label for the title of a piece of content: in the table of translation jobs, in the translation editor, and in the table of changes on the "Translation updates" screen. Noun. */
				return __( 'Title', 'sitepress' );
			case 'URL':
				/* translators: Label for the part of an address that stands for a page or a term, after the site's address: in the table of changes on the "Translation updates" screen and in the term translation dialog. Noun. */
				return __( 'Slug', 'sitepress' );
			case 'excerpt':
				/* translators: Label for the short summary of a post: a field in the translation editor, and a row in the table of changes on the "Translation updates" screen. Noun. */
				return __( 'Excerpt', 'sitepress' );
			default:
				/* translators: In the table of changes on the "Translation updates" screen: a custom field of the page. Noun. */
				return __( 'Custom field', 'sitepress' );
		}
	}

	private static function absent( bool $client ): string {
		return $client
			/* translators: In the table of changes on the "Translation updates" screen, under "Your version": this part is only in the new translation. */
			? __( '(not in your version)', 'sitepress' )
			/* translators: In the table of changes on the "Translation updates" screen, under "New translation": this part is only in the client's version, and Revert to previous brings it back. */
			: __( '(not in the new translation. Revert to previous brings it back.)', 'sitepress' );
	}
}
