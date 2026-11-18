<?php

namespace WPML\Troubleshooting;

use WPML\TM\Jobs\JobLog;
use WPML\Upgrade\CreateTable;

class TranslationsTableKeys {

	const BACKUP_INFIX = 'icl_translations_backup_';

	const PRIMARY        = 'PRIMARY';
	const AUTO_INCREMENT = 'AUTO_INCREMENT';

	const REFUSED_ALREADY_RIGHT = 'already-right';
	const REFUSED_COLLISION     = 'collision';
	const REFUSED_CONFLICT      = 'conflict';
	const REFUSED_BACKUP        = 'backup';
	const FAILED_WRITE          = 'write';
	const FAILED_KEYS           = 'keys';

	const KEYS = [
		'trid_lang'        => 'UNIQUE KEY `trid_lang` (`trid`,`language_code`)',
		'el_type_id'       => 'UNIQUE KEY `el_type_id` (`element_type`,`element_id`)',
		'trid'             => 'KEY `trid` (`trid`)',
		'id_type_language' => 'KEY `id_type_language` (`element_id`,`element_type`,`language_code`)',
	];

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function table() {
		return $this->wpdb->prefix . 'icl_translations';
	}

	public function missing() {
		$wpdb   = $this->wpdb;
		$keys   = (array) $wpdb->get_results( "SHOW KEYS FROM {$wpdb->prefix}icl_translations" );
		$column = $wpdb->get_row( "SHOW COLUMNS FROM {$wpdb->prefix}icl_translations LIKE 'translation_id'" );

		if ( ! $column ) {
			return [];
		}

		$names = [];
		foreach ( $keys as $key ) {
			$names[ (string) $key->Key_name ] = true;
		}

		$missing = [];
		if ( ! isset( $names[ self::PRIMARY ] ) ) {
			$missing[] = self::PRIMARY;
		}
		if ( false === stripos( (string) $column->Extra, 'auto_increment' ) ) {
			$missing[] = self::AUTO_INCREMENT;
		}
		foreach ( array_keys( self::KEYS ) as $name ) {
			if ( ! isset( $names[ $name ] ) ) {
				$missing[] = $name;
			}
		}

		return $missing;
	}

	public function zeroRows() {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations WHERE translation_id = 0" );
	}

	public function needsRepair() {
		return $this->missing() !== [] || $this->zeroRows() > 0;
	}

	public function lacksPrimaryKeyOrAutoIncrement() {
		return (bool) array_intersect( [ self::PRIMARY, self::AUTO_INCREMENT ], $this->missing() );
	}

	public function plan() {
		$missing = $this->missing();
		$plan    = self::planRows( $this->rows() );

		$plan['missing']        = $missing;
		$plan['orphanStatuses'] = $this->orphanStatuses();
		if ( ! $missing && 0 === $plan['zero'] ) {
			$plan['refusal'] = self::REFUSED_ALREADY_RIGHT;
		}
		unset( $plan['actions'] );

		return $plan;
	}

	public function repair( $now ) {
		$result = [
			'done'         => false,
			'refusal'      => null,
			'error'        => '',
			'backup'       => '',
			'renumbered'   => 0,
			'removed'      => 0,
			'removedExact' => 0,
			'keysAdded'    => 0,
		];

		$missing = $this->missing();
		$rows    = $this->rows();
		$plan    = self::planRows( $rows );
		if ( ! $missing && 0 === $plan['zero'] ) {
			$result['refusal'] = self::REFUSED_ALREADY_RIGHT;

			return $result;
		}

		if ( null !== $plan['refusal'] ) {
			$result['refusal'] = $plan['refusal'];

			return $result;
		}

		$backup = $this->wpdb->prefix . self::BACKUP_INFIX . gmdate( 'YmdHis', (int) $now );
		$copied = $this->backup( $backup, $rows );
		if ( true !== $copied ) {
			$result['refusal'] = self::REFUSED_BACKUP;
			$result['error']   = (string) $copied;

			return $result;
		}
		$result['backup'] = $backup;

		$written = $this->applyActions( $plan['actions'] );
		if ( true !== $written ) {
			$result['refusal'] = self::FAILED_WRITE;
			$result['error']   = (string) $written;

			return $result;
		}
		$result['renumbered']   = $plan['renumbered'];
		$result['removed']      = $plan['removed'];
		$result['removedExact'] = $plan['removedExact'];

		$keys = $missing ? $this->addKeys( $missing ) : $this->moveAutoIncrementPastRenumbered( $plan['actions'] );
		if ( true !== $keys ) {
			$result['refusal'] = self::FAILED_KEYS;
			$result['error']   = (string) $keys;

			return $result;
		}
		$result['keysAdded'] = count( $missing );
		$result['done']      = true;

		self::log( $result );

		return $result;
	}

