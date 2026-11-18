<?php
defined( 'ABSPATH' ) || exit;

$slot_type     = wpml_view_attr( $model, 'slot_type' );
$slug          = wpml_view_attr( $model, 'slug' );
$slot_settings = wpml_view_attr( $model, 'slot_settings' );
$slots         = wpml_view_attr( $model, 'slots' );
$strings       = wpml_view_attr( $model, 'strings' );
$is_static     = wpml_view_attr( $model, 'is_static' );
?>
<?php if ( 'statics' == $slot_type ) : ?>
	<?php $is_static = true; ?>
	<?php $dialog_title = wpml_view_attr( wpml_view_attr( $strings, $slug ), 'dialog_title' ); ?>
	<?php $include_row = 'slot-subform-' . $slot_type . '-' . $slug . '.twig'; ?>
<?php else : ?>
	<?php $dialog_title = wpml_view_attr( wpml_view_attr( $strings, $slot_type ), 'dialog_title' ); ?>
	<?php $include_row = 'slot-subform-' . $slot_type . '.twig'; ?>
<?php endif; ?>

<?php $slot_row_id = 'wpml-ls-' . $slot_type . '-row-' . $slug; ?>
<tr id="<?php echo wpml_view_esc( $slot_row_id ); ?>" class="js-wpml-ls-row" data-item-slug="<?php echo wpml_view_esc( $slug ); ?>" data-item-type="<?php echo wpml_view_esc( $slot_type ); ?>">
    <td class="wpml-ls-cell-preview">
        <div class="js-wpml-ls-subform wpml-ls-subform" data-origin-id="<?php echo wpml_view_esc( $slot_row_id ); ?>" data-title="<?php echo wpml_view_esc( $dialog_title ); ?>" data-item-slug="<?php echo wpml_view_esc( $slug ); ?>" data-item-type="<?php echo wpml_view_esc( $slot_type ); ?>">
            <?php if ( $slot_settings ) : ?>
                <?php
                $wpml_view->partial(
                    $include_row,
                    array_merge(
                        $model,
                        array(
                            'is_static'     => $is_static,
                            'dialog_title'  => $dialog_title,
                            'include_row'   => $include_row,
                            'slot_row_id'   => $slot_row_id,
                            'slug'          => $slug,
                            'slot_settings' => $slot_settings,
                            'settings'      => wpml_view_attr( $model, 'settings' ),
                            'slots'         => $slots,
                            'strings'       => $strings,
                            'preview'       => wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'previews' ), $slot_type ), $slug ),
                            'color_schemes' => wpml_view_attr( $model, 'color_schemes' ),
                        )
                    )
                );
                ?>
            <?php endif; ?>
        </div>
    </td>

	<?php if ( ! $is_static ) : ?>
    <td>
        <span class="js-wpml-ls-row-title"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $slots, $slug ), 'name' ) ); ?></span>
    </td>
	<?php endif; ?>

	<td class="wpml-ls-cell-action">
        <a href="#" title="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'misc' ), 'title_action_edit' ) ); ?>" class="js-wpml-ls-row-edit wpml-ls-row-edit"><i class="otgs-ico-edit"></i></a>
    </td>

	<?php if ( ! $is_static ) : ?>
    <td class="wpml-ls-cell-action">
        <a href="#" title="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $strings, 'misc' ), 'title_action_delete' ) ); ?>" class="js-wpml-ls-row-remove wpml-ls-row-remove"><i class="otgs-ico-delete"></i></a>
    </td>
	<?php endif; ?>
</tr>