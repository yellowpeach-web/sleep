<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_url = isset( $url ) ? $url : null;
$wpml_view_class = isset( $class ) ? $class : null;
$wpml_view_label = isset( $label ) ? $label : null;
?>
<?php $wpml_view_arrows = array( 'first-page' => '«', 'previous-page' => '‹', 'next-page' => '›', 'last-page' => '»' ); ?>

<?php if ( $wpml_view_url ) : ?>
    <a class="<?php echo wpml_view_esc( $wpml_view_class ); ?>" href="<?php echo wpml_view_esc( $wpml_view_url ); ?>">
        <span class="screen-reader-text"><?php echo wpml_view_esc( $wpml_view_label ); ?></span><span aria-hidden="true"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_arrows, wpml_view_string( $wpml_view_class ) ) ); ?></span>
    </a>
<?php else: ?>
    <span class="tablenav-pages-navspan" aria-hidden="true"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_arrows, wpml_view_string( $wpml_view_class ) ) ); ?></span>
<?php endif; ?>