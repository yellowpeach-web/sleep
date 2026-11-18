<?php

namespace WPML\ST\MO\File;

use GlobIterator;
use WPML\Collect\Support\Collection;
use WPML\ST\DB\Mappers\StringsRetrieve as DbStringsRetrieve;
use WPML\ST\TranslationFile\Domains;
use WPML\ST\Storage\StoragePerLanguageInterface;
use WPML\ST\TranslationFile\StringsRetrieve;
use WPML\ST\TranslationFile\Sync\TranslationUpdates;
use WPML\StringTranslation\Infrastructure\Core\Command\SaveFileCommand;
use WPML\Utilities\Lock;
use WPML_Language_Records;
use function WPML\Container\make;

class Manager extends \WPML\ST\TranslationFile\Manager {

	const LOST_OPTION = 'wpml_st_lost_files';
	const REBUILT_OPTION = 'wpml_st_lost_files_rebuilt';
	const REBUILD_LOCK_TIMEOUT = 300;
	const REBUILD_CAP = 50;
	const REBUILD_GIVE_UP = 3;

	private $translation_updates;

	private $rebuilding = false;

	private $lost_found = [];

	private $lost_hooked = false;

	public function __construct(
		StringsRetrieve $strings,
		Builder $builder,
		\WP_Filesystem_Base $filesystem,
		WPML_Language_Records $language_records,
		Domains $domains,
		?SaveFileCommand $save_file_command = null,
		?StoragePerLanguageInterface $index_storage = null,
		?TranslationUpdates $translation_updates = null
	) {
		parent::__construct( $strings, $builder, $filesystem, $language_records, $domains, $save_file_command, $index_storage );
		$this->translation_updates = $translation_updates;
	}

	protected function fileFoundMissing( $domain, $locale ) {
		$this->lost_found[ $locale . '#' . $domain ] = true;

		if ( ! $this->lost_hooked ) {
			$this->lost_hooked = true;
			add_action( 'shutdown', [ $this, 'queueLostFiles' ] );
		}
	}

	public function queueLostFiles() {
		$found            = $this->lost_found;
		$this->lost_found = [];

		if ( ! $found ) {
			return;
		}

		$lost   = $this->lostFilesAsStored();
		$queued = $lost + $found;

		if ( $queued !== $lost ) {
			update_option( self::LOST_OPTION, $queued, true );
		}
	}

	public function rebuildLostFiles() {
		if ( ! array_key_exists( self::LOST_OPTION, wp_load_alloptions() ) ) {
			return;
		}

		$lost = $this->lostFiles();

		if ( ! $lost ) {
			return;
		}

		$lock = make( Lock::class, [ ':name' => self::class ] );
		if ( ! $lock->create( self::REBUILD_LOCK_TIMEOUT ) ) {
			return;
		}

		$stored  = get_option( self::REBUILT_OPTION, [] );
		$rebuilt = is_array( $stored ) ? $stored : [];
		$saved   = $rebuilt;
		$budget  = $this->rebuildCap();
		$done    = [];

		$this->rebuilding = true;
		try {
			foreach ( array_keys( $lost ) as $key ) {
				if ( $budget <= 0 ) {
					break;
				}

				list( $locale, $domain ) = array_pad( explode( '#', (string) $key, 2 ), 2, '' );

				if ( $this->filesystem->is_readable( $this->getFilepath( $domain, $locale ) ) ) {
					unset( $rebuilt[ $key ] );
					$done[] = $key;
					continue;
				}

				$count = isset( $rebuilt[ $key ] ) ? (int) $rebuilt[ $key ] : 0;
				if ( $count >= self::REBUILD_GIVE_UP ) {
					if ( self::REBUILD_GIVE_UP === $count ) {
						$rebuilt[ $key ] = $count + 1;
						error_log( 'WPML String Translation: the translation file of ' . $domain . ' (' . $locale . ') was rebuilt ' . $count . ' times and is gone again; it is not rebuilt any more until a translation of the domain is saved.' );
					}
					$done[] = $key;
					continue;
				}

				$budget--;
				$rebuilt[ $key ] = $count + 1;
				$this->saveRebuilt( $saved, $rebuilt );
				$saved = $rebuilt;

				try {
					$this->add( $domain, $locale );
				} catch ( \Throwable $e ) {
					error_log( 'WPML String Translation: the rebuild of the translation file of ' . $domain . ' (' . $locale . ') failed: ' . $e->getMessage() );
					break;
				}
			}
		} finally {
			$this->rebuilding = false;
			$lock->release();
		}

		if ( $rebuilt !== $saved ) {
			$this->saveRebuilt( $saved, $rebuilt );
		}
		$this->dequeue( $done );
	}

