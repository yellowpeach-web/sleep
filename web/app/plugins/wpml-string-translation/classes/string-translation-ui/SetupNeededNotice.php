<?php

namespace WPML\ST\Main;

class SetupNeededNotice {

	const SETUP_PAGE = 'sitepress-multilingual-cms/menu/setup.php';

	public static function render() {
		$setup_page = defined( 'WPML_PLUGIN_FOLDER' ) ? WPML_PLUGIN_FOLDER . '/menu/setup.php' : self::SETUP_PAGE;
		?>
		<div class="wrap wpml-st-setup-notice">
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'You need to configure WPML before you can start translating.', 'sitepress' ); ?></p>
				<p>
					<a class="button-primary configure-wpml" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $setup_page ) ); ?>">
						<?php /* translators: Heading of the screen where WPML is set up, and the text of the link that opens it. Verb phrase, imperative. */ esc_html_e( 'Configure WPML', 'sitepress' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
}
