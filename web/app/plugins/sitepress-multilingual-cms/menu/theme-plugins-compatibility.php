<div class="wrap">
	<h3><?php _e( 'Theme and Plugins compatibility with WPML', 'sitepress' ); ?></h3>

	<p><?php _e( 'Configuration for compatibility between your active plugins and theme is updated automatically on daily basis.', 'sitepress' ); ?></p>
	<div id="icl_theme_plugins_compatibility">
		<?php
		$wpml_conf_upd_span = '<span id="wpml_conf_upd">' . esc_html( WPML_Config_Update_Integrator::last_checked_text() ) . '</span>';
		if ( (int) get_option( 'wpml_config_index_updated' ) > 0 ) {
			/* translators: Line above the list of compatible themes and plugins. %s: the date the list was last brought up to date, in a tag of its own. */
			$wpml_conf_upd_line = sprintf( __( 'Last checked on %s', 'sitepress' ), $wpml_conf_upd_span );
		} else {
			/* translators: Line above the list of compatible themes and plugins while the list was never fetched on this site. %s: the word "Never", in a tag of its own. */
			$wpml_conf_upd_line = sprintf( __( 'Last checked: %s', 'sitepress' ), $wpml_conf_upd_span );
		}
		?>
		<p><?php echo wp_kses_post( $wpml_conf_upd_line ); ?></p>

		<?php /* translators: Button label: fetch the list of compatible themes and plugins again. Verb, imperative, not the noun "an update". */ ?>
		<input class="button" id="update_wpml_config" value="<?php echo __( 'Update', 'sitepress' ); ?>" type="button" style="float:left;"/>

	</div>
</div>

<script type="text/javascript">
	jQuery(document).ready(function ($) {
		$('#update_wpml_config').click(function () {
			var el = $(this);
			var ajaxLoader = $('<span class="spinner" style="float:left"></span>');
			ajaxLoader.insertAfter(el).show();
			el.prop('disabled', true);
			jQuery.ajax({
				type: "post",
				url: ajaxurl,
				data: {
					action: "update_wpml_config_index",
					_icl_nonce: "<?php echo wp_create_nonce( 'icl_theme_plugins_compatibility_nonce' ); ?>",
				},
				success: function (response) {
					if (response)
						$('#wpml_conf_upd').html(response);
				},
				complete: function () {
					ajaxLoader.remove();
					el.prop('disabled', false);
				}
			});
		});
	});
</script>
