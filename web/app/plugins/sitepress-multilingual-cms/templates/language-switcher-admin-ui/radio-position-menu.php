<?php
defined( 'ABSPATH' ) || exit;

$slot_settings = wpml_view_attr( $model, 'slot_settings' );
$name_base     = wpml_view_attr( $model, 'name_base' );
$strings       = wpml_view_attr( $model, 'strings' );
$menus         = wpml_view_attr( $strings, 'menus' );
?>
<?php if ( ! wpml_view_attr( $slot_settings, 'position_in_menu' ) ) : ?>
    <?php $menu_position = 'after'; ?>
<?php else : ?>
    <?php $menu_position = wpml_view_attr( $slot_settings, 'position_in_menu' ); ?>
<?php endif; ?>

<h4><label><?php echo wpml_view_esc( wpml_view_attr( $menus, 'position_label' ) ); ?></label>  <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( wpml_view_attr( $strings, 'tooltips' ), 'menu_position' ) ) ) ); ?></h4>
<ul>
    <li>
        <label>
            <input type="radio" name="<?php if ( $name_base ) : ?><?php echo wpml_view_esc( $name_base ); ?>[position_in_menu]<?php else : ?>position_in_menu<?php endif; ?>"
                   class="wpml-radio-native js-wpml-ls-trigger-update"
                   value="before"<?php if ( 'before' == $menu_position ) : ?> checked="checked"<?php endif; ?>><?php echo wpml_view_esc( wpml_view_attr( $menus, 'position_first_item' ) ); ?>

        </label>
    </li>
    <li>
        <label>
            <input type="radio" name="<?php if ( $name_base ) : ?><?php echo wpml_view_esc( $name_base ); ?>[position_in_menu]<?php else : ?>position_in_menu<?php endif; ?>"
                   class="wpml-radio-native js-wpml-ls-trigger-update"
                   value="after"<?php if ( 'after' == $menu_position ) : ?> checked="checked"<?php endif; ?>><?php echo wpml_view_esc( wpml_view_attr( $menus, 'position_last_item' ) ); ?>

        </label>
    </li>
</ul>
