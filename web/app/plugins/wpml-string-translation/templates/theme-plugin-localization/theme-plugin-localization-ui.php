<?php
defined( 'ABSPATH' ) || exit;

$section_type            = wpml_view_attr( $model, 'type' );
$render_section          = wpml_view_attr( $model, 'render_section' );
$section_class           = wpml_view_attr( $model, 'section_class' );
$show_active_label       = wpml_view_attr( $model, 'show_active_label' );
$scan_folder_nonce_field = wpml_view_attr( $model, 'scan_folder_nonce_field' );
$scan_folder_nonce_val   = wpml_view_attr( $model, 'scan_folder_nonce_val' );
$scan_files_nonce_field  = wpml_view_attr( $model, 'scan_files_nonce_field' );
$scan_files_nonce_val    = wpml_view_attr( $model, 'scan_files_nonce_val' );
$components              = wpml_view_attr( $model, 'components' );
$components_list         = is_array( $components ) ? $components : array();

$display = '';
?>
<?php if ( ! $render_section ) : ?>
<?php $display = ' style="display: none"'; ?>
<?php endif; ?>
<tbody id="wpml-<?php echo wpml_view_esc( $section_type ); ?>-content-header"<?php echo wpml_view_raw( $display ); ?>>
    <?php $wpml_view->partial( 'theme-plugin-localization-ui-category-header.twig', $model ); ?>
</tbody>
<tbody id="wpml-<?php echo wpml_view_esc( $section_type ); ?>-content" class="wpml-section wpml-section-table-content wpml-section-table-content-<?php echo wpml_view_esc( $section_type ); ?> wpml-st-localization <?php echo wpml_view_esc( $section_class ); ?>"<?php echo wpml_view_raw( $display ); ?>>
    <input type="hidden" name="<?php echo wpml_view_esc( $scan_folder_nonce_field ); ?>" value="<?php echo wpml_view_esc( $scan_folder_nonce_val ); ?>">
    <input type="hidden" name="<?php echo wpml_view_esc( $scan_files_nonce_field ); ?>" value="<?php echo wpml_view_esc( $scan_files_nonce_val ); ?>">

    <?php foreach ( $components_list as $dir_and_file => $component_data ) : ?>
        <?php if ( $show_active_label && wpml_view_attr( $component_data, 'active' ) ) : ?>
            <?php $wpml_view->partial( 'theme-plugin-localization-ui-item.twig', array_merge( $model, array( 'dir_and_file' => $dir_and_file, 'component_data' => $component_data ) ) ); ?>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php foreach ( $components_list as $dir_and_file => $component_data ) : ?>
        <?php if ( $show_active_label ) : ?>
            <?php if ( ! wpml_view_attr( $component_data, 'active' ) ) : ?>
                <?php $wpml_view->partial( 'theme-plugin-localization-ui-item.twig', array_merge( $model, array( 'dir_and_file' => $dir_and_file, 'component_data' => $component_data ) ) ); ?>
            <?php endif; ?>
        <?php else : ?>
            <?php $wpml_view->partial( 'theme-plugin-localization-ui-item.twig', array_merge( $model, array( 'dir_and_file' => $dir_and_file, 'component_data' => $component_data ) ) ); ?>
        <?php endif; ?>
    <?php endforeach; ?>
</tbody>
