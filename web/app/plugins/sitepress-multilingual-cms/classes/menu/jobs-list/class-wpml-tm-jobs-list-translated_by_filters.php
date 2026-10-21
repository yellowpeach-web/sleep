<?php

class WPML_TM_Jobs_List_Translated_By_Filters {
	private $services;

	private $translators;

	public function __construct( WPML_TM_Jobs_List_Services $services, WPML_TM_Jobs_List_Translators $translators ) {
		$this->services    = $services;
		$this->translators = $translators;
	}

	public function get() {
		$options = array(
			array(
				'value' => 'any',
				/* translators: First option in the dropdown that filters by translator: do not filter, whoever translated it. */
				'label' => __( 'Anyone', 'sitepress' ),
			),
		);

		$services = $this->services->get();
		if ( $services ) {
			$options[] = array(
				'value' => 'any-service',
				'label' => __( 'Any Translation Service', 'sitepress' ),
			);
		}

		$translators = $this->translators->get();
		if ( $translators ) {
			$options[] = array(
				'value' => 'any-local-translator',
				'label' => __( 'Any WordPress Translator', 'sitepress' ),
			);
		}

		return array(
			'options'     => $options,
			'services'    => $services,
			'translators' => $translators,
		);
	}
}
