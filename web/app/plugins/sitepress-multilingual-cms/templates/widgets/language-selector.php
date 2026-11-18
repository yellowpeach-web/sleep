<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_languages = isset( $languages ) ? $languages : null;
$wpml_view_selected_language = isset( $selected_language ) ? $selected_language : null;
$wpml_view_nonce = isset( $nonce ) ? $nonce : null;
?>
<p>
	<label for="wpml-language"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'label' ) ); ?></label>
	<select id="wpml-language" name="wpml_language">
		<?php foreach ( is_array( $wpml_view_languages ) ? $wpml_view_languages : array() as $wpml_view_language ) : ?>
			<option <?php echo wpml_view_attr( $wpml_view_language, 'code' ) == $wpml_view_selected_language ? 'selected="selected"' : ''; ?>

							value="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_language, 'code' ) ); ?>">
				<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_language, 'native_name' ) ); ?>

			</option>
		<?php endforeach; ?>
	</select>
	<input type="hidden" name="wpml-language-nonce" value="<?php echo wpml_view_esc( $wpml_view_nonce ); ?>">
</p>