<?php

namespace WPML\TM\Menu\TranslationQueue;

class TranslationQueuePage {

	const FOLDER = 'tm';

	const SLUG = self::FOLDER . '/menu/main.php';

	const TAB = 'tasks';

	const LEGACY_SLUG = self::FOLDER . '/menu/translations-queue.php';

	public static function base() {
		return 'admin.php?page=' . self::SLUG . '&tab=' . self::TAB;
	}

	public static function hostsQueue( array $get ) {
		$page = isset( $get['page'] ) && is_string( $get['page'] ) ? $get['page'] : '';
		$tab  = isset( $get['tab'] ) && is_string( $get['tab'] ) ? $get['tab'] : '';

		return self::SLUG === $page && ( self::TAB === $tab || '' === $tab );
	}
}
