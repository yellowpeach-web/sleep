<?php
namespace WPML\CLI\Core\Commands;

class TranslationPolicyCutoverFactory implements IWPML_Core {

	public function create() {
		global $sitepress;

		return new TranslationPolicyCutover( $sitepress );
	}
}
