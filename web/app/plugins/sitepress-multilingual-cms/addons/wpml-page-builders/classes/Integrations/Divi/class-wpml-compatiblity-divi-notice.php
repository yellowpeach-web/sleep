<?php

class WPML_Compatibility_Divi_Notice extends WPML_Notice {

	const ID    = 'wpml-compatibility-divi-editor-warning';
	const GROUP = 'wpml-compatibility-divi';

	public function __construct() {
		parent::__construct( self::ID, $this->get_message(), self::GROUP );
		$this->set_dismissible( true );
		$this->set_css_class_types( 'warning' );
	}

	private function get_message() {
		/* translators: Part 1 of 2 of the admin notice shown when the Divi theme is active and translations are made in the WordPress editor rather than WPML's. The two parts are joined into one paragraph, in this order, with a space between them. */
		$msg = esc_html_x(
			'You are using DIVI theme, and you have chosen to use the standard editor for translating content.',
			'Use Translation Editor notice 1/2',
			'sitepress'
		);

		$msg .= ' ' . \WPML\PB\Helper\BoldNames::render(
			/* translators: Part 2 of 2 of that Divi notice, joined after part 1 with a space. Keep the bold tags around the name of WPML's own editing screen. */
			_x(
				'Some functionalities may not work properly. We encourage you to switch to use the <b>Translation Editor</b>.',
				'Use Translation Editor notice 2/2',
				'sitepress'
			)
		);

		return $msg;
	}
}
