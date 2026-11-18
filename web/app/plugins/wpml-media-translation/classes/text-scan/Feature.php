<?php

namespace WPML\Media\TextScan;

class Feature {

	const FILTER   = 'wpml_media_text_scan_enabled';
	const CONSTANT = 'WPML_MEDIA_TEXT_SCAN';

	public static function isEnabled() {
		return (bool) apply_filters( self::FILTER, defined( self::CONSTANT ) && (bool) constant( self::CONSTANT ) );
	}
}
