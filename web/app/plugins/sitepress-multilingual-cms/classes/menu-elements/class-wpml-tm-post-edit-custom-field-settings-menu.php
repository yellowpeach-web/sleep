<?php

class WPML_TM_Post_Edit_Custom_Field_Settings_Menu {

	private $setting_factory;

	private $post;

	private $rendered = false;

	public function __construct( &$settings_factory, $post ) {
		$this->setting_factory = &$settings_factory;
		$this->post            = $post;
	}

	public function render() {
		$custom_keys = get_post_custom_keys( $this->post->ID );
		$custom_keys = $this->setting_factory->filter_custom_field_keys( $custom_keys );
		ob_start();
		if ( 0 !== count( $custom_keys ) ) {
			?>
			<table class="widefat">
				<thead>
				<tr>
					<th colspan="2"><?php /* translators: Heading above the list of extra fields of a post, in the translation settings. */ esc_html_e( 'Custom fields', 'sitepress' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $custom_keys as $cfield ) {
					$field_setting = $this->setting_factory->post_meta_setting( $cfield );
					if ( $field_setting->excluded() ) {
						continue;
					}
					$this->rendered = true;
					$radio_disabled = $field_setting->get_html_disabled();
					$status         = (int) $field_setting->status();
					$states         = array(
						/* translators: Option in the dropdown that says what happens to a field: leave it out of the translation. Verb phrase, imperative. */
						array( 'value' => WPML_IGNORE_CUSTOM_FIELD, 'text' => __( "Don't translate", 'sitepress' ) ),
						/* translators: Option in the dropdown that says what happens to a field: put the same value in every language, every time the post is saved. Verb, imperative. */
						array( 'value' => WPML_COPY_CUSTOM_FIELD, 'text' => __( "Copy", 'sitepress' ) ),
						/* translators: Option in the dropdown that says what happens to a field: put the value in the translation once, and leave it alone afterwards. Verb phrase, imperative. */
						array( 'value' => WPML_COPY_ONCE_CUSTOM_FIELD, 'text' => __( "Copy once", 'sitepress' ) ),
						/* translators: Option in the dropdown that says what happens to a field, and the heading of the column of the post editing screen where a translation is started: the text is translated. Verb, imperative. */
						array( 'value' => WPML_TRANSLATE_CUSTOM_FIELD, 'text' => __( "Translate", 'sitepress' ) ),
					);
					?>
					<tr>
						<td id="icl_mcs_cf_<?php echo esc_attr( base64_encode( $cfield ) ); ?>">
							<div class="icl_mcs_cf_name">
								<?php echo esc_html( $cfield ); ?>
							</div>
							<?php
							$cfield_description = apply_filters( 'wpml_post_edit_settings_custom_field_description', "", $cfield, $this->post->ID );
							if ( !empty( $cfield_description ) ) {
								printf( '<div class="icl_mcs_cf_description">%s</div>', esc_html( $cfield_description ) );
							}
							?>
						</td>
						<td align="right">
							<?php
							foreach ( $states as $state ) {
								$checked = $status == $state['value'] ? ' checked="checked"' : '';
								?>
									<label>
										<input class="icl_mcs_cfs"
									              name="icl_mcs_cf_<?php echo esc_attr( base64_encode( $cfield ) ); ?>"
									              type="radio"
									              value="<?php echo esc_attr( (string) $state['value'] ); ?>" <?php echo esc_attr( $radio_disabled . $checked ); ?>
										/>
										<?php echo esc_html( $state['text'] ) ?>
									</label>&nbsp;
								<?php
							}
							foreach ( \WPML\TM\Settings\PreferenceOutcomeSentences::all() as $mode => $sentence ) {
								?>
								<p class="icl_mcs_cf_outcome"
								   data-value="<?php echo esc_attr( (string) $mode ); ?>"<?php echo $status === (int) $mode ? '' : ' hidden'; ?>><?php echo esc_html( $sentence ); ?></p>
								<?php
							}
							?>
						</td>
					</tr>
					<?php
				}
				?>
				</tbody>
			</table>
			<br/>
			<?php
		}
		return ob_get_clean();
	}

	public function is_rendered() {

		return $this->rendered;
	}
}
