<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PostTypesSinceRepositoryInterface;

class PostTypesSinceRepositoryFactory {

	private static $instance = null;

	public static function create(): PostTypesSinceRepositoryInterface {
		if ( null === self::$instance ) {
			global $wpml_dic;

			self::$instance = $wpml_dic->make( PostTypesSinceRepositoryInterface::class );
		}

		return self::$instance;
	}

	public static function setService( ?PostTypesSinceRepositoryInterface $instance ) {
		self::$instance = $instance;
	}
}
