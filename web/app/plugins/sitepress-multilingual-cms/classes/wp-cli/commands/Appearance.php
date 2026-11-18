<?php
namespace WPML\CLI\Core\Commands;

use WPML\Knowledge\AppearanceService;

class Appearance implements ICommand {

	const ACTION_STATS   = 'stats';
	const ACTION_RESET   = 'reset';
	const ACTION_REBUILD = 'rebuild';

	const STATS_FIELDS = [ 'type', 'rows', 'held_missing', 'oldest_last_seen', 'newest_last_seen', 'mode' ];

	const FORMATS = [ 'table', 'csv', 'json', 'yaml' ];

	const MESSAGE_NO_TABLE        = 'The appearance registry is not available on this site: its table is missing. WPML creates it on the next admin or front-end request; until then automatic translation sends every item as before.';
	const MESSAGE_STATS_FAILED    = 'The appearance rows could not be read. See the database error in the site error log.';
	const MESSAGE_RESET_FAILED    = 'The appearance rows could not be deleted. See the database error in the site error log.';
	const MESSAGE_FOREIGN_TYPE    = 'Unknown element type "%s". Use post_<post_type>, tax_<taxonomy>, package_<kind_slug> or string.';
	const MESSAGE_UNKNOWN_ACTION  = 'Unknown action "%s". Use stats, reset or rebuild.';
	const MESSAGE_ST_INACTIVE     = 'String Translation is not active; the strings positions belong to it.';
	const MESSAGE_REBUILD_USAGE   = 'Usage: wp wpml appearance rebuild --from=string-positions';
	const MESSAGE_REBUILD_FAILED  = 'The strings could not be imported: the appearance table or the strings positions table is missing, or the database refused the statement. See the database error in the site error log.';
	const MESSAGE_REBUILD_DONE    = '%d strings imported from the front-end positions';

	const REBUILD_FROM_STRING_POSITIONS = 'string-positions';

	const CONFIRM_RESET_ALL  = 'Delete every appearance row, so that each held item waits for its next view before automatic translation sends it, and flush the appearance cache notes (the whole object cache where the cache cannot flush one group)?';
	const CONFIRM_RESET_TYPE = 'Delete the appearance rows of type %s, so that each held item of that type waits for its next view before automatic translation sends it, and flush the appearance cache notes (the whole object cache where the cache cannot flush one group)?';

	private $service;

	public function __construct( AppearanceService $service ) {
		$this->service = $service;
	}

	public function __invoke( $args, $assoc_args ) {
		$action = isset( $args[0] ) ? (string) $args[0] : '';

		switch ( $action ) {
			case self::ACTION_STATS:
				$this->stats( isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table' );
				break;
			case self::ACTION_RESET:
				$this->reset( array_key_exists( 'type', $assoc_args ) ? $assoc_args['type'] : null, $assoc_args );
				break;
			case self::ACTION_REBUILD:
				$this->rebuild( array_key_exists( 'from', $assoc_args ) ? $assoc_args['from'] : null );
				break;
			default:
				\WP_CLI::error( sprintf( self::MESSAGE_UNKNOWN_ACTION, $action ) );
		}
	}

	public function get_command() {
		return 'appearance';
	}

	private function stats( $format ) {
		if ( ! $this->available() ) {
			\WP_CLI::error( self::MESSAGE_NO_TABLE );

			return;
		}

		$stats = $this->service->typeStats();
		if ( null === $stats ) {
			\WP_CLI::error( self::MESSAGE_STATS_FAILED );

			return;
		}

		$held  = $this->service->heldTypes();
		$types = array_unique( array_merge( array_keys( $stats ), $held ) );
		sort( $types );

		if ( ! $types ) {
			\WP_CLI::success(
				sprintf( 'No appearance is recorded and no type is held. Detection mode: %s.', $this->mode( '' ) )
			);

			return;
		}

		$items = [];
		foreach ( $types as $type ) {
			$row     = isset( $stats[ $type ] ) ? $stats[ $type ] : [ 'rows' => 0, 'oldest' => '', 'newest' => '' ];
			$items[] = [
				'type'             => $type,
				'rows'             => $row['rows'],
				'held_missing'     => in_array( $type, $held, true ) ? $this->countMissing( $type ) : '-',
				'oldest_last_seen' => $row['oldest'],
				'newest_last_seen' => $row['newest'],
				'mode'             => $this->mode( $type ),
			];
		}

		\WP_CLI\Utils\format_items( in_array( $format, self::FORMATS, true ) ? $format : 'table', $items, self::STATS_FIELDS );
	}

	private function reset( $type, array $assoc_args ) {
		if ( null !== $type && ! AppearanceService::isKnownType( $type ) ) {
			\WP_CLI::error( sprintf( self::MESSAGE_FOREIGN_TYPE, is_scalar( $type ) ? (string) $type : '' ) );

			return;
		}

		if ( ! $this->available() ) {
			\WP_CLI::error( self::MESSAGE_NO_TABLE );

			return;
		}

		\WP_CLI::confirm(
			null === $type ? self::CONFIRM_RESET_ALL : sprintf( self::CONFIRM_RESET_TYPE, $type ),
			$assoc_args
		);

		$result = $this->service->reset( $type );
		if ( null === $result ) {
			\WP_CLI::error( self::MESSAGE_RESET_FAILED );

			return;
		}

		$message = sprintf(
			'Deleted %d appearance %s %s.',
			$result['deleted'],
			1 === $result['deleted'] ? 'row' : 'rows',
			null === $type ? 'of every type' : 'of type ' . $type
		);
		$message .= $result['reopened']
			? ' Reopened for automatic translation: ' . implode( ', ', $result['reopened'] ) . '.'
			: ' No held type to reopen.';

		\WP_CLI::success( $message );
	}

	private function rebuild( $from ) {
		if ( ! defined( 'WPML_ST_VERSION' ) ) {
			\WP_CLI::error( self::MESSAGE_ST_INACTIVE );

			return;
		}

		if ( self::REBUILD_FROM_STRING_POSITIONS !== $from ) {
			\WP_CLI::error( self::MESSAGE_REBUILD_USAGE );

			return;
		}

		$imported = $this->service->importStrings( null );
		if ( null === $imported ) {
			\WP_CLI::error( self::MESSAGE_REBUILD_FAILED );

			return;
		}

		\WP_CLI::success( sprintf( self::MESSAGE_REBUILD_DONE, $imported ) );
	}

	private function available() {
		return true === apply_filters( 'wpml_appearance_available', false );
	}

	private function countMissing( $type ) {
		$missing = apply_filters( 'wpml_appearance_count_missing', null, $type );

		return is_int( $missing ) ? $missing : 'unknown';
	}

	private function mode( $type ) {
		$mode = apply_filters( 'wpml_appearance_detection_mode', 'off', $type );

		return is_string( $mode ) ? $mode : 'off';
	}
}
