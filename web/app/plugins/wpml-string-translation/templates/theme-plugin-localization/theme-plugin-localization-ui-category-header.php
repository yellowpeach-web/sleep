<?php
defined( 'ABSPATH' ) || exit;

$section_type   = wpml_view_attr( $model, 'type' );
$component      = wpml_view_attr( $model, 'component' );
$render_filters = wpml_view_attr( $model, 'render_filters' );
$all_text       = wpml_view_attr( $model, 'all_text' );
$active_text    = wpml_view_attr( $model, 'active_text' );
$inactive_text  = wpml_view_attr( $model, 'inactive_text' );
$status_count   = wpml_view_attr( $model, 'status_count' );
?>
<tr class="category-header" data-togglecategory="<?php echo wpml_view_esc( $section_type ); ?>">
    <th class="checkbox-column">
        <input class="wpml-checkbox-native" type="checkbox">
    </th>
    <th colspan="2"><?php echo wpml_view_esc( $component ); ?></th>
    <th class="wpml-col-right" colspan="4">
        <?php if ( $render_filters ) : ?>
        <ul class="state-selector">
            <li data-status="all"><?php echo wpml_view_esc( $all_text ); ?> (<?php echo wpml_view_esc( wpml_view_attr( $status_count, 'active' ) + wpml_view_attr( $status_count, 'inactive' ) ); ?>)</li>
            <li class="state-selector-separator">|</li>
            <li data-status="active" class="active"><?php echo wpml_view_esc( $active_text ); ?> (<?php echo wpml_view_esc( wpml_view_attr( $status_count, 'active' ) ); ?>)</li>
            <li class="state-selector-separator">|</li>
            <li data-status="inactive"><?php echo wpml_view_esc( $inactive_text ); ?> (<?php echo wpml_view_esc( wpml_view_attr( $status_count, 'inactive' ) ); ?>)</li>
        </ul>
        <?php endif; ?>
    </th>
</tr>
