<?php
defined( 'ABSPATH' ) || exit;

$theme_config_file = wpml_view_attr( $model, 'theme_config_file' );
?>
<div class="wpml-section" id="wpml_ls_reset">
	<div class="wpml-section-header">
		<h3><?php echo wpml_view_esc( wpml_view_attr( $model, 'title' ) ); ?></h3>
	</div>
	<div class="wpml-section-content">
		<p><?php echo wpml_view_raw( wpml_view_attr( $model, 'description' ) ); ?></p>

		<?php if ( $theme_config_file ) : ?>
			<p class="explanation-text"><?php echo wpml_view_raw( wpml_view_attr( $model, 'explanation_text' ) ); ?></p>
		<?php endif; ?>

		<p class="buttons-wrap">
			<a
				class="button-secondary wpml-button base-btn wpml-button--outlined"
				onclick="if(!confirm('<?php echo wpml_view_esc( wpml_view_attr( $model, 'confirmation_message' ) ); ?>')) return false;"
			   href="<?php echo wpml_view_esc( wpml_view_attr( $model, 'restore_page_url' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $model, 'restore_button_label' ) ); ?><?php if ( $theme_config_file ) : ?> *<?php endif; ?></a>
		</p>
	</div>
</div>
