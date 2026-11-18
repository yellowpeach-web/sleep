<?php

namespace WPML\TranslationPolicy\Duplicate;

use WPML\TranslationPolicy\Settings\Section;

class RetiredNotice implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const OPTION_COUNT = 'wpml_retired_duplicates_count';

	const OPTION_ARMED = 'wpml_retired_duplicates_notice';

	const ID    = 'retired-duplicates';
	const GROUP = 'wpml-translation-policy';

	public function add_hooks(): void {
		if ( ! DuplicateReaders::keepsInStep() ) {
			add_action( 'admin_init', [ $this, 'publish' ] );
		}
	}

	public function count( int $rewritten ) {
		if ( $rewritten > 0 ) {
			update_option( self::OPTION_COUNT, (int) get_option( self::OPTION_COUNT, 0 ) + $rewritten, false );
		}
	}

	public function arm() {
		$count = (int) get_option( self::OPTION_COUNT, 0 );
		delete_option( self::OPTION_COUNT );
		if ( $count > 0 ) {
			update_option( self::OPTION_ARMED, $count, true );
		}
	}

	public function publish() {
		$count = (int) get_option( self::OPTION_ARMED, 0 );
		if ( $count < 1 || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = new \WPML_Notice( self::ID, self::text( $count ), self::GROUP );
		$notice->set_css_class_types( 'info' );
		$notice->set_dismissible( true );
		$notice->add_capability_check( [ 'manage_options' ] );
		wpml_get_admin_notices()->add_notice( $notice );
	}

	public static function text( int $count ): string {
		$link = '<a href="' . esc_url( \WPML_Admin_URL::multilingual_setup( 1 ) . '#' . Section::SECTION_ID ) . '">'
			. esc_html__( 'Translation editor and method', 'sitepress' ) . '</a>';

		return '<h3>' . esc_html(
			/* translators: Heading of the notice after WPML turned the duplicated posts and pages into translations. */
			_n( 'Your duplicate is now a translation', 'Your duplicates are now translations', $count, 'sitepress' )
		) . '</h3>'
			. '<p>' . esc_html(
				sprintf(
					/* translators: %d: how many duplicated posts and pages WPML turned into translations. */
					_n( 'WPML turned %d duplicate into a translation.', 'WPML turned %d duplicates into translations.', $count, 'sitepress' ),
					$count
				)
			) . '</p>'
			. '<p>' . esc_html(
				_n(
					'It still has the text of the original language. WPML no longer updates it when you edit the original.',
					'They still have the text of the original language. WPML no longer updates them when you edit the original.',
					$count,
					'sitepress'
				)
			) . '</p>'
			. '<p>' . sprintf(
				esc_html(
					/* translators: %s: a link to the "Translation editor and method" section of WPML → Settings. */
					_n(
						'To translate it automatically, choose "PTC" in %s. You can also translate it yourself.',
						'To translate them automatically, choose "PTC" in %s. You can also translate them yourself.',
						$count,
						'sitepress'
					)
				),
				$link
			) . '</p>';
	}
}
