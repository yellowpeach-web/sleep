<?php

class WPML_ST_Translations_File_Scan_UI_Block {
	const NOTICES_GROUP               = 'wpml-st-mo-scan';
	const NOTICES_MO_SCANNING_BLOCKED = 'mo-scanning-blocked';

	const REPAIR_ROUTE = 'repair_strings_table_index';

	const REPAIR_PATH = 'admin.php?page=sitepress-multilingual-cms/menu/support.php&tool=db-strings#strings-index';

	private $notices;

	private $link;

	public function __construct( WPML_Notices $notices ) {
		$this->notices = $notices;
		$this->link    = \WPML\ST\OutboundLinks\OutboundLinks::to(
			'https://app.wpml.org/support',
			array(
				'medium'   => 'notice',
				'campaign' => 'troubleshooting',
			)
		);
	}

	public function block_ui() {
		$this->disable_option();
		$this->add_hooks();
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'remove_default_notice' ] );
		add_action( 'admin_init', [ $this, 'display_notice' ] );
	}

	public function unblock_ui() {
		add_action( 'admin_init', [ $this, 'remove_notice' ] );
	}

	public function remove_notice() {
		$this->notices->remove_notice( self::NOTICES_GROUP, self::NOTICES_MO_SCANNING_BLOCKED );
	}

	private function disable_option() {
		add_filter( 'wpml_localization_options_ui_model', array( $this, 'disable_option_handler' ) );
	}

	public function disable_option_handler( $model ) {
		if ( ! isset( $model['top_options'][0] ) ) {
			return $model;
		}

		$model['top_options'][0]['disabled'] = true;
		$model['top_options'][0]['message']  = $this->get_short_notice_message();

		return $model;
	}

	private function get_repair_url() {
		$routes = apply_filters( 'wpml_support_debug_actions', array() );

		if ( ! is_array( $routes ) || ! isset( $routes[ self::REPAIR_ROUTE ] ) ) {
			return null;
		}

		return admin_url( self::REPAIR_PATH );
	}

	private function get_repair_message( $repair_url ) {
		/* translators: In the notice shown when WPML cannot import .mo files because the String Translation table needs a repair; "the table" is that String Translation table. */
		$message = __( 'WPML can repair the table in one click.', 'wpml-string-translation' );

		$message .= ' ' . sprintf(
			/* translators: Last line of the notice shown when WPML cannot import .mo files because the String Translation table needs a repair. %s: the address of the repair on WPML → Support, which fills the link. "Repair the strings table" is the label of the button there. */
			__( 'Go to <a href="%s">WPML → Support → Database & strings maintenance</a> and click <b>Repair the strings table</b>.', 'wpml-string-translation' ),
			esc_url( $repair_url )
		);

		return $message;
	}

	private function get_short_notice_message() {
		$message = _x(
			'WPML cannot replace .mo files because of technical problems in the <b>String Translation</b> table.',
			'MO Import blocked short 1/3',
			'wpml-string-translation'
		);

		$repair_url = $this->get_repair_url();
		if ( null !== $repair_url ) {
			return '<span class="icl_error_text" >' . $message . ' ' . $this->get_repair_message( $repair_url ) . '</span>';
		}

		$message .= ' ' . _x(
			'WPML support team knows how to fix it.',
			'MO Import blocked short 2/3',
			'wpml-string-translation'
		);

		$message .= ' ' . sprintf(
			/* translators: Last line of the notice shown when WPML cannot read the translation files. %s: the address of the WPML support forum, which fills the link. */
			_x(
				'Please add a message in the relevant <a href="%s" target="_blank" >support thread</a> and we\'ll fix it for you.',
				'MO Import blocked short 3/3',
				'wpml-string-translation'
			),
			$this->link
		);

		return '<span class="icl_error_text" >' . $message . '</span>';
	}

	public function display_notice() {
		$message = _x(
			'There is a problem with the <b>String Translation</b> table in your site.',
			'MO Import blocked 1/4',
			'wpml-string-translation'
		);

		$message .= ' ' . _x(
			'This problem is not causing a problem running the site right now, but can become a critical issue in the future.',
			'MO Import blocked 2/4',
			'wpml-string-translation'
		);

		$repair_url = $this->get_repair_url();
		if ( null !== $repair_url ) {
			$message .= ' ' . $this->get_repair_message( $repair_url );
		} else {
			$message .= ' ' . _x(
				'WPML support team knows how to fix it.',
				'MO Import blocked 3/4',
				'wpml-string-translation'
			);

			$message .= ' ' . sprintf(
				/* translators: Last line of the notice shown when WPML cannot read the translation files. %s: the address of the WPML support forum, which fills the link. */
				_x(
					'Please add a message in the relevant <a href="%s" target="_blank">support thread</a> and we\'ll fix it for you.',
					'MO Import blocked 4/4',
					'wpml-string-translation'
				),
				$this->link
			);
		}

		$notice = $this->notices->create_notice( self::NOTICES_MO_SCANNING_BLOCKED, $message, self::NOTICES_GROUP );

		$notice->set_css_class_types( 'error' );
		$notice->set_dismissible( false );
		$restricted_pages = array(
			'tm/menu/main.php',
			'tm/menu/settings',
			'sitepress-multilingual-cms/menu/languages.php',
			'sitepress-multilingual-cms/menu/menu-sync/menus-sync.php',
			'sitepress-multilingual-cms/menu/support.php',
			'sitepress-multilingual-cms/menu/taxonomy-translation.php',
			'sitepress-multilingual-cms/menu/theme-localization.php',
			'wpml-media',
			'wpml-package-management',
			'wpml-string-translation/menu/string-translation.php',
			'wpml-sticky-links',
			'wpml-translation-management/menu/translations-queue.php',
			'wpml-translation-management/menu/main.php',
		);
		$notice->set_restrict_to_pages( $restricted_pages );

		$this->notices->add_notice( $notice );
	}

	public function remove_default_notice() {
		$this->notices->remove_notice( WPML_ST_Themes_And_Plugins_Settings::NOTICES_GROUP, 'wpml_st_faster_settings' );
	}
}
