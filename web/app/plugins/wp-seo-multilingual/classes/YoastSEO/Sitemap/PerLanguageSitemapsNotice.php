<?php

namespace WPML\WPSEO\YoastSEO\Sitemap;

use SitePress;
use WPML\Settings\LanguageNegotiation;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings\UrlsAndSeoController;

class PerLanguageSitemapsNotice implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const SETTINGS_PAGE    = 'tm/menu/settings';
	const SETTINGS_SECTION = 'urls-and-seo';
	const SITEMAP_INDEX    = 'sitemap_index.xml';
	const STYLE_HANDLE     = 'wpseoml-per-language-sitemaps';

	const SHOW_OPEN_UP_TO = 6;

	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks(): void {
		if ( ! $this->isUrlsAndSeoScreen() ) {
			return;
		}

		add_action( 'admin_init', [ $this, 'registerPanel' ] );
	}

	public function registerPanel(): void {
		if ( ! LanguageNegotiation::isDomain() || ! $this->areSitemapsEnabled() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueStyle' ] );

		$sectionHook = self::coreSectionHook();
		add_action( null === $sectionHook ? 'admin_notices' : $sectionHook, [ $this, 'render' ] );
	}

	public static function coreSectionHook(): ?string {
		$constant = UrlsAndSeoController::class . '::AFTER_SECTIONS_HOOK';

		return defined( $constant ) ? (string) constant( $constant ) : null;
	}

	public function enqueueStyle(): void {
		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( '', WPSEOML_PLUGIN_PATH . '/plugin.php' ) . '/res/css/per-language-sitemaps.css',
			[],
			WPSEOML_VERSION
		);
	}

	public function getSitemapUrls(): array {
		$sitemaps = [];

		foreach ( (array) $this->sitepress->get_active_languages() as $code => $language ) {
			$code = isset( $language['code'] ) ? (string) $language['code'] : (string) $code;
			if ( '' === $code ) {
				continue;
			}

			$home = apply_filters( 'wpml_permalink', home_url( '/' ), $code );
			if ( ! is_string( $home ) || '' === $home ) {
				continue;
			}

			$sitemaps[ $code ] = [
				'name' => isset( $language['display_name'] ) && '' !== $language['display_name']
					? (string) $language['display_name']
					: $code,
				'url'  => trailingslashit( $home ) . self::SITEMAP_INDEX,
			];
		}

		return $sitemaps;
	}

	public function render(): void {
		$sitemaps = $this->getSitemapUrls();
		if ( ! $sitemaps ) {
			return;
		}

		$count = count( $sitemaps );

		$wrapper = null === self::coreSectionHook()
			? 'wpseoml-sitemaps notice notice-info'
			: 'wpseoml-sitemaps wpml-section';

		?>
		<div class="<?php echo esc_attr( $wrapper ); ?>">
			<details class="wpseoml-sitemaps__disclosure" <?php echo $count <= self::SHOW_OPEN_UP_TO ? 'open' : ''; ?>>
				<summary class="wpseoml-sitemaps__summary">
					<span class="wpseoml-sitemaps__heading"><?php /* translators: Heading of the box on the Yoast SEO settings screen that lists one sitemap per language. A noun phrase, not an instruction. */ esc_html_e( 'Sitemaps for each language', 'wp-seo-multilingual' ); ?></span>
					<span class="wpseoml-sitemaps__count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
				</summary>
				<p class="wpseoml-sitemaps__intro">
					<?php /* translators: Text inside the "Sitemaps for each language" box on the Yoast SEO settings screen, above the list of addresses. "Google Search Console" is a product name and stays in English. */ esc_html_e( 'Each language runs on its own domain, so each one has its own sitemap. Submit all of them to Google Search Console.', 'wp-seo-multilingual' ); ?>
				</p>
				<ul class="wpseoml-sitemaps__list">
					<?php foreach ( $sitemaps as $sitemap ) : ?>
						<li class="wpseoml-sitemaps__item">
							<span class="wpseoml-sitemaps__language"><?php echo esc_html( $sitemap['name'] ); ?></span>
							<a class="wpseoml-sitemaps__url" href="<?php echo esc_url( $sitemap['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $sitemap['url'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		</div>
		<?php
	}

	private function isUrlsAndSeoScreen(): bool {
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$section = isset( $_GET['section'] ) && is_string( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		return self::SETTINGS_PAGE === $page && self::SETTINGS_SECTION === $section;
	}

	private function areSitemapsEnabled(): bool {
		if ( ! class_exists( '\WPSEO_Options' ) ) {
			return false;
		}

		return (bool) \WPSEO_Options::get( 'enable_xml_sitemap', false );
	}
}
