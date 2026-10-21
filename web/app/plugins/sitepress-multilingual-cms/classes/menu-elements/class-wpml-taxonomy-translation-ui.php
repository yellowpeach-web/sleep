<?php

class WPML_Taxonomy_Translation_UI {

	private $sitepress;
	private $taxonomy;
	private $tax_selector;
	private $screen_options;

	public function __construct(
		SitePress $sitepress,
		$taxonomy = '',
		array $args = array(),
		?WPML_UI_Screen_Options_Factory $screen_options_factory = null
	) {
		$this->sitepress    = $sitepress;
		$this->tax_selector = isset( $args['taxonomy_selector'] ) ? $args['taxonomy_selector'] : true;
		$this->taxonomy     = $taxonomy ? $taxonomy : false;

		if ( $screen_options_factory ) {
			$help_title = function() {
				/* translators: Title of the screen where the names of categories, tags and other groupings are translated. */
				return esc_html__( 'Taxonomy Translation', 'sitepress' );
			};
			$help_text  = [ $this, 'get_help_text' ];

			$this->screen_options = $screen_options_factory->create_pagination(
				'taxonomy_translation_per_page',
				ICL_TM_DOCS_PER_PAGE
			);
			$screen_options_factory->create_help_tab(
				'taxonomy_translation_help_tab',
				$help_title,
				$help_text
			);
		}
	}

	public function render() {
		WPML_Taxonomy_Translation_Table_Display::enqueue_taxonomy_table_resources( $this->sitepress );
		$output = '<div class="wrap">';
		if ( $this->taxonomy ) {
			$output .= '<input type="hidden" id="tax-preselected" value="' . $this->taxonomy . '">';
		}
		if ( ! $this->tax_selector ) {
			$output .= '<input type="hidden" id="tax-selector-hidden" value="1"/>';
		}
		if ( $this->tax_selector ) {
			/* translators: Title of the screen where the names of categories, tags and other groupings are translated. */
			$output .= '<h1>' . esc_html__( 'Taxonomy Translation', 'sitepress' ) . '</h1>';
			$output .= '<br/>';
		}
		$output .= '<div id="wpml_tt_taxonomy_translation_wrap" data-items_per_page="'
				   . $this->get_items_per_page()
				   . '">';
		$output .= '<div class="loading-content"><span class="spinner" style="visibility: visible"></span></div>';
		$output .= '</div>';
		do_action( 'icl_menu_footer' );
		echo $output . '</div>';
	}

	private function get_items_per_page() {
		$items_per_page = 10;
		if ( $this->screen_options ) {
			$items_per_page = $this->screen_options->get_items_per_page();
		}

		return $items_per_page;
	}

	public function get_help_text() {
		/* translators: Link text inside the sentence "...our documentation page about translating post categories and custom taxonomies". It starts in lower case because it sits inside that sentence. */
		$translate_taxonomies_link_title = esc_html__(
			'translating post categories and custom taxonomies',
			'sitepress'
		);
		$translate_taxonomies_url        = \WPML\OutboundLinks\OutboundLinks::to(
			'https://wpml.org/documentation/translating-your-contents/taxonomy/',
			array(
				'medium'   => 'settings',
				'campaign' => 'taxonomy-translation',
			)
		);
		$translate_taxonomies_link       = '<a href="' . esc_url( $translate_taxonomies_url ) . '" target="_blank">'
										   . $translate_taxonomies_link_title
										   . '</a>';

		$help_sentences   = array();
		$help_sentences[] = esc_html__(
			"WPML allows you to easily translate your site's taxonomies. Only taxonomies marked as translatable will be available for translation. Select the taxonomy in the dropdown menu and then use the list of taxonomy terms that appears to translate them.",
			'sitepress'
		);
		if ( defined( 'WPML_ST_VERSION' ) ) {
			/* translators: Name of a section of the Translation Management settings, where the names of categories, tags and other groupings are translated; also the link text inside the sentence "Set it in Taxonomies Translation." It is a section name, so it keeps its capitals. */
			$taxonomies_settings_link_title = esc_html__( 'Taxonomies Translation', 'sitepress' );
			$taxonomies_settings_url        = admin_url( 'admin.php?page=tm/menu/settings&section=taxonomies' );
			$taxonomies_settings_link       = '<a href="' . esc_url( $taxonomies_settings_url ) . '">'
											  . $taxonomies_settings_link_title
											  . '</a>';

			$help_sentences[] = sprintf(
				/* translators: Note on the taxonomy translation screen. %s: a link, already wrapped in its tags, whose text is "Taxonomies Translation". */
				esc_html__(
					'You can translate the base slug of a taxonomy as well as its terms. Set it in %s.',
					'sitepress'
				),
				$taxonomies_settings_link
			);
		}
		/* translators: the sentence is completed with "translating post categories and custom taxonomies" */
		$help_sentences[] = sprintf(
			/* translators: Note on the taxonomy translation screen. %s: a link, already wrapped in its tags, whose text is "translating post categories and custom taxonomies". */
			esc_html__(
				'To learn more, please visit our documentation page about %s.',
				'sitepress'
			),
			$translate_taxonomies_link
		);

		return '<p>' . implode( '</p><p>', $help_sentences ) . '</p>';
	}
}
