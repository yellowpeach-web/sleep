<?php

namespace ACFML\Repeater\Sync;

class CheckboxUI {

	const ACTION_SYNCHRONISE = 'wpml_synchronise_acf_fields_translations_nonce';
	const META_BOX_ID        = 'acfml-field-group-synchronise';

	public static function render( $trid, $displayTitle = false ) {
		?>
		<div class="acf-field acfml-synchronise-repeater-checkbox">
			<?php if ( $displayTitle ) { ?>
				<h2><?php /* translators: Heading of the checkbox that keeps repeater rows in the same order across languages, in the post editor. Verb phrase, imperative. */ esc_html_e( 'Synchronise translations', 'acfml' ); ?></h2>
			<?php } ?>
			<div class="acf-input">
				<div class="acf-input-wrap">
					<input type="checkbox" name="wpml_synchronise_acf_fields_translations" value="synchronise" <?php checked( self::isChecked( $trid ), true, true ); ?> />
					<?php wp_nonce_field( self::ACTION_SYNCHRONISE, self::ACTION_SYNCHRONISE ); ?>
					<label for="wpml_synchronise_acf_fields_translations"><?php /* translators: Label of that checkbox; "repeater" and "flexible" are ACF field types. */ esc_html_e( 'Keep repeater and flexible sub-fields in the same order as the default language.', 'acfml' ); ?></label>
				</div>
			</div>
		</div>
		<?php
	}

	public static function addMetaBox( $trid, $screen ) {
		add_meta_box(
			self::META_BOX_ID,
			/* translators: Title of the panel holding that checkbox in the post editor. "ACFML" is the plugin's short name and stays in English. */
			esc_html__( 'ACFML Synchronise translations', 'acfml' ),
			function () use ( $trid ) {
				self::render( $trid );
			},
			$screen,
			'normal',
			'high'
		);
	}

	public static function removeMetaBox( $screen ) {
		remove_meta_box( self::META_BOX_ID, $screen, 'normal' );
	}

	public static function isSelected() {
		return isset( $_POST[ self::ACTION_SYNCHRONISE ] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::ACTION_SYNCHRONISE ] ) ), self::ACTION_SYNCHRONISE )
			&& isset( $_POST['wpml_synchronise_acf_fields_translations'] )
			&& 'synchronise' === $_POST['wpml_synchronise_acf_fields_translations'];
	}

	protected static function isChecked( $trid ) {
		if ( $trid ) {
			$synchroniseOption = CheckboxOption::get();
			if ( isset( $synchroniseOption[ $trid ] ) ) {
				return (bool) $synchroniseOption[ $trid ];
			}
		}
		return defined( 'ACFML_REPEATER_SYNC_DEFAULT' ) ? (bool) constant( 'ACFML_REPEATER_SYNC_DEFAULT' ) : true;
	}

	public static function isOptionSent() {
		return isset( $_POST[ self::ACTION_SYNCHRONISE ] );
	}
}
