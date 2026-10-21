<?php

class WPML_TM_Email_Notification_View extends WPML_TM_Email_View {

	const PROMOTE_TRANSLATION_SERVICES_TEMPLATE = 'notification/promote-translation-services.twig';

	public function render_model( array $model, $template ) {
		if ( isset( $model['casual_name'] ) && $model['casual_name'] ) {
			$content = $this->render_casual_header( $model['casual_name'] );
		} else {
			$content = $this->render_header( $model['username'] );
		}
		$content .= $this->template_service->show( $model, $template );
		$content .= $this->render_promote_translation_services( $model );
		$content .= $this->render_footer();

		return $content;
	}

	private function render_promote_translation_services( array $model ) {
		$content = '';

		if ( isset( $model['promote_translation_services'] ) && $model['promote_translation_services'] ) {
			$translation_services_url = esc_url( admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/settings&section=translators' ) );

			/* translators: Promote translation services: %s replaced by "professional translation services integrated with WPML" */
			$promoteA = esc_html_x( 'Need faster translation work? Try one of the %s.', 'Promote translation services: %s replaced by "professional translation services integrated with WPML"', 'sitepress' );
			/* translators: Link text inside a sentence of the email WPML sends about translation work. It starts in lower case because it sits inside the sentence. */
			$promoteB = esc_html_x( 'professional translation services integrated with WPML', 'Promote translation services: used to build a link to the translation services page', 'sitepress' );

			$promote_model['message'] = sprintf( $promoteA, '<a href="' . $translation_services_url . '">' . $promoteB . '</a>' );

			$content = $this->template_service->show( $promote_model, self::PROMOTE_TRANSLATION_SERVICES_TEMPLATE );
		}

		return $content;
	}

	private function render_footer() {
		$notifications_url  = esc_url( admin_url( 'admin.php?page=' . WPML_TM_FOLDER . WPML_Translation_Management::PAGE_SLUG_SETTINGS . '&section=translators&flash=translation-notifications' ) );
		$notifications_text = esc_html__( 'WPML Notification Settings', 'sitepress' );
		$notifications_link = '<a href="' . $notifications_url . '" style="color: #ffffff;">' . $notifications_text . '</a>';

		$bottom_text = sprintf(
			/* translators: Last line of the email WPML sends about translation work. %s: a link, already wrapped in its tags, that opens the site. */
			esc_html__(
				'To stop receiving notifications, log-in to %s and change your preferences.',
				'sitepress'
			),
			$notifications_link
		);

		return $this->render_email_footer( $bottom_text );
	}
}
