<?php

namespace OTGS\Installer\Templates\Repository;

class Cell {

	public static function render( $model, $drawState, $log = null ) {
		$error = self::draw( $drawState, $html );
		if ( null === $error ) {
			echo $html;

			return;
		}

		self::log( $log, self::describe( $model, $error ) );
		self::renderControls( $model, $log );
	}

	private static function renderControls( $model, $log ) {
		$inBox = [
			function () use ( $model ) {
				/* translators: %s: product name (WPML, Toolset). */
				echo '<p>' . esc_html( sprintf( __( 'The %s registration details could not be shown. You can still unregister this site or register it with a site key.', 'installer' ), $model->productName ) ) . '</p>';
			},
			function () use ( $model ) {
				if ( ! empty( $model->siteKey ) ) {
					RegisteredButtons::render( (object) array_merge( (array) $model, [ 'expired' => true ] ) );
				}
			},
			function () use ( $model ) {
				Register::renderEnterSiteKeyItem( $model );
			},
		];

		echo '<div class="otgs-installer-registered wp-clearfix"><div class="inline otgs-installer-notice otgs-installer-notice-info otgs-installer-notice-'
			. esc_attr( $model->repoId ) . '"><div class="otgs-installer-notice-content">';
		foreach ( $inBox as $control ) {
			self::drawControl( $model, $control, $log );
		}
		echo '</div></div><div class="installer-error-box hidden"></div></div>';

		self::drawControl(
			$model,
			function () use ( $model ) {
				echo Register::getRegistrationForm( $model );
			},
			$log
		);
	}

	private static function drawControl( $model, $control, $log ) {
		$error = self::draw( $control, $html );
		if ( null === $error ) {
			echo $html;
		} else {
			self::log( $log, self::describe( $model, $error ) );
		}
	}

	private static function draw( $draw, &$html ) {
		$html  = '';
		$error = null;
		$level = ob_get_level();
		ob_start();
		try {
			call_user_func( $draw );
		} catch ( \Exception $e ) {
			$error = $e;
		} catch ( \Throwable $e ) {
			$error = $e;
		}
		while ( ob_get_level() > $level + 1 ) {
			ob_end_clean();
		}
		$html = (string) ob_get_clean();

		return $error;
	}

	private static function describe( $model, $error ) {
		return sprintf(
			'Activate & Update: the %s registration box could not be drawn, its registration controls are drawn instead. %s: %s in %s:%d',
			isset( $model->repoId ) ? $model->repoId : '?',
			get_class( $error ),
			$error->getMessage(),
			$error->getFile(),
			$error->getLine()
		);
	}

	private static function log( $log, $message ) {
		if ( $log ) {
			call_user_func( $log, $message );

			return;
		}

		error_log( 'OTGS Installer: ' . $message );
		if ( function_exists( 'otgs_installer_get_logger_storage' ) && class_exists( 'OTGS_Installer_Log' ) ) {
			try {
				$entry = new \OTGS_Installer_Log();
				$entry->set_component( \OTGS_Installer_Logger_Storage::COMPONENT_RENDERING );
				$entry->set_response( $message );
				otgs_installer_get_logger_storage()->add( $entry );
			} catch ( \Exception $e ) {
				error_log( 'OTGS Installer: the Installer log could not be written: ' . $e->getMessage() );
			}
		}
	}
}
