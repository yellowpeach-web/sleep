<?php

namespace WPML\TM\ATE\ClonedSites\Rebind;

use WPML\TM\ATE\ClonedSites\NoticeBrand;

class Notice implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	public function add_hooks() {
		add_action( 'admin_notices', [ $this, 'render' ] );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo $this->build();
	}

	public function build() {
		if ( ! State::get() ) {
			return '';
		}

		$headline = esc_html__( 'Your existing translations were reconnected', 'sitepress' );

		$items = State::items();

		if ( null === $items ) {
			$body = esc_html__(
				'WPML connected this site to the translation project it already had. Your translations, translation memory and remaining translation words are still there.',
				'sitepress'
			);
		} else {
			$body = sprintf(
				/* translators: %s is a number of translated items, e.g. 1,240. */
				esc_html( _n(
					'WPML connected this site to the translation project it already had. %s translated item came back with it, along with your translation memory and remaining translation words.',
					'WPML connected this site to the translation project it already had. %s translated items came back with it, along with your translation memory and remaining translation words.',
					$items,
					'sitepress'
				) ),
				'<strong>' . esc_html( number_format_i18n( $items ) ) . '</strong>'
			);
		}

		$startFresh = esc_html__( 'Start fresh instead', 'sitepress' );

		$startFreshHint = esc_html__(
			'Puts this site on a new, empty translation project. The existing one keeps its translations and remaining translation words.',
			'sitepress'
		);

		/* translators: Button label that closes a notice once the reader has read it. It is the everyday way of saying "I understand". */
		$dismiss = esc_html__( 'Got it', 'sitepress' );

		return '<div class="notice notice-info wpml-ate-project-rebind">'
		       . NoticeBrand::row()
		       . '<p><strong>' . $headline . '</strong></p>'
		       . '<p>' . $body . '</p>'
		       . '<p><a class="button wpml-ate-rebind-start-fresh" href="'
		       . esc_url( Actions::startFreshUrl() ) . '">' . $startFresh . '</a></p>'
		       . '<p class="description">' . $startFreshHint . '</p>'
		       . '<p><a class="wpml-ate-rebind-dismiss" href="'
		       . esc_url( Actions::dismissUrl() ) . '">' . $dismiss . '</a></p>'
		       . '</div>';
	}
}
