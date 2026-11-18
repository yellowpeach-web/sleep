<?php
$wpml_ls_cc          = wpml_ls_core_css_classes( isset( $backward_compatibility ) ? $backward_compatibility : null );
$css_classes_flag    = $wpml_ls_cc['flag'];
$css_classes_native  = $wpml_ls_cc['native'];
$css_classes_display = $wpml_ls_cc['display'];
$css_classes_bracket = $wpml_ls_cc['bracket'];
?>

<?php
$language = array(
	'flag_url'    => isset( $flag_url ) ? $flag_url : null,
	'flag_alt'    => isset( $flag_alt ) ? $flag_alt : null,
	'flag_width'  => isset( $flag_width ) ? $flag_width : null,
	'flag_height' => isset( $flag_height ) ? $flag_height : null,
);
require dirname( __DIR__ ) . '/flag.php';

$language = array(
	'native_name'  => isset( $native_name ) ? $native_name : null,
	'display_name' => isset( $display_name ) ? $display_name : null,
);
$wpml_ls_names_current = false;
$wpml_ls_names_lang    = isset( $hreflang ) ? $hreflang : '';
require dirname( __DIR__ ) . '/names.php';
