<?php

namespace WPML\PB\TranslationJob;

use WPML\FP\Lst;
use WPML\FP\Str;

use function WPML\FP\pipe;

class Groups {

	const PATH_SEPARATOR  = '/';
	const LABEL_SEPARATOR = ': ';

	const TOP_LEVEL_WIDGETS_ID          = 'Widgets-0';
	const TOP_LEVEL_WIDGETS_DESCRIPTION = 'Widgets';
	const WIDGET_GROUP_NAME             = 'Widget';

	public static function flattenHierarchy( $elements ) {
		$groups = self::extractGroupIds( $elements );
		if ( ! $groups ) {
			return $elements;
		}

		$plan = self::planFlattenedGroups( $elements, $groups );

		$flattenGroup = function ( $element ) use ( $plan ) {
			if ( ! isset( $element['extradata']['group_id'], $element['extradata']['group'] ) ) {
				return $element;
			}

			$groupId = $element['extradata']['group_id'];

			if ( empty( $groupId ) || ! self::isPBGroup( $groupId ) ) {
				return $element;
			}

			if ( self::isBlockElementInGroup( $groupId ) ) {
				list( $element['extradata']['group_id'], $element['extradata']['group'] ) = self::getBlockGroup( $groupId );
			} elseif ( isset( $plan[ $groupId ] ) ) {
				$element['extradata']['group_id'] = $plan[ $groupId ]['group_id'];
				$element['extradata']['group']    = $plan[ $groupId ]['group'];
			}

			return $element;
		};

		return wpml_collect( $elements )
			->map( $flattenGroup )
			->all();
	}

	private static function planFlattenedGroups( array $elements, array $groups ): array {
		$resolved               = [];
		$truePathsByCollapsedId = [];

		foreach ( $elements as $element ) {
			if ( ! isset( $element['extradata']['group_id'], $element['extradata']['group'] ) ) {
				continue;
			}

			$groupId = $element['extradata']['group_id'];

			if ( empty( $groupId )
				|| ! self::isPBGroup( $groupId )
				|| self::isBlockElementInGroup( $groupId )
				|| isset( $resolved[ $groupId ] )
			) {
				continue;
			}

			if ( self::isSingleElementInGroup( $groupId, $groups ) ) {
				$collapsedId = self::removeLastCrumb( $groupId );
				$label       = self::removeLastCrumb( $element['extradata']['group'] );
				$truePath    = self::removeLeafCrumb( $groupId );
			} else {
				$collapsedId = self::removeAllExceptLastCrumb( $groupId );
				$label       = self::removeAllExceptLastCrumb( $element['extradata']['group'] );
				$truePath    = $groupId;
			}

			$resolved[ $groupId ] = compact( 'collapsedId', 'label', 'truePath' );

			if ( ! in_array( $truePath, $truePathsByCollapsedId[ $collapsedId ] ?? [], true ) ) {
				$truePathsByCollapsedId[ $collapsedId ][] = $truePath;
			}
		}

		$groupIdByTruePath = self::resolveCollidingGroupIds( $truePathsByCollapsedId );

		$plan = [];
		foreach ( $resolved as $groupId => $data ) {
			$plan[ $groupId ] = [
				'group_id' => $groupIdByTruePath[ $data['truePath'] ],
				'group'    => $data['label'],
			];
		}

		return $plan;
	}

	private static function resolveCollidingGroupIds( array $truePathsByCollapsedId ): array {
		$reserved = [];
		foreach ( $truePathsByCollapsedId as $collapsedId => $truePaths ) {
			if ( 1 === count( $truePaths ) ) {
				$reserved[ $collapsedId ] = true;
			}
		}

		$groupIdByTruePath = [];
		foreach ( $truePathsByCollapsedId as $collapsedId => $truePaths ) {
			if ( 1 === count( $truePaths ) ) {
				$groupIdByTruePath[ $truePaths[0] ] = $collapsedId;
				continue;
			}

			$base  = (string) preg_replace( '/-\d+$/', '', $collapsedId );
			$index = 0;
			foreach ( $truePaths as $truePath ) {
				do {
					$candidate = $base . '-' . $index;
					++$index;
				} while ( isset( $reserved[ $candidate ] ) );

				$reserved[ $candidate ]         = true;
				$groupIdByTruePath[ $truePath ] = $candidate;
			}
		}

		return $groupIdByTruePath;
	}

