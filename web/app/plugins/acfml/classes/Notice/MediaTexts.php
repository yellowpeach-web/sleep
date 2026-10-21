<?php

namespace ACFML\Notice;

use ACFML\Helper\MediaFields;
use ACFML\Helper\MediaTranslation;

class MediaTexts implements \IWPML_Backend_Action {

	private const NOTICE_PRIORITY = 9;
	private const NOTICE_GROUP    = 'acfml';
	private const NOTICE_ID       = 'media-texts-disabled';

	private const SCRIPT_JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

	public function add_hooks() {
		add_action( 'admin_notices', [ $this, 'displayClassicNotice' ], self::NOTICE_PRIORITY );
		add_action( 'admin_head', [ $this, 'displayBlockEditorNotice' ] );
	}

	public function displayClassicNotice() {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		$notices = wpml_get_admin_notices();
		if ( ! $screen->is_block_editor() && self::shouldDisplayNotice() ) {
			$this->createClassicNotice( $notices, $screen->id, self::settingsUrl() );
		} else {
			$notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );
		}
	}

	public function displayBlockEditorNotice() {
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() || ! self::shouldDisplayNotice() ) {
			return;
		}

		$actions = [
			[
				'url'   => self::settingsUrl(),
				/* translators: Button in the admin notice about untranslated media texts; it opens WPML's Media Translation settings. Verb phrase, imperative. */
				'label' => __( 'Go to Media Translation settings', 'acfml' ),
			],
		];
		?>
		<script type="text/javascript">
			(function() {
				wp.data.dispatch("core/notices").createNotice(
					'info',
					<?php echo wp_json_encode( $this->explanation(), self::SCRIPT_JSON_FLAGS );  ?>,
					{
						id: <?php echo wp_json_encode( self::NOTICE_ID, self::SCRIPT_JSON_FLAGS );  ?>,
						isDismissible: true,
						actions: <?php echo wp_json_encode( $actions, self::SCRIPT_JSON_FLAGS );  ?>
					}
				);
			})();
		</script>
		<?php
	}

	public static function shouldDisplayNotice() : bool {
		$postId = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

		return $postId
			&& ! MediaTranslation::isEnabled()
			&& MediaFields::hasValues( $postId )
			&& '' !== self::settingsUrl();
	}

	private static function settingsUrl() : string {
		if ( '' === (string) menu_page_url( 'wpml-media', false ) ) {
			return '';
		}

		return admin_url( 'admin.php?page=tm/menu/main.php&tab=media' );
	}

	private function explanation() : string {
		/* translators: Body of the admin notice about untranslated media texts. The quoted text is the name of a WPML setting and is translated the same way there. */
		return __( 'This content has ACF image, gallery, or file fields, but the alt text, caption, and title of their media will not be sent for translation. To include them, turn on "Translate Media Library texts when translating content" in WPML Media Translation settings.', 'acfml' );
	}

	private function createClassicNotice( $notices, $screenId, $settingsUrl ) {
		/* translators: Heading of the admin notice about untranslated media texts. */
		$text  = '<h2><i class="otgs-ico-wpml"></i>&nbsp; ' . esc_html__( 'Media texts are not being translated', 'acfml' ) . '</h2>';
		$text .= '<p>' . esc_html( $this->explanation() ) . '</p>';
		$text .= sprintf(
			/* translators: The placeholders are replaced by an HTML link pointing to the WPML Media settings page. */
			esc_html__( '%1$sGo to Media Translation settings%2$s', 'acfml' ),
			'<a href="' . esc_url( $settingsUrl ) . '" class="button">',
			'</a>'
		);

		$notice = $notices->create_notice( self::NOTICE_ID, $text, self::NOTICE_GROUP );
		$notice->set_dismissible( true );
		$notice->set_css_class_types( [ 'info' ] );
		$notice->set_restrict_to_screen_ids( [ $screenId ] );
		$notices->add_notice( $notice );
	}
}
