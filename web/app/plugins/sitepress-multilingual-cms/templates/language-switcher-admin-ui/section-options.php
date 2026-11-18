<?php
defined( 'ABSPATH' ) || exit;

$settings          = wpml_view_attr( $model, 'settings' );
$strings           = wpml_view_attr( $model, 'strings' );
$options           = wpml_view_attr( $strings, 'options' );
$tooltips          = wpml_view_attr( $strings, 'tooltips' );
$ordered_languages = wpml_view_attr( $model, 'ordered_languages' );

$hide_backwards_compatibility = wpml_view_attr( $model, 'hide_backwards_compatibility' );
?>
<div class="js-wpml-ls-option wpml-ls-language_order">
	<h4><label><?php echo wpml_view_esc( wpml_view_attr( $options, 'label_language_order' ) ); ?></label> <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( $tooltips, 'languages_order' ) ) ) ); ?>
		<?php $wpml_view->partial( 'save-notification.twig', $model ); ?>
	</h4>
	<p class="explanation-text"><?php echo wpml_view_esc( wpml_view_attr( $options, 'tip_drag_languages' ) ); ?></p>
	<ul id="wpml-ls-languages-order" class="wpml-ls-languages-order">
		<?php foreach ( is_array( $ordered_languages ) ? $ordered_languages : array() as $language ) : ?>
		<li class="js-wpml-languages-order-item" data-language-code="<?php echo wpml_view_esc( wpml_view_attr( $language, 'code' ) ); ?>">
            <?php echo wpml_view_raw( wpml_view_attr( $language, 'flag_img' ) ); ?> <?php echo wpml_view_esc( wpml_view_attr( $language, 'display_name' ) ); ?><input type="hidden" name="languages_order[]" value="<?php echo wpml_view_esc( wpml_view_attr( $language, 'code' ) ); ?>">
		</li>
		<?php endforeach; ?>
	</ul>
</div>

<div class="js-wpml-ls-option wpml-ls-languages_with_no_translations">
	<h4><label><?php echo wpml_view_esc( wpml_view_attr( $options, 'label_languages_with_no_translations' ) ); ?> <?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( $tooltips, 'languages_without_translation' ) ) ) ); ?>
		</label>
		<?php $wpml_view->partial( 'save-notification.twig', $model ); ?>
	</h4>
	<ul>
		<li>
			<label for="link_empty_off">
				<input type="radio" name="link_empty" id="link_empty_off"
					   class="wpml-radio-native js-wpml-ls-trigger-save"
					   value="0"<?php if ( ! wpml_view_attr( $settings, 'link_empty' ) ) : ?> checked="checked"<?php endif; ?>><?php echo wpml_view_esc( wpml_view_attr( $options, 'option_skip_link' ) ); ?>

			</label>
		</li>
		<li>
			<label for="link_empty_on">
				<input type="radio" name="link_empty" id="link_empty_on"
					   class="wpml-radio-native js-wpml-ls-trigger-save"
					   value="1"<?php if ( wpml_view_attr( $settings, 'link_empty' ) ) : ?> checked="checked"<?php endif; ?>><?php echo wpml_view_esc( wpml_view_attr( $options, 'option_link_home' ) ); ?>

			</label>
		</li>
	</ul>
</div>

<div class="js-wpml-ls-option wpml-ls-preserve_url_args">
	<p class="wpml-ls-form-line">
		<?php if ( ! wpml_view_attr( $settings, 'copy_parameters' ) ) : ?><a href="#" class="js-wpml-ls-toggle-once"><?php endif; ?>
			<label for="copy_parameters">
				<?php echo wpml_view_esc( wpml_view_attr( $options, 'label_preserve_url_args' ) ); ?><?php if ( ! wpml_view_attr( $settings, 'copy_parameters' ) ) : ?><span class="otgs-ico-caret-down js-arrow-toggle"></span><?php endif; ?></label><?php if ( ! wpml_view_attr( $settings, 'copy_parameters' ) ) : ?></a><?php endif; ?>

		<?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( $tooltips, 'preserve_url_arguments' ) ) ) ); ?>

		<?php $wpml_view->partial( 'save-notification.twig', $model ); ?>

		<input type="text" size="100" id="copy_parameters" name="copy_parameters"
			   aria-describedby="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $tooltips, 'preserve_url_arguments' ), 'id' ) ); ?>"
			   value="<?php echo wpml_view_esc( wpml_view_attr( $settings, 'copy_parameters' ) ); ?>"
			   class="js-wpml-ls-trigger-save js-wpml-ls-trigger-need-save<?php if ( ! wpml_view_attr( $settings, 'copy_parameters' ) ) : ?> js-wpml-ls-toggle-target hidden<?php endif; ?>">
	</p>
