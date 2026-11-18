<?php
defined( 'ABSPATH' ) || exit;

$slot_settings = wpml_view_attr( $model, 'slot_settings' );
$name_base     = wpml_view_attr( $model, 'name_base' );
$strings       = wpml_view_attr( $model, 'strings' );
$menus         = wpml_view_attr( $strings, 'menus' );
?>
<?php if ( ! ( is_array( $slot_settings ) && array_key_exists( 'is_hierarchical', $slot_settings ) ) ) : ?>
    <?php $is_hierarchical = 1; ?>
<?php else : ?>
    <?php $is_hierarchical = wpml_view_attr( $slot_settings, 'is_hierarchical' ); ?>
<?php endif; ?>

<h4><label><?php echo wpml_view_esc( wpml_view_attr( $menus, 'is_hierarchical_label' ) ); ?></label>  <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( wpml_view_attr( $strings, 'tooltips' ), 'menu_style_type' ) ) ) ); ?></h4>
<ul>
    <li>
        <label>
            <input type="radio" class="wpml-radio-native js-wpml-ls-trigger-update js-wpml-ls-menu-is-hierarchical"
                   name="<?php if ( $name_base ) : ?><?php echo wpml_view_esc( $name_base ); ?>[is_hierarchical]<?php else : ?>is_hierarchical<?php endif; ?>"
                   value="1"<?php if ( 1 == $is_hierarchical ) : ?> checked="checked"<?php endif; ?>><b><?php echo wpml_view_esc( wpml_view_attr( $menus, 'hierarchical' ) ); ?></b> - <?php echo wpml_view_esc( wpml_view_attr( $menus, 'hierarchical_desc' ) ); ?>

        </label>
    </li>
    <li>
        <label>
            <input type="radio" class="wpml-radio-native js-wpml-ls-trigger-update js-wpml-ls-menu-is-hierarchical"
                   name="<?php if ( $name_base ) : ?><?php echo wpml_view_esc( $name_base ); ?>[is_hierarchical]<?php else : ?>is_hierarchical<?php endif; ?>"
                   value="0"<?php if ( 0 == $is_hierarchical ) : ?> checked="checked"<?php endif; ?>><b><?php echo wpml_view_esc( wpml_view_attr( $menus, 'flat' ) ); ?></b> - <?php echo wpml_view_esc( wpml_view_attr( $menus, 'flat_desc' ) ); ?>

        </label>
    </li>
</ul>
