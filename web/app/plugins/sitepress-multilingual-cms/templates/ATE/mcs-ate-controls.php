<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
?>
<div id="ate-controls" class="ate-controls sub-section">
	<div class="otgs-notice otgs-notice__status inline">
		<p> </p>
		<span class="spinner"> </span>
	</div>

	<div class="otgs-notice otgs-notice__errors error inline hidden">
		<p class="message"> </p>
		<p class="help"><?php echo wpml_view_raw( wpml_view_attr( $wpml_view_strings, 'error_help' ) ); ?></p>
	</div>
</div>
