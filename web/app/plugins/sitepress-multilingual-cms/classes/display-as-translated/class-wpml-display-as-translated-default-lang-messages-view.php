<?php

class WPML_Display_As_Translated_Default_Lang_Messages_View {

	const TEMPLATE = 'default-language-change.twig';

	private $template_service;

	public function __construct( WPML_Twig_Template $template_service ) {
		$this->template_service = $template_service;
	}

	public function display( $prev_default_lang, $default_lang ) {
		echo $this->template_service->show( $this->get_model( $prev_default_lang, $default_lang ), self::TEMPLATE );
	}

	private function get_model( $prev_default_lang, $default_lang ) {
		return array(
			'before_message'   => __( 'You have post types set to fallback to the default language. Changing the default language may impact how archive listings display untranslated posts.', 'sitepress' ),
			/* translators: First half of a notice shown after the language of the site changed; a link follows the words "See". Keep the space at the end. */
			'after_message'    => __( 'Some content may appear gone due to the default language change. See ', 'sitepress' ),
			/* translators: Link text that opens a page on wpml.org explaining the notice above it. Verb phrase, imperative. */
			'before_help_text' => __( 'Learn more', 'sitepress' ),
			'before_help_link' => \WPML\OutboundLinks\OutboundLinks::to(
				'https://wpml.org/documentation/translating-your-contents/displaying-untranslated-content-on-pages-in-secondary-languages/manage-archive-listings-default-language-change/',
				array(
					'medium'   => 'notice',
					'campaign' => 'display-as-translated',
					'content'  => 'default-language-change',
				)
			),
			/* translators: Link text that goes on from the words "Learn more", making the sentence "Learn more how to ensure your archive listing continues to display all posts". It starts in lower case because it continues that sentence. */
			'after_help_text'  => __( 'how to ensure your archive listing continues to display all posts', 'sitepress' ),
			'after_help_link'  => \WPML\OutboundLinks\OutboundLinks::to(
				'https://wpml.org/documentation/translating-your-contents/displaying-untranslated-content-on-pages-in-secondary-languages/manage-archive-listings-default-language-change/#preparing-for-default-language-change',
				array(
					'medium'   => 'notice',
					'campaign' => 'display-as-translated',
					'content'  => 'after-default-language-change',
				)
			),
			/* translators: Button label that closes a notice once the reader has read it. It is the everyday way of saying "I understand". */
			'got_it'           => __( 'Got it', 'sitepress' ),
			'lang_has_changed' => $prev_default_lang !== $default_lang,
		);
	}
}
