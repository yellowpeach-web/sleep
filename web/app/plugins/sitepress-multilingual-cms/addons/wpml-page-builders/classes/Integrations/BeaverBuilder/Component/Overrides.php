<?php

namespace WPML\PB\BeaverBuilder\Component;

class Overrides {

	private const DATA_KEY = 'dynamic_node_settings';
	private const GROUPS   = [ 'root', 'child' ];

	private const CONNECTIONS_KEY = 'connections';

	public static function collectNodes( array $data_array, array &$nodes = [] ): array {
		foreach ( $data_array as $item ) {
			if ( is_array( $item ) ) {
				self::collectNodes( $item, $nodes );
			} elseif ( is_object( $item ) && isset( $item->node, $item->settings ) ) {
				$nodes[ $item->node ] = $item;
			}
		}

		return $nodes;
	}

	public static function getOverrideTargets( \stdClass $node, array $nodes ): array {
		$targets = [];

		if ( ! isset( $node->settings->{self::DATA_KEY} ) ) {
			return $targets;
		}

		$dynamic_node_settings = $node->settings->{self::DATA_KEY};

		foreach ( self::GROUPS as $group ) {
			if ( ! isset( $dynamic_node_settings->$group ) ) {
				continue;
			}

			foreach ( (array) $dynamic_node_settings->$group as $target_id => $overrides ) {
				if ( ! is_object( $overrides ) ) {
					continue;
				}

				$target_id = (string) $target_id;
				$type      = self::resolveType( $target_id, $nodes );

				if ( '' === $type ) {
					continue;
				}

				$settings       = new \stdClass();
				$settings->type = $type;
				$fields         = [];

				foreach ( (array) $overrides as $field => $value ) {
					if ( self::CONNECTIONS_KEY === $field || ! is_string( $value ) ) {
						continue;
					}

					$settings->$field = $value;
					$fields[]         = $field;
				}

				if ( ! $fields ) {
					continue;
				}

				$targets[] = [
					'group'     => $group,
					'target_id' => $target_id,
					'node_id'   => self::stringNodeId( $group, $target_id ),
					'settings'  => $settings,
					'fields'    => $fields,
				];
			}
		}

		return $targets;
	}

	public static function applyTranslatedSettings( \stdClass $node, array $target, \stdClass $translated_settings ): void {
		$group     = $target['group'];
		$target_id = $target['target_id'];

		foreach ( $target['fields'] as $field ) {
			if ( isset( $translated_settings->$field ) ) {
				$node->settings->{self::DATA_KEY}->$group->$target_id->$field = $translated_settings->$field;
			}
		}
	}

	private static function stringNodeId( string $group, string $target_id ): string {
		return 'component-' . $group . '-' . $target_id;
	}

	private static function resolveType( string $target_id, array $nodes ): string {
		if ( isset( $nodes[ $target_id ]->settings->type ) && is_string( $nodes[ $target_id ]->settings->type ) ) {
			return $nodes[ $target_id ]->settings->type;
		}

		return '';
	}
}
