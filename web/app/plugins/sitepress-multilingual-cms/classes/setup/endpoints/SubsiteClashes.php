<?php

namespace WPML\Setup\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\UrlHandling\Domain\SubsiteClash\Clash;
use WPML\FP\Either;
use WPML\UrlHandling\SubsiteClash\Detector;
use WPML\UrlHandling\SubsiteClash\NoticeMarkup;

class SubsiteClashes implements IHandler {

	public static function applies(): bool {
		return is_multisite() && ! is_subdomain_install() && is_main_site();
	}

	public function run( Collection $data ) {
		$clashes = self::applies() ? Detector::wizardClashes() : [];

		return Either::right(
			[
				'clashes'   => array_values(
					array_map(
						function ( Clash $clash ) {
							return [
								'directory' => $clash->directoryCode(),
								'language'  => $clash->languageName(),
							];
						},
						$clashes
					)
				),
				'separator' => NoticeMarkup::listSeparator(),
			]
		);
	}
}