	private static function log( array $result ) {
		if ( ! class_exists( JobLog::class ) ) {
			return;
		}

		JobLog::maybeInitRequest();
		JobLog::createNewGroup( JobLog::GROUP_ID_MAINTENANCE, 'Repair the translations table' );
		JobLog::add(
			'troubleshooting_translations_table_keys_repaired',
			[
				'renumbered'   => $result['renumbered'],
				'removed'      => $result['removed'],
				'removedExact' => $result['removedExact'],
				'keysAdded'    => $result['keysAdded'],
				'backup'       => $result['backup'],
			]
		);
		JobLog::finishCurrentGroup();
	}

	public static function planRows( array $rows ) {
		$plan = [
			'refusal'      => null,
			'rows'         => count( $rows ),
			'zero'         => 0,
			'renumbered'   => 0,
			'removed'      => 0,
			'removedExact' => 0,
			'conflicts'    => 0,
			'actions'      => [],
		];

		$rows   = array_values( array_map( [ self::class, 'normalizeRow' ], $rows ) );
		$maxId  = 0;
		$seenId = [];
		foreach ( $rows as $row ) {
			$id = $row['translation_id'];
			if ( 0 === $id ) {
				$plan['zero']++;
				continue;
			}
			if ( isset( $seenId[ $id ] ) ) {
				$plan['refusal'] = self::REFUSED_COLLISION;
			}
			$seenId[ $id ] = true;
			$maxId         = max( $maxId, $id );
		}
		if ( null !== $plan['refusal'] ) {
			return $plan;
		}

		$groups = self::joinedSets( $rows );
		$nextId = $maxId;

		foreach ( $groups as $members ) {
			$set = [];
			foreach ( $members as $index ) {
				$set[] = $rows[ $index ];
			}

			if ( ! self::isOneRecord( $set ) ) {
				$plan['conflicts']++;
				continue;
			}

			$keep = self::rowToKeep( $set );
			if ( null === $keep ) {
				$plan['conflicts']++;
				continue;
			}

			if ( 0 === $keep['translation_id'] ) {
				$nextId++;
				$plan['renumbered']++;
				$plan['actions'][] = [
					'op'  => 'renumber',
					'id'  => $nextId,
					'row' => $keep,
				];
			}

			$zeroLeft = 0;
			foreach ( $set as $row ) {
				if ( 0 === $row['translation_id'] ) {
					$zeroLeft++;
					if ( $row['source_language_code'] === $keep['source_language_code'] ) {
						$plan['removedExact']++;
					}
				}
			}
			if ( 0 === $keep['translation_id'] ) {
				$zeroLeft--;
				$plan['removedExact']--;
			}
			if ( $zeroLeft > 0 ) {
				$plan['removed']  += $zeroLeft;
				$plan['actions'][] = [
					'op'    => 'remove-zero',
					'count' => $zeroLeft,
					'row'   => $keep,
				];
			}
		}

		if ( $plan['conflicts'] > 0 ) {
			$plan['refusal'] = self::REFUSED_CONFLICT;
			$plan['actions'] = [];
		}

		return $plan;
	}

