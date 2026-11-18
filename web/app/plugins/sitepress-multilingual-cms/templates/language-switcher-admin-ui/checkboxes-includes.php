<?php
defined( 'ABSPATH' ) || exit;

$slot_settings = wpml_view_attr( $model, 'slot_settings' );
$name_base     = wpml_view_attr( $model, 'name_base' );
$strings       = wpml_view_attr( $model, 'strings' );
$misc          = wpml_view_attr( $strings, 'misc' );
?>
<?php $force = wpml_view_attr( wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'data' ), 'templates' ), wpml_view_attr( $model, 'template_slug' ) ), 'force_settings' ); ?>
<?php $is_hierarchical = ( 'menus' == wpml_view_attr( $slot_settings, 'slot_group' ) ) && wpml_view_attr( $slot_settings, 'is_hierarchical' ); ?>

<h4><?php echo wpml_view_esc( wpml_view_attr( $misc, 'title_what_to_include' ) ); ?> <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( wpml_view_attr( $strings, 'tooltips' ), 'what_to_include' ) ) ) ); ?></h4>
<ul class="js-wpml-ls-to-include">
    <li>
        <label>
            <input
                    type="checkbox"
                    class="wpml-checkbox-native js-wpml-ls-setting-display_flags js-wpml-ls-toggle-suboptions js-wpml-ls-trigger-update"
                    data-target=".js-wpml-ls-flag-sizes"
                    data-show-on-checked="1"
                    value="1"
                    name="<?php if ( $name_base ) : ?><?php echo wpml_view_esc( $name_base ); ?>[display_flags]<?php else : ?>display_flags<?php endif; ?>"
                    <?php if ( is_array( $force ) && array_key_exists( 'display_flags', $force ) ) : ?> disabled="disabled"<?php endif; ?>
                    <?php if ( wpml_view_attr( $slot_settings, 'display_flags' ) ) : ?> checked="checked"<?php endif; ?>
            > <?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_include_flag' ) ); ?>

        </label>
        <ul class="js-wpml-ls-flag-sizes"
            style="display: <?php if ( wpml_view_attr( $slot_settings, 'display_flags' ) ) : ?>block<?php else : ?>none<?php endif; ?>;">
            <li>
                <label>
                    <?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_include_flag_width' ) ); ?>

                    <input
                            type="number"
                            class="js-wpml-ls-setting-include_flag_width js-wpml-ls-trigger-update"
                            name="<?php if ( $name_base ) : ?><?php echo wpml_view_esc( $name_base ); ?>[include_flag_width]<?php else : ?>include_flag_width<?php endif; ?>"
                            value="<?php echo wpml_view_attr( $slot_settings, 'include_flag_width' ) > 0 ? wpml_view_esc( wpml_view_attr( $slot_settings, 'include_flag_width' ) ) : ''; ?>"
                            placeholder="<?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_include_flag_width_placeholder' ) ); ?>"
                    >
                    <?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_include_flag_width_suffix' ) ); ?>

                </label>
            </li>
            <li>
                <label>
                    <?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_include_flag_height' ) ); ?>

                    <input
                            type="number"
                            class="js-wpml-ls-setting-_include_flag_height js-wpml-ls-trigger-update"
                            name="<?php if ( $name_base ) : ?><?php echo wpml_view_esc( $name_base ); ?>[include_flag_height]<?php else : ?>include_flag_height<?php endif; ?>"
                            value="<?php echo wpml_view_attr( $slot_settings, 'include_flag_height' ) > 0 ? wpml_view_esc( wpml_view_attr( $slot_settings, 'include_flag_height' ) ) : ''; ?>"
                            placeholder="<?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_include_flag_height_placeholder' ) ); ?>"
                    >
                    <?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_include_flag_height_suffix' ) ); ?>

                </label>
            </li>
        </ul>
    </li>
    <li>
        <label><input type="checkbox" class="wpml-checkbox-native js-wpml-ls-setting-display_names_in_native_lang js-wpml-ls-trigger-update"
                      name="<?php if ( $name_base ) : ?><?php echo wpml_view_esc( $name_base ); ?>[display_names_in_native_lang]<?php else : ?>display_names_in_native_lang<?php endif; ?>"
                    <?php if ( is_array( $force ) && array_key_exists( 'display_names_in_native_lang', $force ) ) : ?> disabled="disabled"<?php endif; ?>
                      value="1"<?php if ( wpml_view_attr( $slot_settings, 'display_names_in_native_lang' ) ) : ?> checked="checked"<?php endif; ?>> <?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_include_native_lang' ) ); ?>

        </label>
    </li>
    <li>
        <label><input type="checkbox" class="wpml-checkbox-native js-wpml-ls-setting-display_names_in_current_lang js-wpml-ls-trigger-update"
                      name="<?php if ( $name_base ) : ?><?php echo wpml_view_esc( $name_base ); ?>[display_names_in_current_lang]<?php else : ?>display_names_in_current_lang<?php endif; ?>"
                    <?php if ( is_array( $force ) && array_key_exists( 'display_names_in_current_lang', $force ) ) : ?> disabled="disabled"<?php endif; ?>
                      value="1"<?php if ( wpml_view_default( wpml_view_attr( $slot_settings, 'display_names_in_current_lang' ), 1 ) ) : ?> checked="checked"<?php endif; ?>> <?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_include_display_lang' ) ); ?>

        </label>
    </li>
    <li>
        <label><input type="checkbox" class="wpml-checkbox-native js-wpml-ls-setting-display_link_for_current_lang js-wpml-ls-trigger-update"
                      name="<?php if ( $name_base ) : ?><?php echo wpml_view_esc( $name_base ); ?>[display_link_for_current_lang]<?php else : ?>display_link_for_current_lang<?php endif; ?>"
                    <?php if ( ( is_array( $force ) && array_key_exists( 'display_link_for_current_lang', $force ) ) || $is_hierarchical ) : ?> disabled="disabled"<?php endif; ?>
                      value="1"<?php if ( wpml_view_default( wpml_view_attr( $slot_settings, 'display_link_for_current_lang' ), 1 ) ) : ?> checked="checked"<?php endif; ?>> <?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_include_current_lang' ) ); ?>

        </label>
    </li>
</ul>
