<?php
defined( 'ABSPATH' ) || exit;

$strings = wpml_view_attr( $model, 'strings' );
$slot    = wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'settings' ), 'statics' ), 'post_translations' );
$texts   = wpml_view_attr( $strings, 'post_translations' );

$availability = wpml_view_attr( $slot, 'availability_text' );
?>
<?php $wpml_view->partial( 'preview.twig', array_merge( $model, array( 'preview' => wpml_view_attr( wpml_view_attr( wpml_view_attr( $model, 'previews' ), 'statics' ), 'post_translations' ) ) ) ); ?>

<div class="wpml-ls-subform-options">

	<?php
	$wpml_view->partial(
		'dropdown-templates.twig',
		array_merge(
			$model,
			array(
				'id'        => 'in-post-translations',
				'name'      => 'statics[post_translations][template]',
				'value'     => wpml_view_attr( $slot, 'template' ),
				'slot_type' => 'post_translations',
			)
		)
	);
	?>

	<?php
	$wpml_view->partial(
		'checkboxes-includes.twig',
		array_merge(
			$model,
			array(
				'name_base'     => 'statics[post_translations]',
				'slot_settings' => $slot,
				'template_slug' => wpml_view_attr( wpml_view_attr( $model, 'slot_settings' ), 'template' ),
			)
		)
	);
	?>

	<h4><label><?php echo wpml_view_esc( wpml_view_attr( $texts, 'position_label' ) ); ?></label>  <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( wpml_view_attr( $strings, 'tooltips' ), 'post_translation_position' ) ) ) ); ?></h4>
	<ul>
		<li>
			<label for="wpml-ls-before-in-post-translations">
				<input type="checkbox" name="statics[post_translations][display_before_content]"
					   id="wpml-ls-before-in-post-translations"
					   class="wpml-checkbox-native"
					   value="1"<?php if ( wpml_view_attr( $slot, 'display_before_content' ) ) : ?> checked="checked"<?php endif; ?>><?php echo wpml_view_esc( wpml_view_attr( $texts, 'position_above' ) ); ?>

			</label>
		</li>
		<li>
			<label>
				<input type="checkbox"  name="statics[post_translations][display_after_content]"
					   id="wpml-ls-after-in-post-translations"
					   class="wpml-checkbox-native"
					   value="1"<?php if ( wpml_view_attr( $slot, 'display_after_content' ) ) : ?> checked="checked"<?php endif; ?>><?php echo wpml_view_esc( wpml_view_attr( $texts, 'position_below' ) ); ?>

			</label>
		</li>
	</ul>

	<?php if ( '' === $availability || false === $availability || null === $availability || array() === $availability ) : ?>
		<?php $availability_text = wpml_view_attr( $texts, 'default_alternative_languages_text' ); ?>
	<?php else : ?>
		<?php $availability_text = $availability; ?>
	<?php endif; ?>

	<h4><label><?php echo wpml_view_esc( wpml_view_attr( $texts, 'label_alternative_languages_text' ) ); ?></label>  <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( wpml_view_attr( $strings, 'tooltips' ), 'alternative_languages_text' ) ) ) ); ?></h4>
	<input type="text" class="js-wpml-ls-trigger-update"
		   name="statics[post_translations][availability_text]" value="<?php echo wpml_view_esc( $availability_text ); ?>" size="40">

</div>