</div>

<div class="js-wpml-ls-option wpml-ls-additional_css">
	<p class="wpml-ls-form-line">
		<?php if ( ! wpml_view_attr( $settings, 'additional_css' ) ) : ?><a href="#" class="js-wpml-ls-toggle-once"><?php endif; ?>
			<label for="additional_css">
				<?php echo wpml_view_esc( wpml_view_attr( $options, 'label_additional_css' ) ); ?><?php if ( ! wpml_view_attr( $settings, 'additional_css' ) ) : ?><span class="otgs-ico-caret-down js-arrow-toggle"></span><?php endif; ?></label><?php if ( ! wpml_view_attr( $settings, 'additional_css' ) ) : ?></a><?php endif; ?>


		<?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( $tooltips, 'additional_css' ) ) ) ); ?>

		<?php $wpml_view->partial( 'save-notification.twig', $model ); ?>

		<textarea id="additional_css" name="additional_css" rows="4" aria-describedby="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $tooltips, 'additional_css' ), 'id' ) ); ?>"
				  class="large-text js-wpml-ls-additional-css js-wpml-ls-trigger-save js-wpml-ls-trigger-need-save<?php if ( ! wpml_view_attr( $settings, 'additional_css' ) ) : ?> js-wpml-ls-toggle-target hidden<?php endif; ?>"><?php echo wpml_view_esc( wpml_view_attr( $settings, 'additional_css' ) ); ?></textarea>
	</p>
</div>

<div class="js-wpml-ls-option wpml-ls-backwards_compatibility">
	<div class="wpml-ls-form-line">
		<?php if ( ( ! wpml_view_attr( $settings, 'migrated' ) ) == 1 ) : ?>
			<?php $hide_backwards_compatibility = true; ?>
		<?php endif; ?>

		<?php if ( $hide_backwards_compatibility ) : ?><a href="#" class="js-wpml-ls-toggle-once"><?php endif; ?>
			<label>
				<?php echo wpml_view_esc( wpml_view_attr( $options, 'label_migrated_toggle' ) ); ?><?php if ( $hide_backwards_compatibility ) : ?><span class="otgs-ico-caret-down js-arrow-toggle"></span><?php endif; ?></label><?php if ( $hide_backwards_compatibility ) : ?></a><?php endif; ?>

		<?php $wpml_view->partial( 'tooltip.twig', array_merge( $model, array( 'content' => wpml_view_attr( $tooltips, 'backwards_compatibility' ) ) ) ); ?>

		<?php $wpml_view->partial( 'save-notification.twig', $model ); ?>

		<p<?php if ( $hide_backwards_compatibility ) : ?> class="js-wpml-ls-toggle-target hidden"<?php endif; ?>>
			<input type="checkbox" id="wpml-ls-backwards-compatibility" name="migrated" aria-describedby="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $tooltips, 'backwards_compatibility' ), 'id' ) ); ?>"
				   value="0"<?php if ( 0 == wpml_view_attr( $settings, 'migrated' ) ) : ?> checked="checked"<?php endif; ?>
				   class="wpml-checkbox-native js-wpml-ls-migrated js-wpml-ls-trigger-save js-wpml-ls-trigger-need-save">

			<label for="wpml-ls-backwards-compatibility">
				<?php echo wpml_view_esc( wpml_view_attr( $options, 'label_skip_backwards_compatibility' ) ); ?>

			</label>
		</p>

	</div>
</div>
