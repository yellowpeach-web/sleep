<?php

namespace WPML\StringTranslation\Infrastructure\Core\HookHandler;


use WPML\Core\Component\StringPackage\Application\Query\Dto\PackageDefinitionDto;
use WPML\Infrastructure\WordPress\Component\StringPackage\Application\Query\PackageDefinitionQuery;
use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractFilterHookHandler;
use WPML\UserInterface\Web\Core\Component\Dashboard\Application\ViewModel\ItemSection;

class WPMLDashboardItemSectionsFilter  extends AbstractFilterHookHandler {
	const FILTER_NAME = 'wpml_tm_dashboard_item_sections';
	const FILTER_ARGS = 1;
	const FILTER_PRIORITY = 9;

	protected function onFilter( ...$args ) {
		$itemSections = $args[0];

		$kindId = 'stringPackage';
		$itemSectionPattern = '%s/%s';

		$package_kinds = $this->getTranslatableStringPackages();
		$definitions   = $this->getPackageDefinitionsByKindKey();

		foreach ( $package_kinds as $package_data ) {
			$package_type_slug = $package_data->kind_slug;
			$package_type      = $package_data->kind;

			$definition = $definitions[ strtolower( (string) $package_type_slug ) ] ?? null;
			$plural     = $definition ? $definition->getPlural() : $package_type;
			$singular   = $definition ? $definition->getTitle() : $package_type;

			$itemSection = new ItemSection(
				sprintf( $itemSectionPattern, $kindId, $package_type_slug ),
				$plural,
				$singular,
				$plural,
				$kindId,
				false,
				$package_type_slug,
				[
					'title' => ''
				]
			);
			$itemSections[] = $itemSection;
		}

		return $itemSections;
	}

	private function getTranslatableStringPackages() {
		global $wpdb;

		$package_kinds = $wpdb->get_results( "SELECT kind, kind_slug FROM {$wpdb->prefix}icl_string_packages WHERE id>0 and post_id is null group by kind_slug, kind" );

		return $package_kinds;
	}

	private function getPackageDefinitionsByKindKey(): array {
		if ( ! class_exists( PackageDefinitionQuery::class ) ) {
			return [];
		}

		$byKindKey = [];
		foreach ( ( new PackageDefinitionQuery() )->getInfoList() as $key => $definition ) {
			$kindKey               = strtolower( (string) $key );
			$byKindKey[ $kindKey ] = $byKindKey[ $kindKey ] ?? $definition;
		}

		return $byKindKey;
	}
}
