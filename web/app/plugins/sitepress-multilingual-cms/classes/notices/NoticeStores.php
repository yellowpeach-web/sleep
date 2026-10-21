<?php

namespace WPML\Notices;

final class NoticeStores {

	public static function rows( \wpdb $wpdb ): array {
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name = %s OR option_name LIKE %s",
				\WPML_Notices::NOTICES_OPTION_KEY,
				$wpdb->esc_like( \WPML_Notices::NOTICES_OPTION_KEY . '_' ) . '%'
			)
		);

		return is_array( $rows ) ? $rows : [];
	}
}
