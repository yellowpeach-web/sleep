<?php
defined( 'ABSPATH' ) || exit;

$slug          = wpml_view_attr( $model, 'slug' );
$slot_settings = wpml_view_attr( $model, 'slot_settings' );
$strings       = wpml_view_attr( $model, 'strings' );
?>
<?php if ( ! array_key_exists( 'slot_settings', $model ) ) : ?>
    <?php $slot_settings = wpml_view_attr( $model, 'default_sidebars_slot' ); ?>
<?php endif; ?>

<?php $context = array_merge( $model, array( 'slot_settings' => $slot_settings ) ); ?>
<?php $wpml_view->partial( 'preview.twig', array_merge( $context, array( 'preview' => wpml_view_attr( $model, 'preview' ) ) ) ); ?>

<div class="wpml-ls-subform-options">

    <?php
    $wpml_view->partial(
        'dropdown-sidebars.twig',
        array_merge(
            $context,
            array(
                'slug'     => $slug,
                'settings' => wpml_view_attr( $model, 'settings' ),
                'sidebars' => wpml_view_attr( $model, 'slots' ),
                'strings'  => $strings,
            )
        )
    );
    ?>

    <?php
    $wpml_view->partial(
        'dropdown-templates.twig',
        array_merge(
            $context,
            array(
                'id'        => 'in-sidebars-' . $slug,
                'name'      => 'sidebars[' . $slug . '][template]',
                'value'     => wpml_view_attr( $slot_settings, 'template' ),
                'slot_type' => 'sidebars',
            )
        )
    );
    ?>

    <?php
    $wpml_view->partial(
        'checkboxes-includes.twig',
        array_merge(
            $context,
            array(
                'name_base'     => 'sidebars[' . $slug . ']',
                'slot_settings' => $slot_settings,
                'strings'       => $strings,
                'template_slug' => wpml_view_attr( $slot_settings, 'template' ),
            )
        )
    );
    ?>

    <h4><label for="widget-title-in-<?php echo wpml_view_esc( $slug ); ?>">
            <?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'sidebars' ), 'label_widget_title' ) ); ?>  <?php $wpml_view->partial( 'tooltip.twig', array_merge( $context, array( 'content' => wpml_view_attr( wpml_view_attr( $strings, 'tooltips' ), 'widget_title' ) ) ) ); ?></label></h4>

    <input type="text" id="widget-title-in-<?php echo wpml_view_esc( $slug ); ?>"
           name="sidebars[<?php echo wpml_view_esc( $slug ); ?>][widget_title]" value="<?php echo wpml_view_esc( wpml_view_attr( $slot_settings, 'widget_title' ) ); ?>" size="40">


    <?php
    $wpml_view->partial(
        'panel-colors.twig',
        array_merge(
            $context,
            array(
                'strings'       => $strings,
                'id'            => 'in-sidebars-' . $slug,
                'name_base'     => 'sidebars[' . $slug . ']',
                'slot_settings' => $slot_settings,
                'color_schemes' => wpml_view_attr( $model, 'color_schemes' ),
            )
        )
    );
    ?>

</div>