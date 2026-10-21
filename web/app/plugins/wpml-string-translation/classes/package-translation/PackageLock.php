<?php

namespace WPML\ST\PackageTranslation;

use WPML\Utilities\AdvisoryLock;
use WPML\Utilities\AdvisoryLockFactory;

class PackageLock {

	public static function create( string $name ): AdvisoryLock {
		return \WPML\Container\make( AdvisoryLockFactory::class )->create( $name );
	}
}
