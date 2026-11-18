<?php

function wpml_tm_register_core_capabilities() {
	add_filter( 'wpml_tm_taxonomy_human_methods_supported', [ \WPML\TM\OwnedPackages\OwnerNeedsUpdate::class, 'taxonomyHumanMethodsSupported' ] );
}
