<?php

namespace WPML\TM\Jobs\TakeOver;

use WPML\LIB\WP\Nonce;
use WPML\LIB\WP\User;

class Payload {

	public static function forJob( $job ) {
		$assigneeId    = (int) $job->get_translator_id();
		$currentUserId = (int) get_current_user_id();

		if ( User::canManageTranslations() ) {
			return null;
		}

		if ( Decision::HARD !== Decision::forJob( $job->get_status_value(), $assigneeId, $currentUserId ) ) {
			return null;
		}

		if ( ! $job->user_can_translate( wp_get_current_user() ) ) {
			return null;
		}

		return array(
			'jobId'    => (int) $job->get_id(),
			'assignee' => $assigneeId,
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'endpoint' => Reassign::class,
			'nonce'    => Nonce::create( Reassign::class ),
			'strings'  => self::strings( $assigneeId, (string) $job->get_title(), (string) $job->get_language_code( true ) ),
		);
	}

	public static function strings( $assigneeId, $title, $languageName ) {
		$assignee     = get_userdata( $assigneeId );
		$assigneeName = $assignee ? $assignee->display_name : '';

		return array(
			'title'         => __( 'Someone is already translating this', 'sitepress' ),
			/* translators: Line in the dialog shown when somebody else is already translating this content. %1$s: the name of that person, %2$s: the title of the content, %3$s: the name of the language. */
			'body'          => sprintf( __( '%1$s is translating %2$s into %3$s.', 'sitepress' ), $assigneeName, $title, $languageName ),
			/* translators: %s: translator name */
			'risk'          => sprintf( __( 'If you take over, the translation is reassigned to you and %s loses any unsaved work.', 'sitepress' ), $assigneeName ),
			/* translators: %s: translator name */
			'ack'           => sprintf( __( 'I understand %s will lose any unsaved work', 'sitepress' ), $assigneeName ),
			/* translators: Button label in that dialog: take the job over from the person who holds it. Verb phrase, imperative. */
			'takeOver'      => __( 'Take over', 'sitepress' ),
			/* translators: Button label that closes a dialog without doing anything, or stops what is going on. Verb, imperative, not the noun "a cancellation". */
			'cancel'        => __( 'Cancel', 'sitepress' ),
			'statusChanged' => __( 'This translation changed since you opened the page. Reloading so you see its current state.', 'sitepress' ),
			'genericError'  => __( 'The translation could not be taken over. Reloading.', 'sitepress' ),
		);
	}
}
