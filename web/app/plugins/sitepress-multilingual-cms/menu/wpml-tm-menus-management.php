<?php

use WPML\Setup\Option;
use function WPML\Container\make;

class WPML_TM_Menus_Management extends WPML_TM_Menus {

	const SKIP_TM_WIZARD_META_KEY = 'wpml_skip_tm_wizard';

	private $admin_sections;

	public function __construct() {
		$this->admin_sections = WPML\Container\make( 'WPML_TM_Admin_Sections' );
		$this->admin_sections->init_hooks();

		parent::__construct();
	}

	protected function render_main() {
	}

	protected function build_tab_items() {
		$this->build_tp_com_log_item();
	}

	private function build_tp_com_log_item() {
		if ( isset( $_GET['sm'] ) && 'com-log' === $_GET['sm'] ) {
			$this->tab_items['com-log'] = array(
				/* translators: Name of the section that shows what the site and the translation service said to each other. */
				'caption'          => __( 'Communication Log', 'sitepress' ),
				'current_user_can' => 'manage_options',
				'callback'         => array( $this, 'build_tp_com_log' ),
				'order'            => 1000000,
			);
		}
	}

	public function build_tp_com_log() {
		if ( isset( $_POST['tp-com-clear-log'] ) ) {
			WPML_TranslationProxy_Com_Log::clear_log();
		}

		if ( isset( $_POST['tp-com-disable-log'] ) ) {
			WPML_TranslationProxy_Com_Log::set_logging_state( false );
		}

		if ( isset( $_POST['tp-com-enable-log'] ) ) {
			WPML_TranslationProxy_Com_Log::set_logging_state( true );
		}

		$action_url = esc_attr( 'admin.php?page=' . WPML_TM_FOLDER . $this->get_page_slug() . '&sm=' . $_GET['sm'] );
		$com_log    = WPML_TranslationProxy_Com_Log::get_log();

		?>

		<form method="post" id="tp-com-log-form" name="tp-com-log-form" action="<?php echo $action_url; ?>">

			<?php if ( WPML_TranslationProxy_Com_Log::is_logging_enabled() ) : ?>

				<?php echo /* translators: Text under that heading. "It" is the log the section shows. */ esc_html__( "This is a log of the communication between your site and the translation system. It doesn't include any private information and allows WPML support to help with problems related to sending content to translation.", 'sitepress' ); ?>

				<br />
				<br />
				<?php if ( $com_log != '' ) : ?>
					<textarea wrap="off" readonly="readonly" rows="16" style="font-size:10px; width:100%"><?php echo $com_log; ?></textarea>
					<br />
					<br />
					<input class="button-secondary" type="submit" name="tp-com-clear-log" value="<?php echo /* translators: Button label: empty the log that is being shown. Verb phrase, imperative. */ esc_attr__( 'Clear log', 'sitepress' ); ?>">
				<?php else : ?>
					<strong><?php echo esc_html__( 'The communication log is empty.', 'sitepress' ); ?></strong>
					<br />
					<br />
				<?php endif; ?>

				<input class="button-secondary" type="submit" name="tp-com-disable-log" value="<?php echo /* translators: Button label: stop keeping that log. Verb phrase, imperative. */ esc_attr__( 'Disable logging', 'sitepress' ); ?>">

			<?php else : ?>
				<?php echo esc_html__( 'Communication logging is currently disabled. To allow WPML support to help you with issues related to sending content to translation, you need to enable the communication logging.', 'sitepress' ); ?>

				<br />
				<br />
				<input class="button-secondary" type="submit" name="tp-com-enable-log" value="<?php echo /* translators: Button label: start keeping that log. Verb phrase, imperative. */ esc_attr__( 'Enable logging', 'sitepress' ); ?>">

			<?php endif; ?>

		</form>
		<?php
	}

	protected function get_page_slug() {
		return WPML_Translation_Management::PAGE_SLUG_MANAGEMENT;
	}

	protected function get_default_tab() {
		return 'dashboard';
	}

	public static function getInstance() {
	 	return make( self::class );
	}
}
