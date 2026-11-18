<?php

namespace WPML\TM\ATE\UpdateTranslation;

use WPML\TM\ATE\Receive\SingleJobDelivery;
use WPML\TM\Editor\Editor;

class NotAppliedCopy {

	public static function get( $code, $outcome, $canManage ) {
		if ( Editor::ATE_JOB_CANCELLED_IN_WPML === $code ) {
			return self::notApplied(
				/* translators: Message on a page of the site, after a translation edited in the Advanced Translation Editor was not applied. */
				__( 'This translation job was cancelled before you completed it.', 'sitepress' ),
				self::resendStep( $canManage )
			);
		}

		if ( Editor::ATE_DELIVERY_NOT_APPLIED !== $code ) {
			return null;
		}

		switch ( $outcome ) {
			case SingleJobDelivery::CLIENT_EDITS:
				return self::notApplied(
					/* translators: Message on a page of the site, after a translation edited in the Advanced Translation Editor was not applied because the translation was opened for editing again. */
					__( 'This translation is being edited somewhere else. WPML does not overwrite those edits.', 'sitepress' ),
					self::resendStep( $canManage )
				);

			case SingleJobDelivery::CLOSED:
				return self::notApplied(
					/* translators: Message on a page of the site, after a translation edited in the Advanced Translation Editor was not applied because WPML closed that edit meanwhile. */
					__( 'This edit was closed in WPML while you were in the editor.', 'sitepress' ),
					self::resendStep( $canManage )
				);

			case SingleJobDelivery::SUPERSEDED:
				return self::notApplied(
					/* translators: Message on a page of the site, after a translation edited in the Advanced Translation Editor was not applied because a newer translation job replaced it. */
					__( 'A newer translation job replaced this one.', 'sitepress' ),
					/* translators: Message on a page of the site: nothing to do, WPML applies the newer translation job by itself. */
					__( 'WPML will apply the newer job when it is ready.', 'sitepress' )
				);

			case SingleJobDelivery::APPLY_FAILED:
			case SingleJobDelivery::WRONG_ORIGINAL:
				return [
					/* translators: Heading of a message on a page of the site, after saving a translation edited in the Advanced Translation Editor failed for now. */
					'heading'  => __( 'Your changes are not on this page yet', 'sitepress' ),
					/* translators: Message on a page of the site, after saving a translation edited in the Advanced Translation Editor failed for now. "them" is the user's changes. */
					'reason'   => __( 'WPML could not save them this time. It will try again on its own.', 'sitepress' ),
					/* translators: Message on a page of the site, after saving a translation edited in the Advanced Translation Editor failed for now. */
					'nextStep' => __( 'Reload this page in a few minutes.', 'sitepress' ),
				];

			default:
				return self::notApplied( '', self::resendStep( $canManage ) );
		}
	}

	private static function notApplied( $reason, $nextStep ) {
		return [
			/* translators: Heading of a message on a page of the site, after a translation edited in the Advanced Translation Editor was not applied. */
			'heading'  => __( 'Your changes were not applied', 'sitepress' ),
			'reason'   => $reason,
			'nextStep' => $nextStep,
		];
	}

	private static function resendStep( $canManage ) {
		return $canManage
			/* translators: Message on a page of the site, after a translation edited in the Advanced Translation Editor was not applied. Shown to a user who can send content to translation. */
			? __( 'To translate this page again, send it to translation.', 'sitepress' )
			/* translators: Message on a page of the site, after a translation edited in the Advanced Translation Editor was not applied. Shown to a translator, who cannot send content to translation. */
			: __( 'Ask your translation manager to send this page to translation again.', 'sitepress' );
	}
}
