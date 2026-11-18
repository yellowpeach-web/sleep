<?php
defined( 'ABSPATH' ) || exit;

$menus    = wpml_view_attr( $model, 'menus' );
$slug     = wpml_view_attr( $model, 'slug' );
$settings = wpml_view_attr( $model, 'settings' );
$strings  = wpml_view_attr( $model, 'strings' );
$tooltips = wpml_view_attr( $strings, 'tooltips' );
$taken    = wpml_view_attr( $settings, 'menus' );
$taken    = is_array( $taken ) ? array_keys( $taken ) : array();
?>
<h4><label for="wpml-ls-available-menus"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'menus' ), 'select_label' ) ); ?>:</label>  <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( $tooltips, 'available_menus' ) ) ) ); ?></h4>
<select id="wpml-ls-available-menus" aria-describedby="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $tooltips, 'available_menus' ), 'id' ) ); ?>" name="wpml_ls_available_menus" class="js-wpml-ls-available-slots js-wpml-ls-available-menus">
    <option disabled="disabled">-- <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'menus' ), 'select_option_choose' ) ); ?> --</option>
    <?php foreach ( is_array( $menus ) ? $menus : array() as $menu_key => $nav_menu ) : ?>
        <?php if ( $menu_key == $slug ) : ?>
            <?php $attr = ' selected="selected"'; ?>
        <?php elseif ( wpml_view_in( wpml_view_attr( $nav_menu, 'term_id' ), $taken ) ) : ?>
            <?php $attr = ' disabled="disabled"'; ?>
        <?php else : ?>
            <?php $attr = ''; ?>
        <?php endif; ?>
        <option value="<?php echo wpml_view_esc( wpml_view_attr( $nav_menu, 'term_id' ) ); ?>"<?php echo wpml_view_esc( $attr ); ?>><?php echo wpml_view_esc( wpml_view_attr( $nav_menu, 'name' ) ); ?></option>
    <?php endforeach; ?>
</select>
