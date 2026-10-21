<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\LanguageEditor\PageData;
use WPML\LanguageEditor\Presets\CatalogueSyncRunner;

class SyncCatalogue implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		$this->runner()->run();

		return Either::right( $this->referenceData() );
	}

	protected function runner() {
		return CatalogueSyncRunner::create();
	}

	protected function referenceData() {
		return array(
			'presets'        => PageData::presets(),
			'countries'      => PageData::countries(),
			'availableFlags' => PageData::availableFlags(),
			'flagManifest'   => PageData::flagManifest(),
			'defaultPairs'   => PageData::defaultPairs(),
		);
	}
}
