<?php
defined( 'ABSPATH' ) || exit;

$content = wpml_view_attr( $model, 'content' );
$tooltip_link    = wpml_view_attr( $content, 'link' );
?>
<a href="#" id="<?php echo wpml_view_esc_attr( wpml_view_attr( $content, 'id' ) ); ?>" class="js-wpml-ls-tooltip-open wpml-ls-tooltip-open otgs-ico-help" role="tooltip" data-content="<?php echo wpml_view_esc_attr( wpml_view_attr( $content, 'text' ) ); ?>" data-link-text="<?php echo wpml_view_esc_attr( wpml_view_attr( $tooltip_link, 'text' ) ); ?>" data-link-url="<?php echo wpml_view_esc_attr( wpml_view_attr( $tooltip_link, 'url' ) ); ?>" data-link-target="<?php echo wpml_view_esc_attr( wpml_view_attr( $tooltip_link, 'target' ) ); ?>">
    <span class="visually-hidden"><?php echo wpml_view_esc_attr( wpml_view_attr( $content, 'text' ) ); ?></span>
</a>