	private function addKeys( array $missing ) {
		$wpdb = $this->wpdb;

		if ( count( $missing ) === 2 + count( self::KEYS ) ) {
			$done = $wpdb->query(
				"ALTER TABLE {$wpdb->prefix}icl_translations
					MODIFY `translation_id` BIGINT NOT NULL AUTO_INCREMENT,
					ADD PRIMARY KEY (`translation_id`),
					ADD UNIQUE KEY `trid_lang` (`trid`,`language_code`),
					ADD UNIQUE KEY `el_type_id` (`element_type`,`element_id`),
					ADD KEY `trid` (`trid`),
					ADD KEY `id_type_language` (`element_id`,`element_type`,`language_code`)"
			);

			return false === $done ? (string) $wpdb->last_error : true;
		}

		foreach ( array_merge( [ self::PRIMARY, self::AUTO_INCREMENT ], array_keys( self::KEYS ) ) as $piece ) {
			if ( ! in_array( $piece, $missing, true ) ) {
				continue;
			}
			switch ( $piece ) {
				case self::PRIMARY:
					$done = $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_translations MODIFY `translation_id` BIGINT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`translation_id`)" );
					break;
				case self::AUTO_INCREMENT:
					$done = in_array( self::PRIMARY, $missing, true )
						? 0
						: $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_translations MODIFY `translation_id` BIGINT NOT NULL AUTO_INCREMENT" );
					break;
				case 'trid_lang':
					$done = $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_translations ADD UNIQUE KEY `trid_lang` (`trid`,`language_code`)" );
					break;
				case 'el_type_id':
					$done = $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_translations ADD UNIQUE KEY `el_type_id` (`element_type`,`element_id`)" );
					break;
				case 'trid':
					$done = $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_translations ADD KEY `trid` (`trid`)" );
					break;
				default:
					$done = $wpdb->query( "ALTER TABLE {$wpdb->prefix}icl_translations ADD KEY `id_type_language` (`element_id`,`element_type`,`language_code`)" );
			}
			if ( false === $done ) {
				return (string) $wpdb->last_error;
			}
		}

