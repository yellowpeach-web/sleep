<?php

namespace WPML\LanguageEditor\Save\Phase;

class SyncSuspension {

	public static function around( callable $work ) {
		$posts = \WPML_Post_Synchronization::$delete_sync_suspended;
		$terms = \WPML_Term_Actions::$delete_sync_suspended;

		\WPML_Post_Synchronization::$delete_sync_suspended = true;
		\WPML_Term_Actions::$delete_sync_suspended         = true;

		try {
			return $work();
		} finally {
			\WPML_Post_Synchronization::$delete_sync_suspended = $posts;
			\WPML_Term_Actions::$delete_sync_suspended         = $terms;
		}
	}

	public static function isSuspended() {
		return \WPML_Post_Synchronization::$delete_sync_suspended
			&& \WPML_Term_Actions::$delete_sync_suspended;
	}
}
