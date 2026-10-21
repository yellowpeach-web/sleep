<?php

namespace ACFML\Post;

use ACFML\Notice\Links;
use ACFML\Tools\AdminUrl;
use WPML\API\Sanitize;
use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use ACFML\FieldGroup\GroupsOnPost;
use ACFML\FieldGroup\Mode;

class MixedFieldGroupModesHooks implements \IWPML_Backend_Action {

	const MAX_LISTED_FIELD_GROUPS = 3;

	const SCRIPT_JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

	private const NOTICE_PRIORITY = 9;
	private const NOTICE_GROUP    = 'acfml';
	private const NOTICE_ID       = 'acfml-post-edit-translation-notice';

	public function add_hooks() {
		if ( self::shouldDisplayNotice() ) {
			Hooks::onAction( 'admin_notices', self::NOTICE_PRIORITY )->then( [ $this, 'displayWarningClassic' ] );
			Hooks::onAction( 'admin_head' )->then( [ $this, 'displayWarningOnGutenberg' ] );
		}
	}

	public static function shouldDisplayNotice() {
		if ( ! \WPML_ACF::is_acf_active() ) {
			return false;
		}

		global $pagenow;

		$isPostEditScreen = in_array( $pagenow, [ 'post.php', 'post-new.php' ], true );
		if ( ! $isPostEditScreen ) {
			return false;
		}

		$fieldGroups = self::getFieldGroups();

		return count( $fieldGroups ) > 1
			&& Mode::MIXED === Mode::getForFieldGroups( $fieldGroups )
			&& wpml_collect( $fieldGroups )->contains( fn( $fieldGroup ) => Mode::LOCALIZATION === Mode::getMode( $fieldGroup ) );
	}

	private static function getFieldGroups() {
		$postId   = isset( $_GET['post'] ) ? (int) $_GET['post'] : null;
		$postType = isset( $_GET['post_type'] ) ? Sanitize::string( $_GET['post_type'] ) : 'post';

		return $postId
			? GroupsOnPost::filter( (array) acf_get_field_groups(), $postId )
			: acf_get_field_groups( [ 'post_type' => $postType ] );
	}

	private static function getFieldGroupModes() {
		return wpml_collect( self::getFieldGroups() )
			->map( fn( $fieldGroup ) => [
				'title'     => Obj::propOr( '', 'title', $fieldGroup ),
				'modeLabel' => Mode::getLabel( Mode::getMode( $fieldGroup ) ),
				'editLink'  => (string) get_edit_post_link( Obj::prop( 'ID', $fieldGroup ), 'raw' ),
			] )
			->values()
			->toArray();
	}

	public function displayWarningClassic() {
		$screen = get_current_screen();
		if ( ! $screen || ! function_exists( 'wpml_get_admin_notices' ) ) {
			return;
		}

		$notices = wpml_get_admin_notices();

		if ( $screen->is_block_editor() ) {
			$notices->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );

			return;
		}

		$notice = $notices->create_notice( self::NOTICE_ID, self::getClassicMessage(), self::NOTICE_GROUP );
		$notice->set_dismissible( true );
		$notice->set_css_class_types( [ 'notice-warning' ] );
		$notice->add_display_callback( [ self::class, 'shouldDisplayNotice' ] );
		$notices->add_notice( $notice );
	}

	private static function getClassicMessage() {
		$intro = sprintf(
			/* translators: %1$s: opening <a> tag, %2$s: closing </a> tag. */
			__( 'You need to %1$stranslate this post manually%2$s because the field groups attached to it use different translation options:', 'acfml' ),
			'<a href="' . esc_url( self::getNoticeLink() ) . '" class="wpml-external-link" target="_blank" rel="noopener noreferrer">',
			'</a>'
		);

		$groups = self::getFieldGroupModes();
		$shown  = array_slice( $groups, 0, self::MAX_LISTED_FIELD_GROUPS );

		$items = wpml_collect( $shown )
			->map( fn( $group ) => sprintf(
				'<li><a href="%1$s">%2$s</a>: %3$s</li>',
				esc_url( $group['editLink'] ),
				esc_html( $group['title'] ),
				esc_html( $group['modeLabel'] )
			) )
			->implode( '' );

		$remaining = count( $groups ) - count( $shown );
		if ( $remaining > 0 ) {
			$items .= sprintf(
				'<li>%1$s <a href="%2$s">%3$s</a></li>',
				/* translators: %d: number of additional field groups not listed. */
				esc_html( sprintf( __( 'and %d more.', 'acfml' ), $remaining ) ),
				esc_url( AdminUrl::getFieldGroupsList() ),
				/* translators: Link and button that open the ACF field-groups list, in the notice about field groups set up differently from one another. Verb phrase, imperative. */
				esc_html__( 'Show all field groups', 'acfml' )
			);
		}

		return $intro . '<ul>' . $items . '</ul>';
	}

	public function displayWarningOnGutenberg() {
		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}

		$groups = self::getFieldGroupModes();
		?>
		<script type="text/javascript">
			(function() {
				wp.data.dispatch("core/notices").createNotice(
					'warning',
					<?php echo wp_json_encode( self::getBlockEditorContent( $groups ), self::SCRIPT_JSON_FLAGS );  ?>,
					{
						id: 'acfml_post_edit_translation_notice',
						isDismissible: true,
						actions: <?php echo wp_json_encode( self::getBlockEditorActions( $groups ), self::SCRIPT_JSON_FLAGS );  ?>
					}
				);
			})();
		</script>
		<?php
	}

	private static function getBlockEditorContent( array $groups ) {
		$shown = array_slice( $groups, 0, self::MAX_LISTED_FIELD_GROUPS );

		$list = wpml_collect( $shown )
			->map( fn( $group ) => sprintf( '%s (%s)', $group['title'], $group['modeLabel'] ) )
			->implode( ', ' );

		$remaining = count( $groups ) - count( $shown );
		if ( $remaining > 0 ) {
			/* translators: %d: number of additional field groups not listed. */
			$list .= ', ' . sprintf( __( 'and %d more', 'acfml' ), $remaining );
		}

		return sprintf(
			/* translators: %s: comma-separated list of field groups with their translation options. */
			__( 'You need to translate this post manually because the field groups attached to it use different translation options: %s.', 'acfml' ),
			$list
		);
	}

	private static function getBlockEditorActions( array $groups ) {
		$shown = array_slice( $groups, 0, self::MAX_LISTED_FIELD_GROUPS );

		$actions = wpml_collect( $shown )
			->map( fn( $group ) => [
				'url'   => $group['editLink'],
				/* translators: %s: field group name. */
				'label' => sprintf( __( 'Edit %s', 'acfml' ), $group['title'] ),
			] )
			->values()
			->toArray();

		if ( count( $groups ) > count( $shown ) ) {
			$actions[] = [
				'url'   => AdminUrl::getFieldGroupsList(),
				/* translators: Link and button that open the ACF field-groups list, in the notice about field groups set up differently from one another. Verb phrase, imperative. */
				'label' => __( 'Show all field groups', 'acfml' ),
			];
		}

		$actions[] = [
			'url'   => self::getNoticeLink(),
			/* translators: Button in that notice; it opens the ACFML documentation. Verb phrase, imperative. */
			'label' => __( 'Go to documentation', 'acfml' ),
		];

		return $actions;
	}

	private static function getNoticeLink() {
		return Links::getDifferentTranslationEditorsDoc(
			[
				'utm_content' => 'manual-translation',
			]
		);
	}
}
