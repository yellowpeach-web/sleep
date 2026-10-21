<?php

use WPML\ST\TranslationFile\EntryQueries;
use WPML\ST\TranslationFile\PluralForms;
use WPML\ST\TranslationFile\QueueFilter;

class WPML_ST_Translations_File_Queue {
	const DEFAULT_LIMIT = 20000;
	const TIME_LIMIT    = 10;
	const LOCK_FIELD    = '_wpml_st_file_scan_in_progress';

	const UNREADABLE_LOGGED_FIELD = '_wpml_st_file_scan_unreadable_logged';

	private $file_dictionary;

	private $file_scan;

	private $file_scan_storage;

	private $language_records;

	private $limit;

	private $transient;

	private $unreadable = array();

	public function __construct(
		WPML_ST_Translations_File_Dictionary $file_dictionary,
		WPML_ST_Translations_File_Scan $file_scan,
		WPML_ST_Translations_File_Scan_Storage $file_scan_storage,
		WPML_Language_Records $language_records,
		$limit,
		WPML_Transient $transient
	) {
		$this->file_dictionary   = $file_dictionary;
		$this->file_scan         = $file_scan;
		$this->file_scan_storage = $file_scan_storage;
		$this->language_records  = $language_records;
		$this->limit             = $limit;
		$this->transient         = $transient;
	}

	public function import( $queueFilter = null ) {
		$this->file_dictionary->clear_skipped();
		$files = $this->file_dictionary->get_not_imported_files();

		if ( count( $files ) ) {
			$this->lock();

			$start_time = time();
			$imported   = 0;
			foreach ( $files as $file ) {
				if ( $imported >= $this->limit || time() - $start_time > self::TIME_LIMIT ) {
					break;
				}

				if ( ! $queueFilter || $queueFilter->isSelected( $file ) ) {

					$full_path = $file->get_full_path();
					if ( ! $this->file_scan->can_read( $full_path ) ) {
						$this->unreadable[ $file->get_path() ] = true;
						$this->log_unreadable_once( $file, $full_path );
						continue;
					}

					$translations = $this->file_scan->load_translations( $full_path );

					try {
						$number_of_translations = count( $translations );
						if ( ! $number_of_translations ) {
							throw new RuntimeException( 'File is empty' );
						}

						$translations = $this->constrain_translations_number(
							$translations,
							$file->get_imported_strings_count(),
							$this->limit - $imported
						);

						$imported += $imported_in_file = count( $translations );

						$this->file_scan_storage->save(
							$translations,
							$file->get_domain(),
							$this->map_language_code( $file->get_file_locale() )
						);

						$plural_forms = $this->file_scan->get_plural_forms( $full_path );
						if ( $plural_forms ) {
							PluralForms::save( $file->get_domain(), $file->get_file_locale(), $plural_forms );
						}

						$file->set_imported_strings_count( $file->get_imported_strings_count() + $imported_in_file );

						if ( $file->get_imported_strings_count() >= $number_of_translations ) {
							$file->set_status( WPML_ST_Translations_File_Entry::IMPORTED );
						} else {
							$file->set_status( WPML_ST_Translations_File_Entry::PARTLY_IMPORTED );
						}
					} catch ( WPML_ST_Bulk_Strings_Insert_Exception $e ) {
						$file->set_status( WPML_ST_Translations_File_Entry::PARTLY_IMPORTED );
						break;
					} catch ( Exception $e ) {
						$file->set_status( WPML_ST_Translations_File_Entry::IMPORTED );
					}
				} else {
					$file->set_status( WPML_ST_Translations_File_Entry::SKIPPED );
				}
				$this->file_dictionary->save( $file );

				do_action( 'wpml_st_translations_file_post_import', $file );
			}

			$this->unlock();
		}
	}

	private function log_unreadable_once( WPML_ST_Translations_File_Entry $file, $full_path ) {
		$logged = $this->transient->get( self::UNREADABLE_LOGGED_FIELD );
		$logged = is_array( $logged ) ? $logged : array();
		$key    = $file->get_path_hash();
		if ( isset( $logged[ $key ] ) ) {
			return;
		}

		$logged[ $key ] = 1;
		$this->transient->set( self::UNREADABLE_LOGGED_FIELD, $logged, MINUTE_IN_SECONDS * 60 * 24 * 7 );

		error_log( 'WPML String Translation: the translation file "' . $full_path . '" (stored as "' . $file->get_path() . '") cannot be read; it stays pending and is imported once it can be read.' );
	}

	private function get_pending_files() {
		$unreadable = $this->unreadable;

		return array_filter(
			$this->file_dictionary->get_not_imported_files(),
			function ( WPML_ST_Translations_File_Entry $file ) use ( $unreadable ) {
				return ! isset( $unreadable[ $file->get_path() ] );
			}
		);
	}

	private function map_language_code( $locale ) {
		$language_code = $this->language_records->get_language_code( $locale );

		if ( $language_code ) {
			return $language_code;
		}

		return $locale;
	}

	public function is_completed() {
		return 0 === count( $this->file_dictionary->get_not_imported_files() ) &&
			   0 < count( $this->file_dictionary->get_imported_files() );
	}

	public function get_processed() {
		return wp_list_pluck( $this->file_dictionary->get_imported_files(), 'path' );
	}

	public function is_processing() {
		return 0 !== count( $this->get_pending_files() );
	}

	public function get_pending() {
		return count( $this->get_pending_files() );
	}

	public function getPendingByFilter( QueueFilter $queueFilter ) {
		$this->file_dictionary->clear_skipped();
		$files = $this->file_dictionary->get_not_imported_files();

		if ( ! count( $files ) ) {
			return 0;
		}

		$count = 0;

		foreach ( $files as $file ) {
			if ( $queueFilter->isSelected( $file ) ) {
				$count++;
			}
		}

		return $count;
	}

	public function mark_as_finished() {
		foreach ( $this->file_dictionary->get_imported_files() as $file ) {
			$file->set_status( WPML_ST_Translations_File_Entry::FINISHED );
			$this->file_dictionary->save( $file );
		}
	}

	private function constrain_translations_number( array $translations, $offset, $limit ) {
		if ( $limit > count( $translations ) ) {
			return $translations;
		}

		return array_slice( $translations, $offset, $limit );
	}

	public function is_locked() {
		return (bool) $this->transient->get( self::LOCK_FIELD );
	}

	private function lock() {
		$this->transient->set( self::LOCK_FIELD, 1, MINUTE_IN_SECONDS * 5 );
	}

	private function unlock() {
		$this->transient->delete( self::LOCK_FIELD );
	}
}
