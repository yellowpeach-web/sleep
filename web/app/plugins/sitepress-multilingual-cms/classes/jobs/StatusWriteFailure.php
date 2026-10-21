<?php

namespace WPML\TM\Jobs;

use WPML\Upgrade\TranslationStatusSchema;

class StatusWriteFailure {

	const CODE_WRITE_FAILED     = 'translation_status_write_failed';
	const CODE_NOT_CONFIRMED    = 'send_not_confirmed';
	const CODE_JOB_WRITE_FAILED = 'translation_job_write_failed';

	const TABLE_NOT_FOUND = 'table not found';

	const MYSQL_ERROR_NAMES = [
		1054 => 'Unknown column',
		1062 => 'Duplicate entry',
		1114 => 'Table is full',
		1142 => 'Command not allowed for the database user',
		1146 => "Table doesn't exist",
		1205 => 'Lock wait timeout',
		1213 => 'Deadlock',
		1406 => 'Data too long',
		2006 => 'Database connection lost',
		2013 => 'Database connection lost',
	];

	const JOB_LOG_URL = 'admin.php?page=sitepress-multilingual-cms/menu/support.php&tool=job-logs';

	const WHERE_PHP_ERROR_LOG = 'Details are in the PHP error log.';
	const WHERE_ASK_ADMIN     = 'An administrator can find details in WPML > Support > Job logs.';

	public static function details( $mysqlErrno, $where = null ) {
		return sprintf(
			'WPML could not save the translation status in the database%s. Error code: %s. %s',
			self::mysqlError( (int) $mysqlErrno ),
			self::CODE_WRITE_FAILED,
			null === $where ? self::WHERE_PHP_ERROR_LOG : $where
		);
	}

	public static function jobWriteDetails( $mysqlErrno, $where = null ) {
		return sprintf(
			'WPML could not save the translation job in the database%s. Error code: %s. %s',
			self::mysqlError( (int) $mysqlErrno ),
			self::CODE_JOB_WRITE_FAILED,
			null === $where ? self::WHERE_PHP_ERROR_LOG : $where
		);
	}

	public static function whereAreTheDetails() {
		if ( ! JobLog::isEnabled() ) {
			return self::WHERE_PHP_ERROR_LOG;
		}

		foreach ( \WPML\TM\Jobs\Log\Hooks::CAPABILITIES as $capability ) {
			if ( current_user_can( $capability ) ) {
				return sprintf( 'Details are in the <a href="%s">Job Log</a>.', esc_url( admin_url( self::JOB_LOG_URL ) ) );
			}
		}

		return self::WHERE_ASK_ADMIN;
	}

	public static function notConfirmedDetails( $jobs ) {
		return sprintf(
			'WPML sent %d job(s) but could not read them back from the database. Error code: %s. Details are in the PHP error log.',
			(int) $jobs,
			self::CODE_NOT_CONFIRMED
		);
	}

	public static function missingColumns( \WPML_Upgrade_Schema $schema ) {
		if ( ! $schema->does_table_exist( TranslationStatusSchema::TABLE ) ) {
			return [ self::TABLE_NOT_FOUND ];
		}

		$missing = [];
		foreach ( array_keys( TranslationStatusSchema::COLUMNS ) as $column ) {
			if ( ! $schema->does_column_exist( TranslationStatusSchema::TABLE, $column ) ) {
				$missing[] = $column;
			}
		}

		return $missing;
	}

	private static function mysqlError( $errno ) {
		if ( $errno <= 0 ) {
			return '';
		}

		return isset( self::MYSQL_ERROR_NAMES[ $errno ] )
			? sprintf( ' (MySQL error %d: %s)', $errno, self::MYSQL_ERROR_NAMES[ $errno ] )
			: sprintf( ' (MySQL error %d)', $errno );
	}
}
