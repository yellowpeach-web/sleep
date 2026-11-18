<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
?>
<tr class="user-language-wrap">
    <th colspan="2"><h3><a name="wpml"></a><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'section_title' ) ); ?></h3></th>
</tr>
<tr class="user-language-wrap">
    <th><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'field_title' ) ); ?></th>
    <td>
        <input type="checkbox" name="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'field_name' ) ); ?>" id="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'field_name' ) ); ?>" value="0" <?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'checked' ) ); ?> />
        &nbsp;<label for="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'field_name' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'field_text' ) ); ?></label>
    </td>
</tr>