<?php

class WPML_TM_Jobs_Weekly_Summary_Report_Model implements WPML_TM_Jobs_Summary_Report_Model {

	public function get_subject() {
		/* translators: Subject of the weekly email WPML sends about translation work. %1$s: the name of the site, %2$s: the last date the email covers. */
		return __( 'Translation updates for %1$s until %2$s', 'sitepress' );
	}

	public function get_summary_text() {
		/* translators: First line of the weekly email WPML sends about translation work. %1$s: the name of the site, %2$s: how many translations were updated. */
		return __( 'This week %1$s had the following %2$s translation updates', 'sitepress' );
	}
}