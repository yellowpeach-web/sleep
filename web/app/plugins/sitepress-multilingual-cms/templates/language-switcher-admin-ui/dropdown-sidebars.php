<?php
defined( 'ABSPATH' ) || exit;

$sidebars = wpml_view_attr( $model, 'sidebars' );
$slug     = wpml_view_attr( $model, 'slug' );
$settings = wpml_view_attr( $model, 'settings' );
$strings  = wpml_view_attr( $model, 'strings' );
$tooltips = wpml_view_attr( $strings, 'tooltips' );
$taken    = wpml_view_attr( $settings, 'sidebar' );
$taken    = is_array( $taken ) ? array_keys( $taken ) : array();
?>
<h4><label for="wpml-ls-available-sidebars"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'sidebars' ), 'select_label' ) ); ?>:</label>  <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( $tooltips, 'available_sidebars' ) ) ) ); ?></h4>
<select name="wpml_ls_available_sidebars" class="js-wpml-ls-available-slots js-wpml-ls-available-sidebars">
    <option disabled="disabled">-- <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'sidebars' ), 'select_option_choose' ) ); ?> --</option>
    <?php foreach ( is_array( $sidebars ) ? $sidebars : array() as $sidebar_key => $sidebar ) : ?>
        <?php if ( $sidebar_key == $slug ) : ?>
            <?php $attr = ' selected="selected"'; ?>
        <?php elseif ( wpml_view_in( wpml_view_attr( $sidebar, 'id' ), $taken ) ) : ?>
            <?php $attr = ' disabled="disabled"'; ?>
        <?php else : ?>
            <?php $attr = ''; ?>
        <?php endif; ?>
        <option value="<?php echo wpml_view_esc( wpml_view_attr( $sidebar, 'id' ) ); ?>"<?php echo wpml_view_esc( $attr ); ?>><?php echo wpml_view_esc( wpml_view_attr( $sidebar, 'name' ) ); ?></option>
    <?php endforeach; ?>
</select>
