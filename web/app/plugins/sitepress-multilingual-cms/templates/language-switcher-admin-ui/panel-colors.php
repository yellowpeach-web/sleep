<?php
defined( 'ABSPATH' ) || exit;

$slot_settings = wpml_view_attr( $model, 'slot_settings' );
$name_base     = wpml_view_attr( $model, 'name_base' );
$picker_id            = wpml_view_attr( $model, 'id' );
$strings       = wpml_view_attr( $model, 'strings' );
$picker        = wpml_view_attr( $strings, 'color_picker' );
$color_schemes = wpml_view_attr( $model, 'color_schemes' );
?>
<?php
$wrapper_options = array(
	array( 'label' => wpml_view_attr( $picker, 'background' ), 'name' => 'background', 'schemes' => array( 'normal' ), 'default' => '' ),
	array( 'label' => wpml_view_attr( $picker, 'border' ), 'name' => 'border', 'schemes' => array( 'normal' ), 'default' => '' ),
);
?>

<?php
$options = array(
	array( 'label' => wpml_view_attr( $picker, 'font_current' ), 'name' => 'font_current', 'schemes' => array( 'normal', 'hover' ), 'default' => '' ),
	array( 'label' => wpml_view_attr( $picker, 'background_current' ), 'name' => 'background_current', 'schemes' => array( 'normal', 'hover' ), 'default' => '' ),
	array( 'label' => wpml_view_attr( $picker, 'font_other' ), 'name' => 'font_other', 'schemes' => array( 'normal', 'hover' ), 'default' => '' ),
	array( 'label' => wpml_view_attr( $picker, 'background_other' ), 'name' => 'background_other', 'schemes' => array( 'normal', 'hover' ), 'default' => '' ),
);
?>

<?php if ( 'menus' != wpml_view_attr( $model, 'slot_type' ) ) : ?>
    <?php $options = array_merge( $wrapper_options, $options ); ?>
<?php endif; ?>

<?php $css_class = wpml_view_default( wpml_view_attr( $model, 'css_class' ), 'js-wpml-ls-colorpicker' ); ?>

<div class="js-wpml-ls-panel-colors wpml-ls-panel-colors">
    <h4><?php echo wpml_view_esc( wpml_view_attr( $picker, 'panel_title' ) ); ?></h4>

    <label for="wpml-ls-<?php echo wpml_view_esc( $picker_id ); ?>-colorpicker-preset"><?php echo wpml_view_esc( wpml_view_attr( $picker, 'label_color_preset' ) ); ?></label>
    <select id="wpml-ls-<?php echo wpml_view_esc( $picker_id ); ?>-colorpicker-preset" class="js-wpml-ls-colorpicker-preset">
        <option value="">-- <?php echo wpml_view_esc( wpml_view_attr( $picker, 'select_option_choose' ) ); ?> --</option>
        <?php foreach ( is_array( $color_schemes ) ? $color_schemes : array() as $scheme_id => $scheme ) : ?>
            <option value="<?php echo wpml_view_esc( $scheme_id ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $scheme, 'label' ) ); ?></option>
        <?php endforeach; ?>
    </select>

    <div>
        <table>
            <tr>
                <td>
                </td>
                <th id="normal_color_scheme"><?php echo wpml_view_esc( wpml_view_attr( $picker, 'label_normal_scheme' ) ); ?></th>
                <th id="hover_color_scheme"><?php echo wpml_view_esc( wpml_view_attr( $picker, 'label_hover_scheme' ) ); ?></th>
            </tr>
            <?php foreach ( $options as $option ) : ?>
            <tr>
                <td id="color_label_<?php echo wpml_view_esc( wpml_view_attr( $option, 'name' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $option, 'label' ) ); ?></td>
                <td class="js-wpml-ls-colorpicker-wrapper">
                    <?php if ( wpml_view_in( 'normal', wpml_view_attr( $option, 'schemes' ) ) ) : ?>
                        <?php if ( $name_base ) : ?>
                            <?php $input_name = $name_base . '[' . wpml_view_attr( $option, 'name' ) . '_normal]'; ?>
                        <?php else : ?>
                            <?php $input_name = wpml_view_attr( $option, 'name' ) . '_normal'; ?>
                        <?php endif; ?>
                        <input class="<?php echo wpml_view_esc( $css_class ); ?> js-wpml-ls-color-<?php echo wpml_view_esc( wpml_view_attr( $option, 'name' ) ); ?>_normal" type="text" size="7"
                               id="wpml-ls-<?php echo wpml_view_esc( $picker_id ); ?>-<?php echo wpml_view_esc( wpml_view_attr( $option, 'name' ) ); ?>-normal" name="<?php echo wpml_view_esc( $input_name ); ?>"
                               value="<?php echo wpml_view_esc( wpml_view_default( wpml_view_attr( $slot_settings, wpml_view_attr( $option, 'name' ) . '_normal' ), wpml_view_attr( $option, 'default' ) ) ); ?>" data-default-color="<?php echo wpml_view_esc( wpml_view_attr( $option, 'default' ) ); ?>" style="display: none;">
                    <?php endif; ?>
                </td>
                <td class="js-wpml-ls-colorpicker-wrapper">
                    <?php if ( wpml_view_in( 'hover', wpml_view_attr( $option, 'schemes' ) ) ) : ?>
                        <?php if ( $name_base ) : ?>
                            <?php $input_name = $name_base . '[' . wpml_view_attr( $option, 'name' ) . '_hover]'; ?>
                        <?php else : ?>
                            <?php $input_name = wpml_view_attr( $option, 'name' ) . '_hover'; ?>
                        <?php endif; ?>
                        <input class="<?php echo wpml_view_esc( $css_class ); ?> js-wpml-ls-color-<?php echo wpml_view_esc( wpml_view_attr( $option, 'name' ) ); ?>_hover" type="text" size="7"
                               id="wpml-ls-<?php echo wpml_view_esc( $picker_id ); ?>-<?php echo wpml_view_esc( wpml_view_attr( $option, 'name' ) ); ?>-hover" name="<?php echo wpml_view_esc( $input_name ); ?>"
                               value="<?php echo wpml_view_esc( wpml_view_default( wpml_view_attr( $slot_settings, wpml_view_attr( $option, 'name' ) . '_hover' ), wpml_view_attr( $option, 'default' ) ) ); ?>" data-default-color="<?php echo wpml_view_esc( wpml_view_attr( $option, 'default' ) ); ?>" style="display: none;">
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
