<?php
global $sitepress;

if ( ( ! isset( $sitepress_settings['existing_content_language_verified'] ) ||
	  ! $sitepress_settings['existing_content_language_verified'] ) ||
	  2 > count( $sitepress->get_active_languages() ) ) {
	return;
}

?>

<div class="wrap">
	<h2><?php _e( 'Theme and plugins localization', 'sitepress' ); ?></h2>

	<?php
	if ( \WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\AdminTextsTranslationController::rendersLocalizationSection() ) :
		$wpml_scan_url = admin_url( \WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\AdminTextsTranslationController::localizationSectionUrl() );
		?>
		<p class="wpml-localization-scan-link">
			<?php
			printf(
				/* translators: Line under the "Theme and plugins localization" heading in WPML → Settings → Compatibility. %s: a link to the Admin Texts Translation page, whose text is that page's name. */
				esc_html__( 'Scan your theme and plugins for strings on the %s page.', 'sitepress' ),
				'<a href="' . esc_url( $wpml_scan_url ) . '">' . esc_html__( 'Admin Texts Translation', 'sitepress' ) . '</a>'
			);
			?>
		</p>
	<?php endif; ?>

	<?php

		do_action( 'icl_custom_localization_type' );

		do_action( 'wpml_custom_localization_type' );
	?>

	<?php do_action( 'icl_menu_footer' ); ?>
</div>