	private static function isPBGroup( $groupId ) {
		return (bool) Str::startsWith( \WPML_TM_Page_Builders::TOP_LEVEL_GROUP_ID, $groupId );
	}

	public static function extractGroupIds( $elements ) {
		return pipe(
			Lst::pluck( 'extradata' ),
			Lst::pluck( 'group_id' )
		)( $elements );
	}

	public static function isSingleElementInGroup( $needle, $haystack ) {
		$count = wpml_collect( $haystack )
			->map( 'trailingslashit' )
			->filter( Str::startsWith( trailingslashit( $needle ) ) )
			->count();

		return 1 === $count;
	}

	public static function isBlockElementInGroup( $groupId ) {
		return (bool) preg_match( '/\/block-\d+\//', $groupId );
	}

	public static function removeLastCrumb( $group ) {
		$crumbs = explode( self::PATH_SEPARATOR, $group );

		if ( count( $crumbs ) > 1 ) {
			array_pop( $crumbs );
		}

		return self::removeAllExceptLastCrumb( implode( self::PATH_SEPARATOR, $crumbs ) );
	}

	public static function removeAllExceptLastCrumb( $group ) {
		$crumbs = explode( self::PATH_SEPARATOR, $group );

		return count( $crumbs ) > 2
			? reset( $crumbs ) . self::PATH_SEPARATOR . end( $crumbs )
			: $group;
	}

	private static function removeLeafCrumb( string $group ): string {
		$crumbs = explode( self::PATH_SEPARATOR, $group );

		if ( count( $crumbs ) > 1 ) {
			array_pop( $crumbs );
		}

		return implode( self::PATH_SEPARATOR, $crumbs );
	}

	private static function getBlockGroup( string $groupIds ): array {
		$blockId = null;

		if ( preg_match( '/block-\d+/', $groupIds, $matches ) ) {
			$blockId = $matches[0];
		}

		if ( null === $blockId ) {
			return [
				self::TOP_LEVEL_WIDGETS_ID . '/' . self::WIDGET_GROUP_NAME,
				self::TOP_LEVEL_WIDGETS_DESCRIPTION . '/' . self::WIDGET_GROUP_NAME,
			];
		}

		$sidebarsWidgets = get_option( 'sidebars_widgets' );
		$foundSidebarId  = self::WIDGET_GROUP_NAME;

		if ( is_array( $sidebarsWidgets ) ) {
			foreach ( $sidebarsWidgets as $sidebarId => $widgets ) {
				if ( is_array( $widgets ) && in_array( $blockId, $widgets, true ) ) {
					$foundSidebarId = $sidebarId;
					break;
				}
			}
		}

		return [
			self::TOP_LEVEL_WIDGETS_ID . '/' . $foundSidebarId,
			self::TOP_LEVEL_WIDGETS_DESCRIPTION . '/' . self::getSidebarName( $foundSidebarId ),
		];
	}

	private static function getSidebarName( $sidebarId ) {
		global $wp_registered_sidebars;

		return isset( $wp_registered_sidebars[ $sidebarId ] )
			? $wp_registered_sidebars[ $sidebarId ]['name']
			: Labels::convertToHuman( $sidebarId, true );
	}

	public static function isGroupLabel( $title ) {
		return false !== strpos( (string) $title, self::LABEL_SEPARATOR );
	}

	public static function buildGroupLabel( $groups, $title, $sequence = null ) {
		if ( ! $groups ) {
			return $title;
		}

		return implode( self::PATH_SEPARATOR, $groups )
			. ( null === $sequence ? '' : '-' . $sequence )
			. self::LABEL_SEPARATOR . $title;
	}

	public static function parseGroupLabel( $groupLabel ) {
		list( $groups, $title ) = explode( self::LABEL_SEPARATOR, $groupLabel, 2 );

		return [
			explode( self::PATH_SEPARATOR, $groups ),
			$title,
		];
	}

	public static function appendImageIdToGroupLabel( $groupLabel, $imageId ) {
		list( $group, $title ) = explode( self::LABEL_SEPARATOR, $groupLabel, 2 );

		return $group . '-' . (string) $imageId . self::LABEL_SEPARATOR . $title;
	}
}
