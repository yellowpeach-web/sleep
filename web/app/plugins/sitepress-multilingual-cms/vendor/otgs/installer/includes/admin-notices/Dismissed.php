<?php

namespace OTGS\Installer\AdminNotices;

use OTGS\Installer\FP\Obj;

class Dismissed {
	const STORE_KEY = 'dismissed';
	const NONCE_ACTION = 'otgs_installer_dismiss_nag';

	public static function isDismissed( array $dismissedNotices, $repo, $id ) {
		return isset( $dismissedNotices['repo'][ $repo ][ $id ] );
	}

	public static function dismissNoticeOnPluginActivation( $plugin_slug, $network ) {
		$repositoryRecommendations = Obj::propOr([], 'repo', apply_filters( 'otgs_installer_admin_notices', [] ) );

		$isPluginRecommendation = function( $plugin_attrs ) use ( $plugin_slug ) {
			return '' === $plugin_slug || strpos( $plugin_slug, $plugin_attrs['glue_plugin_slug'] ) !== false;
		};
		foreach( $repositoryRecommendations as $repository => $notices ) {
			if ( ! isset( $notices['plugin-activated'] ) ) {
				continue;
			}
			$pluginRecommendationsToDisable = array_filter( $notices['plugin-activated'], $isPluginRecommendation );

			foreach ( $pluginRecommendationsToDisable as $plugin => $recommendation ) {
				self::dismissNoticeByTypeAndRepository( $repository, 'plugin-activated', $plugin );
			}
		}
	}

	public static function clearExpired( array $dismissedNotices, callable $timeOut ) {
		if ( isset( $dismissedNotices['repo'] ) ) {

			foreach ( $dismissedNotices['repo'] as $repo => $ids ) {
				foreach ( $ids as $id => $dismissedTimeStamp ) {
					if ( $timeOut( $dismissedTimeStamp, $repo, $id ) ) {
						unset ( $dismissedNotices['repo'][ $repo ][ $id ] );
					}
				}
			}
		}

		return $dismissedNotices;
	}

	public static function dismissNotice() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error(
				[ 'error' => __( 'You are not allowed to dismiss this notice.', 'installer' ) ],
				403
			);
			return;
		}

		$rawData = filter_var_array( $_POST, [
			'repository'       => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'noticeType'       => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			'noticePluginSlug' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
		] );

		$dismissed = self::dismissNoticeByTypeAndRepository(
			Obj::propOr('', 'repository', $rawData ),
			Obj::propOr('', 'noticeType', $rawData ),
			Obj::propOr('', 'noticePluginSlug', $rawData )
		);

		if ( ! $dismissed ) {
			wp_send_json_error(
				[ 'error' => __( 'Unknown notice type.', 'installer' ) ],
				400
			);
			return;
		}

		wp_send_json_success( [] );
	}

	public static function dismissRecommendationNoticeByPluginSlug( $dismissed, $data ) {
		$dismissed['repo'][ $data['repository'] ][ $data['noticePluginSlug'] ] = time();
		return $dismissed;
	}

	private static function dismissNoticeByTypeAndRepository($dismissRepository, $dismissNoticeType, $dismissNoticePluginSlug) {
		$dismissions = apply_filters('otgs_installer_admin_notices_dismissions', []);

		if ( ! isset( $dismissions[ $dismissNoticeType ] ) || ! is_callable( $dismissions[ $dismissNoticeType ] ) ) {
			return false;
		}

		$store = new Store();

		$dismissed = $store->get(self::STORE_KEY, []);

		$data = [
			'repository' => $dismissRepository,
			'noticeType' => $dismissNoticeType,
			'noticePluginSlug' => $dismissNoticePluginSlug,
		];

		$dismissed = $dismissions[$dismissNoticeType]($dismissed, $data);

		$store->save(self::STORE_KEY, $dismissed);

		return true;
	}

}