		return true;
	}

	private function moveAutoIncrementPastRenumbered( array $actions ) {
		$highest = 0;
		foreach ( $actions as $action ) {
			if ( 'renumber' === $action['op'] ) {
				$highest = max( $highest, (int) $action['id'] );
			}
		}
		if ( 0 === $highest ) {
			return true;
		}

		$wpdb = $this->wpdb;
		$done = $wpdb->query( $wpdb->prepare( "ALTER TABLE {$wpdb->prefix}icl_translations AUTO_INCREMENT = %d", $highest + 1 ) );

		return false === $done ? (string) $wpdb->last_error : true;
	}

	private function rows() {
		$wpdb = $this->wpdb;

		return (array) $wpdb->get_results( "SELECT translation_id, element_type, element_id, trid, language_code, source_language_code FROM {$wpdb->prefix}icl_translations ORDER BY trid, element_type, element_id, language_code", ARRAY_A );
	}

	private function orphanStatuses() {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translation_status WHERE translation_id = 0" );
	}

	private function backup( $backup, array $rows ) {
		$created = CreateTable::create(
			$this->wpdb,
			$backup,
			"CREATE TABLE `{$backup}` (
				`translation_id` BIGINT NOT NULL DEFAULT 0,
				`element_type` VARCHAR( 60 ) NOT NULL DEFAULT 'post_post',
				`element_id` BIGINT NULL DEFAULT NULL,
				`trid` BIGINT NOT NULL,
				`language_code` VARCHAR( 7 ) NOT NULL,
				`source_language_code` VARCHAR( 7 )
			)"
		);
		if ( ! $created ) {
			return (string) $this->wpdb->last_error;
		}

		$copied = 0;
		foreach ( $rows as $row ) {
			if ( false === $this->wpdb->insert( $backup, (array) $row ) ) {
				return (string) $this->wpdb->last_error;
			}
			$copied++;
		}

		return $copied === count( $rows ) ? true : sprintf( 'the copy holds %d rows of %d', $copied, count( $rows ) );
	}

	private function applyActions( array $actions ) {
		$wpdb = $this->wpdb;
		$wpdb->query( 'START TRANSACTION' );

		foreach ( $actions as $action ) {
			$row       = $action['row'];
			$noElement = null === $row['element_id'] ? 1 : 0;
			if ( 'renumber' === $action['op'] ) {
				$expected = 1;
				$affected = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_translations SET translation_id = %d
						 WHERE translation_id = 0 AND element_type = %s AND trid = %d AND language_code = %s
						   AND ( element_id = %d OR ( %d = 1 AND element_id IS NULL ) )
						   AND ( source_language_code = %s OR ( %d = 1 AND source_language_code IS NULL ) )
						 LIMIT 1",
						$action['id'],
						$row['element_type'],
						$row['trid'],
						$row['language_code'],
						(int) $row['element_id'],
						$noElement,
						(string) $row['source_language_code'],
						null === $row['source_language_code'] ? 1 : 0
					)
				);
			} else {
				$expected = $action['count'];
				$affected = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}icl_translations
						 WHERE translation_id = 0 AND element_type = %s AND trid = %d AND language_code = %s
						   AND ( element_id = %d OR ( %d = 1 AND element_id IS NULL ) )",
						$row['element_type'],
						$row['trid'],
						$row['language_code'],
						(int) $row['element_id'],
						$noElement
					)
				);
			}

			if ( $affected !== $expected ) {
				$error = false === $affected
					? (string) $wpdb->last_error
					: sprintf( '%d rows changed where %d were expected', (int) $affected, $expected );
				$wpdb->query( 'ROLLBACK' );

				return $error;
			}
		}

		$wpdb->query( 'COMMIT' );

		return true;
	}

	private static function normalizeRow( $row ) {
		$row = (array) $row;

		return [
			'translation_id'       => (int) $row['translation_id'],
			'element_type'         => (string) $row['element_type'],
			'element_id'           => null === $row['element_id'] ? null : (int) $row['element_id'],
			'trid'                 => (int) $row['trid'],
			'language_code'        => (string) $row['language_code'],
			'source_language_code' => null === $row['source_language_code'] ? null : (string) $row['source_language_code'],
		];
	}

	private static function joinedSets( array $rows ) {
		$parent = array_keys( $rows );
		$find   = function ( $i ) use ( &$parent ) {
			while ( $parent[ $i ] !== $i ) {
				$parent[ $i ] = $parent[ $parent[ $i ] ];
				$i            = $parent[ $i ];
			}

			return $i;
		};

		$firstByKey = [];
		foreach ( $rows as $i => $row ) {
			$keys = [ 'tl|' . $row['trid'] . '|' . $row['language_code'] ];
			if ( null !== $row['element_id'] ) {
				$keys[] = 'el|' . $row['element_type'] . '|' . $row['element_id'];
			}
			foreach ( $keys as $key ) {
				if ( ! isset( $firstByKey[ $key ] ) ) {
					$firstByKey[ $key ] = $i;
					continue;
				}
				$a = $find( $i );
				$b = $find( $firstByKey[ $key ] );
				if ( $a !== $b ) {
					$parent[ $a ] = $b;
				}
			}
		}

		$sets = [];
		foreach ( array_keys( $rows ) as $i ) {
			$sets[ $find( $i ) ][] = $i;
		}

		return array_values( $sets );
	}

	private static function isOneRecord( array $set ) {
		$first = self::identity( $set[0] );
		foreach ( $set as $row ) {
			if ( self::identity( $row ) !== $first ) {
				return false;
			}
		}

		return true;
	}

	private static function identity( array $row ) {
		$source = $row['source_language_code'] === $row['language_code'] ? null : $row['source_language_code'];

		return var_export( [ $row['element_type'], $row['element_id'], $row['trid'], $row['language_code'], $source ], true );
	}

	private static function rowToKeep( array $set ) {
		$positive = array_values(
			array_filter(
				$set,
				function ( $row ) {
					return $row['translation_id'] > 0;
				}
			)
		);
		if ( count( $positive ) > 1 ) {
			return null;
		}
		if ( $positive ) {
			return $positive[0];
		}

		foreach ( $set as $row ) {
			if ( $row['source_language_code'] !== $row['language_code'] ) {
				return $row;
			}
		}

		return $set[0];
	}
}
