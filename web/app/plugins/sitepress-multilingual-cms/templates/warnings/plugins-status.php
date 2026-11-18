<?php
defined( 'ABSPATH' ) || exit;

$wpml_view_strings = isset( $strings ) ? $strings : null;
$wpml_view_shared = isset( $shared ) ? $shared : null;
$wpml_view_data = isset( $data ) ? $data : null;
?>
	<h3>
		<?php echo wpml_view_raw( wpml_view_attr( $wpml_view_strings, 'title' ) ); ?>

	</h3>

	<?php if ( wpml_view_attr( $wpml_view_strings, 'message' ) ) : ?>
		<p>
			<?php echo wpml_view_raw( wpml_view_attr( $wpml_view_strings, 'message' ) ); ?>

		</p>
	<?php endif; ?>

	<div>
		<div>
			<ul class="wpml-status-list wpml-plugins-status-list">
				<?php foreach ( is_array( wpml_view_attr( $wpml_view_data, 'requirements' ) ) ? wpml_view_attr( $wpml_view_data, 'requirements' ) : array() as $wpml_view_requirement ) : ?>
					<li>
						<?php if ( wpml_view_attr( $wpml_view_requirement, 'missing' ) ) : ?>
							<i class="otgs-ico-warning"></i>
						<?php else: ?>
							<i class="otgs-ico-ok"></i>
						<?php endif; ?>
						<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_requirement, 'name' ) ); ?>

						<?php if ( wpml_view_attr( $wpml_view_requirement, 'missing' ) && ! wpml_view_attr( $wpml_view_requirement, 'disabled' ) ) : ?>
							(<a href="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_shared, 'install_link' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'install' ) ); ?></a> | <a href="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_requirement, 'url' ) ); ?>" target="_blank"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'download' ) ); ?></a>)
						<?php endif; ?>
                        <?php if ( wpml_view_attr( $wpml_view_requirement, 'disabled' ) ) : ?>
                            (<a data-wpml-activated="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'activated' ) ); ?>" data-wpml-error="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'error' ) ); ?>" data-wpml-activating="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'activating' ) ); ?>" data-wpml-disabled-plugin-nonce="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_requirement, 'activation_nonce' ) ); ?>" class="wpml-requirements-disabled" data-plugin-file="<?php echo wpml_view_esc( wpml_view_attr( $wpml_view_requirement, 'plugin_file' ) ); ?>" href="#"><?php echo wpml_view_esc( wpml_view_attr( $wpml_view_strings, 'activate' ) ); ?></a>)
                        <?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
