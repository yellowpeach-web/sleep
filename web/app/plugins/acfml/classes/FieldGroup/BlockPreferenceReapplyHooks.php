<?php

namespace ACFML\FieldGroup;

class BlockPreferenceReapplyHooks implements \IWPML_Backend_Action {

	const PRIORITY = 10;

	public function add_hooks() {
		add_filter( 'wpml_preference_reapply_affected_posts', [ $this, 'addBlockPosts' ], self::PRIORITY, 2 );
		add_action( 'wpml_preference_reapply_batch_completed', [ $this, 'sendBlockPostsAgain' ], self::PRIORITY, 2 );
	}

	public function addBlockPosts( $postIds, $changes ) {
		$blockPostIds = AttachedBlockPosts::idsForFieldNames( self::fieldNames( $changes ) );

		return array_merge( array_map( 'intval', (array) $postIds ), $blockPostIds );
	}

	public function sendBlockPostsAgain( $postIds, $changes ) {
		$fieldNames = self::fieldNames( $changes, true );

		if ( ! $postIds || ! $fieldNames ) {
			return;
		}

		$blockPostIds = AttachedBlockPosts::idsWithin( $fieldNames, (array) $postIds );

		$service = $blockPostIds ? TranslationStatusService::get() : null;

		if ( $service && method_exists( $service, 'updateNeedsUpdate' ) ) {
			$service->updateNeedsUpdate( $blockPostIds );
		}
	}

	private static function fieldNames( $changes, $onlyNeedingUpdate = false ) {
		$fieldNames = [];

		foreach ( (array) $changes as $name => $change ) {
			$name = (string) $name;

			if ( '' === $name ) {
				continue;
			}

			if ( $onlyNeedingUpdate && ! self::needsUpdate( $change ) ) {
				continue;
			}

			$fieldNames[] = $name;
		}

		return array_values( array_unique( $fieldNames ) );
	}

	private static function needsUpdate( $change ) {
		if ( ! is_array( $change ) || ! isset( $change['newPref'] ) ) {
			return false;
		}

		return in_array(
			(int) $change['newPref'],
			[ WPML_TRANSLATE_CUSTOM_FIELD, WPML_COPY_CUSTOM_FIELD, WPML_COPY_ONCE_CUSTOM_FIELD ],
			true
		);
	}
}