	private function lostFiles() {
		$lost = get_option( self::LOST_OPTION, [] );

		return is_array( $lost ) ? $lost : [];
	}

	private function lostFilesAsStored() {
		wp_cache_delete( self::LOST_OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		return $this->lostFiles();
	}

	private function dequeue( array $keys ) {
		if ( ! $keys ) {
			return;
		}

		$lost = array_diff_key( $this->lostFilesAsStored(), array_fill_keys( $keys, true ) );

		update_option( self::LOST_OPTION, $lost, true );
	}

	private function saveRebuilt( array $before, array $after ) {
		wp_cache_delete( self::REBUILT_OPTION, 'options' );
		$stored  = get_option( self::REBUILT_OPTION, [] );
		$stored  = is_array( $stored ) ? $stored : [];
		$updated = $stored;

		foreach ( array_keys( $before + $after ) as $key ) {
			if ( ( $before[ $key ] ?? null ) === ( $after[ $key ] ?? null ) ) {
				continue;
			}

			if ( isset( $after[ $key ] ) ) {
				$updated[ $key ] = $after[ $key ];
			} else {
				unset( $updated[ $key ] );
			}
		}

		if ( $updated !== $stored ) {
			update_option( self::REBUILT_OPTION, $updated, false );
		}
	}

	protected function rebuildCap() {
		return self::REBUILD_CAP;
	}

	public function add( $domain, $locale ) {
		if ( ! $this->rebuilding ) {
			$rebuilt = get_option( self::REBUILT_OPTION, [] );

			if ( is_array( $rebuilt ) && isset( $rebuilt[ $locale . '#' . $domain ] ) ) {
				unset( $rebuilt[ $locale . '#' . $domain ] );
				update_option( self::REBUILT_OPTION, $rebuilt, false );
			}
		}

		return parent::add( $domain, $locale );
	}

	protected function getMark( $domain, $locale ) {
		$marks = $this->translationUpdates()->getMarks( [ $domain ], $locale );

		return isset( $marks[ $domain ] ) ? $marks[ $domain ] : null;
	}

	private function translationUpdates() {
		if ( ! $this->translation_updates ) {
			$this->translation_updates = make( TranslationUpdates::class );
		}

		return $this->translation_updates;
	}

	protected function getFileExtension() {
		return 'mo';
	}

	public function isPartialFile() {
		return true;
	}

	protected function getDomains() {
		return $this->domains->getMODomains();
	}

	public function handles( $domain ) {
		if ( parent::handles( $domain ) ) {
			return true;
		}

		$isWordPressDomain = function ( $moDomain ) {
			return strtolower( DbStringsRetrieve::CONTEXT_WORDPRESS ) === strtolower( (string) $moDomain );
		};

		return DbStringsRetrieve::CONTEXT_DEFAULT === strtolower( (string) $domain )
			&& null !== $this->getDomains()->first( $isWordPressDomain );
	}

	public static function hasFiles() {
		return (bool) ( new GlobIterator( self::getSubdir() . '/*.mo' ) )->count();
	}
}
