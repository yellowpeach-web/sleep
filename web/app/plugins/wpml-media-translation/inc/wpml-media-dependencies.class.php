<?php
class WPML_Media_Dependencies {

	const APP_PRODUCTION_ORIGIN = 'https://app.wpml.org';

	function check() {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) || ICL_PLUGIN_INACTIVE ) {
			add_action( 'admin_notices', array( $this, '_no_wpml_warning' ) );

			return false;
		}

		if ( ! WPML_Core_Version_Check::is_ok( WPML_MEDIA_PATH . '/wpml-dependencies.json' ) ) {
			add_action( 'admin_notices', array( $this, '_old_wpml_warning' ) );

			return false;
		}

		return true;
	}

	function _no_wpml_warning() {
		?>
		<div class="notice notice-error wpml-admin-notice wpml-media-inactive wpml-inactive">
			<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: Admin notice shown when WPML Media Translation is active but WPML core is missing. %1$s: the URL of the client's wpml.org account downloads page, which fills the first link. %2$s: the URL of this site's Plugins screen, which fills the second link. */
					__( 'WPML Media Translation is active, but it needs WPML Multilingual CMS, which is not installed or not active. Media Translation stays off until WPML runs. Download WPML from <a href="%1$s">your wpml.org account</a> and activate it on the <a href="%2$s">Plugins screen</a>.', 'wpml-media' ),
					esc_url( self::account_downloads_url() ),
					esc_url( admin_url( 'plugins.php' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
			</p>
		</div>
		<?php
	}

	function _old_wpml_warning() {
		?>
		<div class="notice notice-error wpml-admin-notice wpml-media-inactive wpml-outdated">
			<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: Admin notice shown when the installed WPML core is older than WPML Media Translation needs. %1$s: Media Translation's own version. %2$s: the minimum WPML version it needs. %3$s: the WPML version this site runs. %4$s: the URL of this site's Plugins screen, which fills the link. */
					__( 'WPML Media Translation %1$s needs WPML Multilingual CMS %2$s or newer. This site runs WPML %3$s, so Media Translation stays off until WPML is updated. Update WPML on the <a href="%4$s">Plugins screen</a>.', 'wpml-media' ),
					self::plugin_version(),
					self::required_core_version(),
					self::installed_core_version(),
					esc_url( admin_url( 'plugins.php' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			);
			?>
			</p>
		</div>
		<?php
	}

	private static function account_downloads_url() {
		$path = '/account/downloads';

		if ( class_exists( '\WPML\OutboundLinks\OutboundLinks' ) ) {
			return \WPML\OutboundLinks\OutboundLinks::to(
				self::APP_PRODUCTION_ORIGIN . $path,
				array(
					'medium'   => 'notice',
					'campaign' => 'requirements',
				)
			);
		}

		return self::app_origin() . $path
			. '?utm_source=wpml-plugin&utm_medium=notice&utm_campaign=requirements';
	}

	private static function app_origin() {
		if ( ! defined( 'WPML_ORG_ORIGIN' ) || ! is_string( WPML_ORG_ORIGIN ) ) {
			return self::APP_PRODUCTION_ORIGIN;
		}

		$origin = rtrim( trim( (string) WPML_ORG_ORIGIN ), '/' );

		if ( '' === $origin ) {
			return self::APP_PRODUCTION_ORIGIN;
		}

		if ( ! preg_match( '#^https?://#i', $origin ) ) {
			$origin = 'https://' . $origin;
		}

		if ( ! preg_match( '#^(https?://)(.+)$#i', $origin, $matches ) ) {
			return self::APP_PRODUCTION_ORIGIN;
		}

		if ( 'wpml.org' === strtolower( $matches[2] ) ) {
			return self::APP_PRODUCTION_ORIGIN;
		}

		if ( 0 === strpos( strtolower( $matches[2] ), 'app.' ) ) {
			return $origin;
		}

		return $matches[1] . 'app.' . $matches[2];
	}

	private static function plugin_version() {
		return defined( 'WPML_MEDIA_VERSION' ) ? (string) WPML_MEDIA_VERSION : '';
	}

	private static function required_core_version() {
		$file = WPML_MEDIA_PATH . '/wpml-dependencies.json';

		if ( ! is_readable( $file ) ) {
			return '';
		}

		$bundle = json_decode( (string) file_get_contents( $file ), true );

		return is_array( $bundle ) && isset( $bundle['sitepress-multilingual-cms'] )
			? (string) $bundle['sitepress-multilingual-cms']
			: '';
	}

	private static function installed_core_version() {
		return defined( 'ICL_SITEPRESS_VERSION' ) ? (string) ICL_SITEPRESS_VERSION : '';
	}
}
