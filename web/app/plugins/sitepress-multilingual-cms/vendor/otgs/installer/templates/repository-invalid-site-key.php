<?php

namespace OTGS\Installer\Templates\Repository;

class InvalidSiteKey {

	public static function render( $model ) {
		?>
		<div class="otgs-installer-registered wp-clearfix">
			<div class="enter_site_key_wrap_js inline otgs-installer-notice otgs-installer-notice-<?php echo $model->repoId; ?> otgs-installer-notice-invalid-key">
				<span class="dashicons dashicons-warning otgs-installer-invalid-key-icon" aria-hidden="true"></span>
				<div class="otgs-installer-notice-content otgs-installer-invalid-key-content">
					<h2><?php esc_html_e( 'Your site key is no longer valid.', 'installer' ); ?></h2>
					<p>
						<?php
						/* translators: %s: product name. */
						echo esc_html( sprintf( __( 'Please register a new key to continue receiving updates for %s.', 'installer' ), $model->productName ) );
						?>
					</p>
				</div>
				<div class="otgs-installer-invalid-key-actions">
					<a class="enter_site_key_js button button-primary"
					   href="#"
						<?php if ( \WP_Installer::get_repository_hardcoded_site_key( $model->repoId ) ): ?>
							disabled
							aria-disabled="true"
							title="<?php printf( esc_attr__( "Site-key was set by %s, most likely in wp-config.php. Please remove the constant before attempting to register.", 'installer' ), 'OTGS_INSTALLER_SITE_KEY_' . strtoupper( $model->repoId ) ); ?>"
						<?php endif; ?>
					>
						<?php echo esc_html( sprintf( __( 'Register %s', 'installer' ), $model->productName ) ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php

		echo Register::getRegistrationForm( $model );
	}
}
