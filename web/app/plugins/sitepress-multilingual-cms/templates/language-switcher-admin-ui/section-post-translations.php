<?php
defined( 'ABSPATH' ) || exit;

$post_translations = wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'settings' ), 'statics' ), 'post_translations' );
?>
<p class="wpml-ls-form-line js-wpml-ls-option">
    <label for="wpml-ls-show-in-post-translations">
        <input type="checkbox" id="wpml-ls-show-in-post-translations" name="statics[post_translations][show]" value="1"
               class="wpml-checkbox-native js-wpml-ls-toggle-slot js-wpml-ls-trigger-save" data-target=".js-wpml-ls-post-translations-toggle-target"
               <?php if ( wpml_view_attr( $post_translations, 'show' ) ) : ?>checked="checked"<?php endif; ?>/>
        <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'strings' ), 'post_translations' ), 'show' ) ); ?>

    </label>

    <?php $wpml_view->partial( 'save-notification.twig', $model ); ?>
</p>

<div class="js-wpml-ls-post-translations-toggle-target alignleft<?php if ( 1 != wpml_view_attr( $post_translations, 'show' ) ) : ?> hidden<?php endif; ?>">

    <?php $slot_settings = array(); ?>
    <?php $slot_settings = array_merge( $slot_settings, array( 'post_translations' => $post_translations ) ); ?>

    <?php
    $wpml_view->partial(
        'table-slots.twig',
        array_merge(
            $model,
            array(
                'slot_settings'  => $slot_settings,
                'slot_type'      => 'statics',
                'slots_settings' => $slot_settings,
                'slug'           => 'post_translations',
            )
        )
    );
    ?>

</div>
