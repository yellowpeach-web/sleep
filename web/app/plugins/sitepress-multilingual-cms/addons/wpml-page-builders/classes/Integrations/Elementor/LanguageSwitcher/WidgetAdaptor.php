<?php

namespace WPML\PB\Elementor\LanguageSwitcher;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;

class WidgetAdaptor {

	private $widget;

	public function setTarget( Widget $widget ) {
		$this->widget = $widget;
	}

	public function getName() {
		return 'wpml-language-switcher';
	}

	public function getTitle() {
		/* translators: Name of the widget WPML adds to Elementor's widget panel; it puts a language switcher on the page. "WPML" stays in English. */
		return __( 'WPML Language Switcher', 'sitepress' );
	}

	public function getIcon() {
		return 'fa fa-globe';
	}

	public function getCategories() {
		return [ 'general' ];
	}

	public function registerControls() {
		$this->widget->start_controls_section(
			'section_content',
			[
				/* translators: Title of the first settings tab of the WPML language-switcher widget in Elementor, the one holding what the switcher shows. Elementor uses the same word for this tab on every widget, so translate it as Elementor does. */
				'label' => __( 'Content', 'sitepress' ),
				'type'  => Controls_Manager::SECTION,
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->widget->add_control(
			'style',
			[
				/* translators: Label of the dropdown that picks which of WPML's three switcher layouts the widget renders, in the Elementor widget settings. */
				'label'   => __( 'Language switcher type', 'sitepress' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'custom',
				'options' => [
					/* translators: Adjective, in two language switcher dropdowns: the group heading for styles that come from the theme rather than from WPML, and the Elementor option for the switcher built from the settings below. */
					'custom'            => __( 'Custom', 'sitepress' ),
					/* translators: Option of the language-switcher type dropdown in Elementor: the plain list layout WPML calls the footer switcher. */
					'footer'            => __( 'Footer', 'sitepress' ),
					/* translators: Option of the language-switcher type dropdown in Elementor: the layout that links to this post's translations only. */
					'post_translations' => __( 'Post Translations', 'sitepress' ),
				],
			]
		);

		$this->widget->add_control(
			'display_flag',
			[
				/* translators: Label of the on/off switch that shows each language's flag, in the Elementor language-switcher settings. Verb phrase, imperative. */
				'label'        => __( 'Display Flag', 'sitepress' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 1,
				'default'      => 1,
			]
		);

		$this->widget->add_control(
			'link_current',
			[
				/* translators: Label of the on/off switch that keeps the current language in the list, in the Elementor language-switcher settings; the note after the dash warns that the dropdown layout needs it on. */
				'label'        => __( 'Show Active Language - has to be ON with Dropdown', 'sitepress' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 1,
				'default'      => 1,
			]
		);

		$this->widget->add_control(
			'native_language_name',
			[
				/* translators: Label of the on/off switch that names each language in that language, in the Elementor language-switcher settings. */
				'label'        => __( 'Native language name', 'sitepress' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 1,
				'default'      => 1,
			]
		);

		$this->widget->add_control(
			'language_name_current_language',
			[
				/* translators: Label of the on/off switch that names each language in the language the visitor is reading, in the Elementor language-switcher settings. */
				'label'        => __( 'Language name in current language', 'sitepress' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 1,
				'default'      => 1,
			]
		);

		$this->widget->end_controls_section();

		$this->widget->start_controls_section(
			'style_section',
			[
				/* translators: Title of the second settings tab of the WPML language-switcher widget in Elementor, the one holding colours and spacing. Elementor uses the same word for this tab on every widget, so translate it as Elementor does. */
				'label' => __( 'Style', 'sitepress' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);
		$this->widget->start_controls_tabs( 'style_tabs' );

		$this->widget->start_controls_tab(
			'style_normal_tab',
			[
				/* translators: Heading of the color settings that apply to the language switcher in its normal state, as opposed to when the mouse is over it. */
				'label' => __( 'Normal', 'sitepress' ),
			]
		);

		$this->widget->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'switcher_typography',
				'selector' => '{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item',
			]
		);

		$this->widget->add_control(
			'switcher_text_color',
			[
				/* translators: Label of the colour field for the language switcher's text, in the Elementor widget settings. */
				'label'     => __( 'Text Color', 'sitepress' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item .wpml-ls-link, 
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-legacy-dropdown a' => 'color: {{VALUE}}',
				],
			]
		);

		$this->widget->add_control(
			'switcher_bg_color',
			[
				/* translators: Label of the colour field for the language switcher's background, in the Elementor widget settings. */
				'label'     => __( 'Background Color', 'sitepress' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item .wpml-ls-link, 
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-legacy-dropdown a' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->widget->end_controls_tab();

		$this->widget->start_controls_tab(
			'style_hover_tab',
			[
				/* translators: Heading of the color settings that apply while the mouse is over the language switcher. */
				'label' => __( 'Hover', 'sitepress' ),
			]
		);
		$this->widget->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'switcher_hover_typography',
				'selector' => '{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item:hover,
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item.wpml-ls-item__active,
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item.highlighted,
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item:focus',
			]
		);

		$this->widget->add_control(
			'switcher_hover_color',
			[
				/* translators: Label of the colour field for the language switcher's text, in the Elementor widget settings. */
				'label'     => __( 'Text Color', 'sitepress' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => [
					'default' => Global_Colors::COLOR_ACCENT,
				],
				'selectors' => [
					'{{WRAPPER}} .wpml-elementor-ls .wpml-ls-legacy-dropdown a:hover,
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-legacy-dropdown a:focus,
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-legacy-dropdown .wpml-ls-current-language:hover>a,
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item .wpml-ls-link:hover,
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item .wpml-ls-link.wpml-ls-link__active,
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item .wpml-ls-link.highlighted,
					{{WRAPPER}} .wpml-elementor-ls .wpml-ls-item .wpml-ls-link:focus' => 'color: {{VALUE}}',
				],
			]
		);

		$this->widget->end_controls_tab();

		$this->widget->end_controls_tabs();

		$this->widget->end_controls_section();

		$this->widget->start_controls_section(
			'language_flag',
			[
				/* translators: Heading of the size and spacing settings of the flag images, in the Elementor language-switcher settings. */
				'label'     => __( 'Language Flag', 'sitepress' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [
					'display_flag' => [ 1 ],
				],
			]
		);

		$this->widget->add_control(
			'flag_margin',
			[
				/* translators: Label of the spacing field for the room around the flag, in the Elementor language-switcher settings. Elementor uses the same word on every widget, so translate it as Elementor does. */
				'label'      => __( 'Margin', 'sitepress' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .wpml-elementor-ls .wpml-ls-flag' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->widget->end_controls_section();

		$this->widget->start_controls_section(
			'post_translation_text',
			[
				/* translators: Heading of the settings for the text shown beside each translation, in the Elementor language-switcher settings. */
				'label'     => __( 'Post Translation Text', 'sitepress' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [
					'style' => [ 'post_translations' ],
				],
			]
		);

		$this->widget->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'post_translation_typography',
				'selector' => '{{WRAPPER}} .wpml-elementor-ls .wpml-ls-statics-post_translations',
			]
		);

		$this->widget->add_control(
			'post_translation_color',
			[
				/* translators: Label of the colour field for the language switcher's text, in the Elementor widget settings. */
				'label'     => __( 'Text Color', 'sitepress' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .wpml-elementor-ls .wpml-ls-statics-post_translations' => 'color: {{VALUE}}',
				],
			]
		);

		$this->widget->add_control(
			'post_translation_bg_color',
			[
				/* translators: Label of the colour field for the language switcher's background, in the Elementor widget settings. */
				'label'     => __( 'Background Color', 'sitepress' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .wpml-elementor-ls .wpml-ls-statics-post_translations' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->widget->add_control(
			'post_translation_padding',
			[
				/* translators: Label of the spacing field for the room inside the language switcher, in the Elementor widget settings. Elementor uses the same word on every widget, so translate it as Elementor does. */
				'label'      => __( 'Padding', 'sitepress' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .wpml-elementor-ls .wpml-ls-statics-post_translations' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->widget->add_control(
			'post_translation_margin',
			[
				/* translators: Label of the spacing field for the room around the flag, in the Elementor language-switcher settings. Elementor uses the same word on every widget, so translate it as Elementor does. */
				'label'      => __( 'Margin', 'sitepress' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors'  => [
					'{{WRAPPER}} .wpml-elementor-ls .wpml-ls-statics-post_translations' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->widget->end_controls_section();

	}

	public function render() {
		$settings = $this->widget->get_settings_for_display();

		$this->widget->add_render_attribute('wpml-elementor-ls', 'class', [
			'wpml-elementor-ls',
		]);

		$args = array(
			'display_link_for_current_lang' => $settings['link_current'],
			'flags'                         => $settings['display_flag'],
			'native'                        => $settings['native_language_name'],
			'translated'                    => $settings['language_name_current_language'],
			'type'                          => $settings['style'],
		);

		echo '<div ' . $this->widget->get_render_attribute_string( 'wpml-elementor-ls' ) . '>';
		do_action( 'wpml_language_switcher', $args );
		echo '</div>';
	}
}
