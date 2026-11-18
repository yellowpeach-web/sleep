<?php

namespace WPML\StringTranslation\Infrastructure\StringGettext\Command;

use WPML\StringTranslation\Application\Setting\Repository\FilesystemRepositoryInterface;
use WPML\StringTranslation\Application\StringGettext\Command\SavePendingStringsCommandInterface;
use WPML\StringTranslation\Infrastructure\StringGettext\Repository\Util\PendingStringsMerger;

class SavePendingStringsPhpCommand implements SavePendingStringsCommandInterface {

	private $filesystemRepository;

	private $createPhpFile;

	private $fileLock;

	private $merger;

	public function __construct(
		FilesystemRepositoryInterface $filesystemRepository,
		CreatePhpFileCommand $createPhpFile,
		PendingStringsFileLock $fileLock,
		PendingStringsMerger $merger
	) {
		$this->filesystemRepository = $filesystemRepository;
		$this->createPhpFile        = $createPhpFile;
		$this->fileLock             = $fileLock;
		$this->merger               = $merger;
	}

	public function run( string $domain, array $strings ) : bool {
		$filepath = $this->filesystemRepository->getPendingStringsFilepath( $domain, 'php' );
		$lock     = $this->fileLock->acquire( $filepath . '.lock' );

		if ( ! $lock ) {
			return $this->spillIntoClaim( $domain, $strings, $filepath );
		}

		try {
			$existingStrings = $this->getExistingPendingStrings( $filepath );
			if ( null === $existingStrings ) {
				return false;
			}

			$strings = $this->merger->merge( $existingStrings, $strings );

			return $this->createPhpFile->run(
				$strings,
				$filepath
			);
		} finally {
			$this->fileLock->release( $lock );
		}
	}

	private function spillIntoClaim( string $domain, array $strings, string $filepath ): bool {
		$processingDir = rtrim( $this->filesystemRepository->getQueueDir(), '/\\' ) . '/processing/';
		if ( ! is_dir( $processingDir ) ) {
			@mkdir( $processingDir, 0777, true );
		}
		if ( ! is_dir( $processingDir ) ) {
			return false;
		}

		$token         = str_replace( '.', '', uniqid( '', true ) );
		$claimFilepath = $processingDir . basename( $filepath ) . '.' . $token . '.php';

		return $this->createPhpFile->run( $strings, $claimFilepath );
	}

	private function getExistingPendingStrings( string $filepath ) {
		if ( ! file_exists( $filepath ) ) {
			return [];
		}
		if ( ! is_readable( $filepath ) ) {
			return $this->quarantineFile( $filepath ) ? [] : null;
		}

		try {
			$result = include $filepath;
		} catch ( \Throwable $e ) {
			return $this->quarantineFile( $filepath ) ? [] : null;
		}

		if (
			! $result ||
			! is_array( $result ) ||
			! isset( $result['items'] ) ||
			! is_array( $result['items'] )
		) {
			return $this->quarantineFile( $filepath ) ? [] : null;
		}

		return $result['items'];
	}

	private function quarantineFile( string $filepath ) : bool {
		if ( ! file_exists( $filepath ) ) {
			return true;
		}

		$quarantineDir = dirname( $filepath ) . '/quarantine/';
		if ( ! is_dir( $quarantineDir ) ) {
			@mkdir( $quarantineDir, 0777, true );
		}
		if ( ! is_dir( $quarantineDir ) ) {
			return false;
		}

		$token              = str_replace( '.', '', uniqid( '', true ) );
		$quarantineFilepath = $quarantineDir . basename( $filepath ) . '.corrupt.' . $token . '.php';

		return @rename( $filepath, $quarantineFilepath );
	}

}
