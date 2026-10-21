<?php

namespace WPML\ContentDeletion\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\ContentDeletion\Settings;
use WPML\FP\Either;

class SaveSettings implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'wpml_manage_languages' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		$sanitized = Settings::sanitize(
			[
				Settings::ORIGINALS    => $data->get( Settings::ORIGINALS, [] ),
				Settings::TRANSLATIONS => $data->get( Settings::TRANSLATIONS, [] ),
			]
		);

		global $sitepress;

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'save_settings' ) ) {
			return Either::left( [ 'error' => 'settings_unavailable' ] );
		}

		$sitepress->save_settings( [ Settings::KEY => $sanitized ] );

		return Either::right( $sanitized );
	}
}
