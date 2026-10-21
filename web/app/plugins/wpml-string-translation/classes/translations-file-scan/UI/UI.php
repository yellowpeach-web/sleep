<?php

namespace WPML\ST\MO\Scan\UI;

use \WPML\ST\WP\App\Resources;
use WPML\LIB\WP\Hooks as WPHooks;

class UI {

	public static function add_hooks( callable $getModel ) {
		WPHooks::onAction( 'admin_enqueue_scripts' )
			->then( $getModel )
			->then( [ self::class, 'localize' ] )
			->then( Resources::enqueueApp( 'mo-scan' ) );

		WPHooks::onAction( 'admin_footer' )
			->then( [ self::class, 'render_prompt_mount' ] );
	}

	public static function render_prompt_mount() {
		?>
		<div id="wpml-mo-scan-st-page-pregenerate"></div>
		<?php
	}

	public static function localize( $model ) {
		return [
			'name' => 'wpml_mo_scan_ui_files',
			'data' => $model,
		];
	}
}
