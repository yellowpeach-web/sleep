<?php

namespace OTGS\Installer\AdminNotices\Notices;

use OTGS\Installer\AdminNotices\ToolsetConfig;
use OTGS\Installer\AdminNotices\WPMLConfig;
use OTGS\Installer\Collection;

class ApiConnection {
	const CONNECTION_ISSUES = 'connection-issues';
	const UNREGISTER_PENDING = 'unregister-pending';
	const WPML_ACTIVATE_UPDATE_PAGE = 'wpml-activate-update';

	public static function getCurrentNotices( \WP_Installer $installer, array $initialNotices ) {
		$config = $installer->getRepositories();

		$noticeTypes = [
			self::CONNECTION_ISSUES  => [ApiConnection::class, 'shouldShowConnectionIssues'],
			self::UNREGISTER_PENDING => [ApiConnection::class, 'shouldShowUnregisterPending'],
		];

		return collection::of( $noticeTypes )
		                 ->entities()
		                 ->reduce( Notice::addNoticesForType($installer, $config), Collection::of( $initialNotices ) )
		                 ->get();

	}

	public static function shouldShowConnectionIssues( \WP_Installer $installer, array $nag ) {
		return $installer->shouldDisplayConnectionIssueMessage( $nag['repository_id'] );
	}

	public static function shouldShowUnregisterPending( \WP_Installer $installer, array $nag ) {
		return (bool) \OTGS_Installer_Site_Key_Remove_Service::pending( $nag['repository_id'] );
	}

	public static function config( array $initialConfig ) {
		return self::pages( self::screens( $initialConfig ) );
	}

	public static function pages( array $initialPages ) {
		$wpmlPages    = [ 'pages' => array_merge( WPMLConfig::pages(), [ self::WPML_ACTIVATE_UPDATE_PAGE ] ) ];
		$toolsetPages = [ 'pages' => array_merge( ToolsetConfig::pages(), [ self::WPML_ACTIVATE_UPDATE_PAGE ] ) ];

		return array_merge_recursive( $initialPages, [
			'repo' => [
				'wpml'    => [
					ApiConnection::CONNECTION_ISSUES  => $wpmlPages,
					ApiConnection::UNREGISTER_PENDING => $wpmlPages,
				],
				'toolset' => [
					ApiConnection::CONNECTION_ISSUES  => $toolsetPages,
					ApiConnection::UNREGISTER_PENDING => $toolsetPages,
				],
			],
		] );
	}

	public static function screens( array $screens ) {
		$config = [
			ApiConnection::CONNECTION_ISSUES  => [ 'screens' => [ 'plugins', 'plugin-install' ] ],
			ApiConnection::UNREGISTER_PENDING => [ 'screens' => [ 'plugins', 'plugin-install' ] ],
		];

		return array_merge_recursive( $screens, [
			'repo' => [
				'wpml'    => $config,
				'toolset' => $config,
			],
		] );
	}

	public static function texts( array $initialTexts ) {
		return array_merge_recursive( $initialTexts, [
			'repo' => [
				'wpml'    => [
					ApiConnection::CONNECTION_ISSUES  => WPMLTexts::class . '::connectionIssues',
					ApiConnection::UNREGISTER_PENDING => WPMLTexts::class . '::unregisterPending',
				],
				'toolset' => [
					ApiConnection::CONNECTION_ISSUES  => ToolsetTexts::class . '::connectionIssues',
					ApiConnection::UNREGISTER_PENDING => ToolsetTexts::class . '::unregisterPending',
				],
			],
		] );
	}

	public static function dismissions( array $initialDismissions ) {
		return $initialDismissions;
	}
}
