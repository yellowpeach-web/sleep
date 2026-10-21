<?php

namespace WPML\Media;

class AddAttachmentHook {

	public static function suspendDuring( callable $callback ) {
		$hook = isset( $GLOBALS['wp_filter']['add_attachment'] ) ? $GLOBALS['wp_filter']['add_attachment'] : null;
		unset( $GLOBALS['wp_filter']['add_attachment'] );

		try {
			return $callback();
		} finally {
			if ( null !== $hook ) {
				$GLOBALS['wp_filter']['add_attachment'] = $hook;
			}
		}
	}
}
