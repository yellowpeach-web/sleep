<?php

class WPML_Support_Page {
	private $wpml_wp_api;

	public function __construct( &$wpml_wp_api ) {
		$this->wpml_wp_api = &$wpml_wp_api;
		$this->init_hooks();
	}

	public function display_compatibility_issues() {
		$message = $this->get_message();
		$this->render_message( $message );
	}

	private function get_message() {
		$message = '';
		if ( ! $this->wpml_wp_api->extension_loaded( 'libxml' ) ) {
			$message .= $this->missing_extension_message();
			if ( $this->wpml_wp_api->version_compare_naked( $this->wpml_wp_api->phpversion(), '7.0.0', '>=' ) ) {
				$message .= $this->missing_extension_message_for_php7();
			}
			$message .= $this->contact_the_admin();

			return $message;
		}

		return $message;
	}

	private function init_hooks() {
		add_action( 'wpml_support_page_after', array( $this, 'display_compatibility_issues' ) );
	}

	private function missing_extension_message() {
		return '<p class="missing-extension-message">'
		       /* translators: Notice on the Support screen when a PHP extension WPML needs is missing. "It looks like" is about this site. %1$s: the name of that extension, in bold, %2$s: a link, already wrapped in its tags, to the page that explains how to install it. */
		       . esc_html__( 'It looks like the %1$s extension, which is required by WPML, is not installed. Please refer to this link to know how to install this extension: %2$s.', 'sitepress' )
		       . '</p>';
	}

	private function missing_extension_message_for_php7() {
		/* translators: Second line of that notice, shown when a system update may have dropped the extension. %3$s: an example package name, in a code box. */
		return '<p class="missing-extension-message-for-php7">' . esc_html__( 'Your system may have removed this extension during an update. Install your PHP XML package, for example %3$s on Debian or Ubuntu. Then restart your web server.', 'sitepress' ) . '</p>';
	}

	private function xml_package_name() {
		$parts = explode( '.', (string) $this->wpml_wp_api->phpversion() );
		$short = isset( $parts[1] ) ? $parts[0] . '.' . $parts[1] : $parts[0];

		return 'php' . $short . '-xml';
	}

	private function contact_the_admin() {
		return '<p class="contact-the-admin">' . esc_html__( 'You may need to contact your server administrator or your hosting company to install this extension.', 'sitepress' ) . '</p>';
	}

	private function render_message( $message ) {
		if ( $message ) {
			$libxml_text    = '<strong>libxml</strong>';
			$libxml_link    = '<a href="http://php.net/manual/en/book.libxml.php" target="_blank">http://php.net/manual/en/book.libxml.php</a>';
			$libxml_package = '<code>' . esc_html( $this->xml_package_name() ) . '</code>';
			echo '<div class="icl-admin-message icl-admin-message-icl-admin-message-warning icl-admin-message-warning error">';
			echo wp_kses_post( sprintf( $message, $libxml_text, $libxml_link, $libxml_package ) );
			echo '</div>';
		}
	}
}
