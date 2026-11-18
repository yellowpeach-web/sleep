<?php
defined( 'ABSPATH' ) || exit;
?>
<form method="post" action="" name="wpml-user-language-switcher-form" id="wpml-user-language-switcher-form">
	<select name="language" title="Language selector">
		<?php foreach ( isset( $options ) && is_array( $options ) ? $options : array() as $option ) : ?>
			<option value="<?php echo wpml_view_esc( wpml_view_attr( $option, 'value' ) ); ?>"<?php echo wpml_view_attr( $option, 'selected' ) ? ' selected="selected"' : ''; ?>><?php echo wpml_view_esc( wpml_view_attr( $option, 'label' ) ); ?></option>
		<?php endforeach; ?>
	</select>
</form>
