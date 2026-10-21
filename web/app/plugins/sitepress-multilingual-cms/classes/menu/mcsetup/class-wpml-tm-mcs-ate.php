<?php

class WPML_TM_MCS_ATE extends WPML_Twig_Template_Loader {
	private $authentication;
	private $authentication_data;
	private $endpoints;
	private $strings;

	private $model = array();

	public function __construct(
		WPML_TM_ATE_Authentication $authentication,
		WPML_TM_ATE_AMS_Endpoints $endpoints,
		WPML_TM_MCS_ATE_Strings $strings
	) {
		parent::__construct(
			array(
				$this->get_template_path(),
			)
		);

		$this->authentication      = $authentication;
		$this->endpoints           = $endpoints;
		$this->strings             = $strings;
		$this->authentication_data = get_option( WPML_TM_ATE_Authentication::AMS_DATA_KEY, array() );

		/* translators: Link text inside a sentence about a problem with automatic translation; it opens the WPML support pages. It is the name of the support team, a noun. */
		$wpml_support      = esc_html__( 'WPML support', 'sitepress' );
		$wpml_support_url  = \WPML\OutboundLinks\OutboundLinks::to(
			\WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl::URL,
			array(
				'medium'   => 'settings',
				'campaign' => 'support',
			)
		);
		$wpml_support_link = '<a target="_blank" rel="noopener" href="' . esc_url( $wpml_support_url ) . '">' . $wpml_support . '</a>';

		$this->model = [
			'status_button_text'      => $this->get_status_button_text(),
			'synchronize_button_text' => $this->strings->get_synchronize_button_text(),
			'strings'                 => [
				/* translators: %s: link to WPML support. */
				'error_help' => sprintf( esc_html__( 'Please try again in a few minutes. If the problem persists, please contact %s.', 'sitepress' ), $wpml_support_link ),
			],
		];
	}

	public function get_template_path() {
		return WPML_TM_PATH . '/templates/ATE';
	}

	public function init_hooks() {
		add_action( 'wpml_tm_mcs_' . ICL_TM_TMETHOD_ATE, array( $this, 'render' ) );
		add_action( 'wpml_tm_mcs_troubleshooting', [ $this, 'renderTroubleshooting' ] );
	}

	public function get_model( array $args = array() ) {
		if ( array_key_exists( 'wizard', $args ) ) {
			$this->model['strings']['error_help'] = wpml_bold_names(
				__( 'You can continue the <b>Translation Management</b> configuration later by going to WPML -> Settings -> Translation Editor.', 'sitepress' )
			);
		}

		return $this->model;
	}

	public function render() {
		echo $this->get_template()
				  ->show( $this->get_model(), 'mcs-ate-controls.twig' );
	}

	public function renderTroubleshooting() {
		echo '<div id="synchronize-ate-ams"></div>';
	}

	public function get_strings() {
		return $this->strings;
	}

	private function has_translators() {
		global $iclTranslationManagement;

		return $iclTranslationManagement->has_translators();
	}

	private function get_status_button_text() {
		return $this->strings->get_current_status_attribute( 'button' );
	}

	public function get_script_data() {
		return array(
			'hasTranslators' => $this->has_translators(),
			'currentStatus'  => $this->strings->get_status(),
			'statuses'       => $this->strings->get_statuses(),
		);
	}

}
