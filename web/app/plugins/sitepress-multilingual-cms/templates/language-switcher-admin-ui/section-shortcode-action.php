<?php
defined( 'ABSPATH' ) || exit;

$shortcode_actions = wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'settings' ), 'statics' ), 'shortcode_actions' );
$texts             = wpml_view_attr( wpml_view_attr( $model, 'strings' ), 'shortcode_actions' );
?>
<p><?php echo wpml_view_raw( wpml_view_attr( $texts, 'section_description' ) ); ?></p>
<p>
    <input type="checkbox" id="wpml-ls-show-in-shortcode-actions" name="statics[shortcode_actions][show]" value="1"
                   class="wpml-checkbox-native js-wpml-ls-toggle-slot js-wpml-ls-trigger-save" data-target=".js-wpml-ls-shortcode-actions-toggle-target"
                   <?php if ( wpml_view_attr( $shortcode_actions, 'show' ) ) : ?>checked="checked"<?php endif; ?>/>

    <label for="wpml-ls-show-in-shortcode-actions"><?php echo wpml_view_esc( wpml_view_attr( $texts, 'show' ) ); ?></label>
</p>

<div class="hidden">
    <?php $slot_settings = array(); ?>
    <?php $slot_settings = array_merge( $slot_settings, array( 'shortcode_actions' => $shortcode_actions ) ); ?>

    <?php
    $wpml_view->partial(
        'table-slots.twig',
        array_merge(
            $model,
            array(
                'slot_settings'  => $slot_settings,
                'slot_type'      => 'statics',
                'slots_settings' => $slot_settings,
                'slug'           => 'shortcode_actions',
            )
        )
    );
    ?>

</div>

<div class="js-wpml-ls-shortcode-actions-toggle-target alignleft<?php if ( 1 != wpml_view_attr( $shortcode_actions, 'show' ) ) : ?> hidden<?php endif; ?>">
    <button class="button-secondary js-wpml-ls-open-dialog wpml-button base-btn wpml-button--outlined"
            data-target="#wpml-ls-slot-list-statics-shortcode_actions"
            name="wpml-ls-customize"><?php echo wpml_view_esc( wpml_view_attr( $texts, 'customize_button_label' ) ); ?></button>
</div>
