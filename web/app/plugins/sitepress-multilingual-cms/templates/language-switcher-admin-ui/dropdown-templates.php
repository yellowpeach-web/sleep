<?php
defined( 'ABSPATH' ) || exit;

$data      = wpml_view_attr( $model, 'data' );
$slot_type = wpml_view_attr( $model, 'slot_type' );
$value     = wpml_view_attr( $model, 'value' );
$strings   = wpml_view_attr( $model, 'strings' );
$misc      = wpml_view_attr( $strings, 'misc' );
$templates = wpml_view_attr( $data, 'templates' );
?>
<?php $supported_core_templates = array(); ?>
<?php $supported_custom_templates = array(); ?>

<?php foreach ( is_array( $templates ) ? $templates : array() as $template ) : ?>
<?php if ( wpml_view_in( $slot_type, wpml_view_attr( $template, 'supported_slot_types' ) ) ) : ?>
	<?php if ( wpml_view_attr( $template, 'is_core' ) ) : ?>
		<?php $supported_core_templates = array_merge( $supported_core_templates, array( $template ) ); ?>
	<?php else : ?>
		<?php $supported_custom_templates = array_merge( $supported_custom_templates, array( $template ) ); ?>
	<?php endif; ?>
<?php endif; ?>
<?php endforeach; ?>

<?php $total_templates = count( $supported_core_templates ) + count( $supported_custom_templates ); ?>

<div<?php if ( $total_templates <= 1 ) : ?> class="hidden"<?php endif; ?>>

	<h4><label for="template-<?php echo wpml_view_esc( wpml_view_attr( $model, 'id' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $misc, 'templates_dropdown_label' ) ); ?></label>  <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( wpml_view_attr( $strings, 'tooltips' ), 'available_templates' ) ) ) ); ?></h4>

	<select id="template-<?php echo wpml_view_esc( wpml_view_attr( $model, 'id' ) ); ?>" name="<?php echo wpml_view_esc( wpml_view_attr( $model, 'name' ) ); ?>" class="js-wpml-ls-template-selector js-wpml-ls-trigger-update">

		<optgroup label="<?php echo wpml_view_esc( wpml_view_attr( $misc, 'templates_wpml_group' ) ); ?>">
		<?php foreach ( $supported_core_templates as $template ) : ?>
			<?php $template_data = wpml_view_attr( $template, 'get_template_data' ); ?>
			<option value="<?php echo wpml_view_esc( wpml_view_attr( $template_data, 'slug' ) ); ?>" <?php if ( $value == wpml_view_attr( $template_data, 'slug' ) ) : ?>selected="selected"<?php endif; ?>><?php echo wpml_view_esc( wpml_view_attr( $template_data, 'name' ) ); ?></option>
		<?php endforeach; ?>
		</optgroup>

		<?php if ( count( $supported_custom_templates ) > 0 ) : ?>
			<optgroup label="<?php echo wpml_view_esc( wpml_view_attr( $misc, 'templates_custom_group' ) ); ?>">
			<?php foreach ( $supported_custom_templates as $template ) : ?>
				<?php $template_data = wpml_view_attr( $template, 'get_template_data' ); ?>
				<option value="<?php echo wpml_view_esc( wpml_view_attr( $template_data, 'slug' ) ); ?>" <?php if ( $value == wpml_view_attr( $template_data, 'slug' ) ) : ?>selected="selected"<?php endif; ?>><?php echo wpml_view_esc( wpml_view_attr( $template_data, 'name' ) ); ?></option>
			<?php endforeach; ?>
			</optgroup>
		<?php endif; ?>

	</select>

</div>
