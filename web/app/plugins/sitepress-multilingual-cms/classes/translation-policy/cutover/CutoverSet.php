<?php

namespace WPML\TranslationPolicy;

use WPML\TranslationPolicy\Migration\SyncTranslationPolicy;
use WPML\Upgrade\CommandsStatus;
use WPML_Upgrade_Command_Definition;
use WPML_Upgrade_Command_Factory;

class CutoverSet {

	public const SCOPES = [ 'admin', 'cli' ];

	public const CORE_STEPS = [ SyncTranslationPolicy::class, RewriteDuplicateStatus::class ];

	public const DRY_RUN_LISTING = 'translation-policy-dry-run';

	public static function isPending(): bool {
		return FeatureSwitch::isArmed() && ! FeatureSwitch::isOn() && ! self::isDryRunRequest();
	}

	public static function addOnStep( string $className, array $args = [] ): callable {
		if ( ! is_subclass_of( $className, CutoverCommand::class ) ) {
			throw new \InvalidArgumentException( sprintf( '%s does not implement %s.', esc_html( $className ), CutoverCommand::class ) );
		}

		return function ( $commands, $listing = null ) use ( $className, $args ) {
			if ( is_array( $commands ) && ( self::DRY_RUN_LISTING === $listing || self::isPending() ) ) {
				$commands[] = wpml_create_upgrade_command_definition( $className, $args, self::SCOPES );
			}

			return $commands;
		};
	}

	public static function stepsForDryRun(): array {
		$added = apply_filters( 'wpml_upgrade_commands', [], self::DRY_RUN_LISTING );

		return array_values( array_unique( array_merge( self::CORE_STEPS, self::stepsIn( is_array( $added ) ? $added : [] ) ) ) );
	}

	private static function isDryRunRequest(): bool {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI', false ) ) {
			return false;
		}

		$runner = \WP_CLI::get_runner();

		return array_slice( (array) $runner->arguments, 0, 3 ) === explode( ' ', 'wpml ' . \WPML\CLI\Core\Commands\TranslationPolicyCutover::COMMAND )
			&& ! empty( $runner->assoc_args['dry-run'] );
	}

	public static function definitions( WPML_Upgrade_Command_Factory $factory, \SitePress $sitepress, CommandsStatus $status ): array {
		if ( ! self::isPending() ) {
			return [];
		}

		$definitions = [];
		foreach ( self::CORE_STEPS as $step ) {
			$definitions[] = $factory->create_command_definition( $step, [], self::SCOPES );
		}

		$definitions[] = $factory->create_command_definition( MarkCutover::class, [ $sitepress, $status ], self::SCOPES );

		return $definitions;
	}

	public static function stepsIn( array $definitions ): array {
		$steps = [];
		foreach ( $definitions as $definition ) {
			if ( ! $definition instanceof WPML_Upgrade_Command_Definition ) {
				continue;
			}
			$class = $definition->get_class_name();
			if ( MarkCutover::class !== $class && is_subclass_of( $class, CutoverCommand::class ) ) {
				$steps[] = $class;
			}
		}

		return array_values( array_unique( $steps ) );
	}
}
