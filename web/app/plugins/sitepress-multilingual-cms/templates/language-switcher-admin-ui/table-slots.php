<?php
defined( 'ABSPATH' ) || exit;

$slot_type      = wpml_view_attr( $model, 'slot_type' );
$slug           = wpml_view_attr( $model, 'slug' );
$slots_settings = wpml_view_attr( $model, 'slots_settings' );
$is_static      = wpml_view_attr( $model, 'is_static' );
$misc           = wpml_view_attr( wpml_view_attr( $model, 'strings' ), 'misc' );
?>
<?php if ( 'statics' == $slot_type ) : ?>
	<?php $is_static = true; ?>
	<?php $table_id = 'wpml-ls-slot-list-' . $slot_type . '-' . $slug; ?>
<?php else : ?>
	<?php $table_id = 'wpml-ls-slot-list-' . $slot_type; ?>
<?php endif; ?>

<?php if ( wpml_view_in( $slug, array( 'footer', 'post_translations' ) ) ) : ?>
    <?php $label_action = wpml_view_attr( $misc, 'label_action' ); ?>
<?php else : ?>
    <?php $label_action = wpml_view_attr( $misc, 'label_actions' ); ?>
<?php endif; ?>

<?php $context = array_merge( $model, array( 'is_static' => $is_static, 'table_id' => $table_id, 'label_action' => $label_action ) ); ?>
<table id="<?php echo wpml_view_esc( $table_id ); ?>" class="js-wpml-ls-slot-list wpml-ls-slot-list"<?php if ( ! $slots_settings ) : ?> style="display:none;"<?php endif; ?>>
    <thead>
    <tr>
        <th><?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_preview' ) ); ?></th>
        <?php if ( ! $is_static ) : ?><th><?php echo wpml_view_esc( wpml_view_attr( $misc, 'label_position' ) ); ?></th><?php endif; ?>
        <th<?php if ( ! $is_static ) : ?> colspan="2"<?php endif; ?>><?php echo wpml_view_esc( $label_action ); ?></th></tr>
    </thead>
    <tbody>
    <?php foreach ( is_array( $slots_settings ) ? $slots_settings : array() as $slug => $slot_settings ) : ?>
        <?php $wpml_view->partial( 'table-slot-row.twig', array_merge( $context, array( 'slug' => $slug, 'slot_type' => $slot_type, 'slot_settings' => $slot_settings, 'slots' => wpml_view_attr( $model, 'slots' ) ) ) ); ?>
    <?php endforeach; ?>
    </tbody>
</table>