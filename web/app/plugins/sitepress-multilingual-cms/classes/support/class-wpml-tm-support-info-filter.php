<?php

class WPML_TM_Support_Info_Filter {
	private $support_info;

	function __construct( WPML_TM_Support_Info $support_info ) {
		$this->support_info = $support_info;
	}

	public function filter_blocks( array $blocks ) {

		$is_simplexml_extension_loaded      = $this->support_info->is_simplexml_extension_loaded();
		$blocks['php']['data']['simplexml'] = array(
			/* translators: Label of the row on the Support screen about a PHP extension. SimpleXML is a technical name and stays as it is. */
			'label'      => __( 'SimpleXML extension', 'sitepress' ),
			'value'      => $is_simplexml_extension_loaded ? /* translators: Value of a row on the Support screen: the extension is there and in use. Past participle used as a state. */ __( 'Loaded', 'sitepress' ) : /* translators: Value of a row on the Support screen: the extension is not in use. Past participle used as a state. */ __( 'Not loaded', 'sitepress' ),
		);

		return $blocks;
	}
}
