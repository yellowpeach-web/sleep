<?php

namespace WPML\DataSharing;

use WPML\OutboundLinks\OutboundLinks;

class DataSharingSection {

	const SECTION_ID = 'ml-content-setup-sec-reporting';

	const RENDER_ACTION = 'otgs_installer_render_local_components_setting';

	const OPTION_KEY = 'otgs_share_local_components';

	const REPOSITORY = 'wpml';

	const SAVE_ACTION = 'otgs_save_setting_share_local_components';

	const CHECKBOX_ID = 'wpml-data-sharing-opt-in';

	public static function isAvailable() {
		return (bool) has_action( self::RENDER_ACTION );
	}

	public static function heading() {
		/* translators: Heading of the Data sharing section (WPML → Settings and the Multilingual Content Setup page): the section says what WPML sends to wpml.org. */
		return __( 'Information shared with wpml.org', 'sitepress' );
	}

	public static function strings() {
		return array(
			/* translators: Data sharing (setup wizard and WPML → Settings): what WPML sends to wpml.org whatever the opt-in below says. */
			'always' => __( "WPML sends wpml.org your site's WPML version, setup and translation settings, so that your account, updates and support work.", 'sitepress' ),
			/* translators: Data sharing (setup wizard and WPML → Settings): the label of the opt-in checkbox, which sends the content statistics and the list of the site's plugins and themes. */
			'label'  => __( 'Also send content statistics and your list of plugins and themes, to improve WPML and get maintenance alerts.', 'sitepress' ),
			/* translators: Data sharing (setup wizard and WPML → Settings): the help line under the opt-in checkbox, saying what content statistics are and what the list of plugins and themes is used for. */
			'help'   => __( 'Content statistics are counts: how many posts and pages you have, how much text, and how much of it is translated into each language. The content itself is never sent. The list of plugins and themes lets us warn you about known compatibility problems.', 'sitepress' ),
		);
	}

	public static function isOptedIn() {
		$stored = get_option( self::OPTION_KEY, array() );

		return is_array( $stored ) && ! empty( $stored[ self::REPOSITORY ] );
	}

	public static function render( $heading_tag = 'h3' ) {
		$heading_tag = 'h2' === $heading_tag ? 'h2' : 'h3';
		$strings     = self::strings();
		$help_id     = self::CHECKBOX_ID . '-help';
		$nonce       = wp_create_nonce( self::SAVE_ACTION );
		$links       = self::links();
		?>
		<div class="wpml-section wpml-section-wpml-theme-and-plugins-reporting" id="<?php echo esc_attr( self::SECTION_ID ); ?>">
			<div class="wpml-section-header">
				<?php if ( 'h2' === $heading_tag ) : ?>
					<h2><?php echo esc_html( self::heading() ); ?></h2>
				<?php else : ?>
					<h3><?php echo esc_html( self::heading() ); ?></h3>
				<?php endif; ?>
			</div>
			<div class="wpml-section-content">
				<div class="otgs-installer-component-setting wpml-data-sharing" style="max-width: 660px;">
					<p class="wpml-data-sharing__always"><?php echo esc_html( $strings['always'] ); ?></p>
					<p class="wpml-data-sharing__opt-in" style="padding-left: 24px; text-indent: -24px;">
						<input type="checkbox" id="<?php echo esc_attr( self::CHECKBOX_ID ); ?>" class="js-otgs-components-report-user-choice" value="1"<?php echo self::isOptedIn() ? ' checked="checked"' : ''; ?> aria-describedby="<?php echo esc_attr( $help_id ); ?>" data-nonce-action="<?php echo esc_attr( self::SAVE_ACTION ); ?>" data-nonce-value="<?php echo esc_attr( $nonce ); ?>" data-repo="<?php echo esc_attr( self::REPOSITORY ); ?>" />
						<label for="<?php echo esc_attr( self::CHECKBOX_ID ); ?>"><?php echo esc_html( $strings['label'] ); ?></label>
						<span class="spinner otgs-components-report-setting-spinner" style="float: none; margin-top: 0;"></span>
					</p>
					<p class="description wpml-data-sharing__help" id="<?php echo esc_attr( $help_id ); ?>" style="margin-left: 24px;"><?php echo esc_html( $strings['help'] ); ?></p>
					<?php foreach ( $links as $link ) : ?>
						<p class="otgs-installer-component-privacy-policy">
							<a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener" class="otgs-external-link"><?php echo esc_html( $link['text'] ); ?></a>
						</p>
					<?php endforeach; ?>
				</div>
			</div>
		</div><!-- #ml-content-setup-sec-reporting -->
		<?php
	}

	private static function links() {
		$utm = array(
			'medium'   => 'settings',
			'campaign' => 'data-sharing',
		);

		return array(
			array(
				'url'  => OutboundLinks::to( 'https://wpml.org/documentation/privacy-policy-and-gdpr-compliance/', $utm ),
				'text' => __( 'Privacy and data usage policy', 'sitepress' ),
			),
			array(
				'url'  => OutboundLinks::to( 'https://wpml.org/documentation/privacy-policy-and-gdpr-compliance/optional-data-sharing/', $utm ),
				'text' => __( 'Full details of the info we’re proposing to share', 'sitepress' ),
			),
		);
	}
}
