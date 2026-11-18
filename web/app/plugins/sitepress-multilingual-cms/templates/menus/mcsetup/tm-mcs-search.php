<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_search_string = isset( $search_string ) ? $search_string : null;
?>
<div class="wpml-flex-table-row wpml_tm_search_container">
    <div class="wpml-flex-table-cell text-right">
        <span class="spinner"></span>
        <label><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'search_for' ) ); ?>: <input type="text" class="wpml_tm_search" value="<?php echo wpml_view_esc( $wpml_view_search_string ); ?>"></label>
    </div>
</div>
