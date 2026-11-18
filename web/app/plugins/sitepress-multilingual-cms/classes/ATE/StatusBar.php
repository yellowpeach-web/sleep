<?php

namespace WPML\TM\ATE;

use WPML\BackgroundTask\BackgroundTask;
use WPML\Element\API\Languages;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\User;
use function WPML\FP\spreadArgs;
use WPML\Setup\Option;

class StatusBar {

	public static function add_hooks() {
		if ( User::canManageTranslations() ) {
			Hooks::onAction( 'admin_bar_menu', 999 )
			     ->then( spreadArgs( [ self::class, 'add' ] ) );
		}
	}

	public static function add( \WP_Admin_Bar $adminBar ) {
		$stalled = self::stalled();

		$adminBar->add_node(
			[
				'parent' => false,
				'id'     => 'ate-status-bar',
				'title'  => '<i id="wpml-status-bar-icon" class="otgs-ico otgs-ico-wpml" aria-hidden="true"></i>' .
					'<span class="screen-reader-text" id="wpml-ate-status-bar-label">' .
					esc_html__( 'WPML automatic translation', 'sitepress' ) .
					'</span>' .
					'<span id="wp-admin-bar-ate-status-bar-badge"></span>' .
					self::stalledText( $stalled ),
				'href'   => false,
				'meta'   => [ 'class' => 'wpml-status-bar-hidden' . ( '' !== $stalled['attribute'] ? ' wpml-ate-delivery-stalled' : '' ) ]
			]
		);
		$adminBar->add_node(
			[
				'parent' => 'ate-status-bar',
				'id'     => 'ate-status-bar-content',
				'meta'   => [ 'html' => '<div id="wpml-ate-status-bar-content"></div>' ],
				'href'   => false,
			]
		);
	}

	private static function stalled() {
		try {
			$config = Loader::handshakeConfig();

			return isset( $config['stalled'] ) && is_array( $config['stalled'] ) ? $config['stalled'] : [ 'attribute' => '', 'adminBar' => '' ];
		} catch ( \Throwable $e ) {
			return [ 'attribute' => '', 'adminBar' => '' ];
		}
	}

	const STALLED_ID = 'wpml-ate-status-bar-stalled';

	public static function stalledText( array $stalled ) {
		if ( empty( $stalled['attribute'] ) || empty( $stalled['adminBar'] ) ) {
			return '';
		}

		return '<span id="' . self::STALLED_ID . '" class="wpml-ate-status-bar-stalled" data-wpml-delivery-stalled="' . esc_attr( $stalled['attribute'] ) . '">' .
			esc_html( $stalled['adminBar'] ) .
			'</span>';
	}
}
