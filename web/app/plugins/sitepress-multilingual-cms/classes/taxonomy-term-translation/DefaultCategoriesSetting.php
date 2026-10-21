<?php

namespace WPML\TaxonomyTermTranslation;

class DefaultCategoriesSetting implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const PAGE    = 'writing';
	const SECTION = 'default';

	private $sitepress;

	private $box;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	public function register() {
		if ( ! $this->sitepress->is_setup_complete()
			|| count( (array) $this->sitepress->get_active_languages() ) < 2 ) {
			return;
		}

		register_setting(
			self::PAGE,
			\WPML_Default_Categories_Box::FIELD,
			array( 'sanitize_callback' => array( $this->box(), 'sanitize' ) )
		);

		add_settings_field(
			\WPML_Default_Categories_Box::FIELD,
			__( 'Default categories per language', 'sitepress' ),
			array( $this, 'renderField' ),
			self::PAGE,
			self::SECTION
		);
	}

	public function renderField() {
		echo '<p class="description">'
			. esc_html__( 'A category cannot be deleted while it is a language\'s default - move that language to another category first.', 'sitepress' )
			. '</p>';

		echo $this->box()->render();
	}

	private function box() {
		if ( ! $this->box ) {
			$this->box = new \WPML_Default_Categories_Box( $this->sitepress );
		}

		return $this->box;
	}
}
