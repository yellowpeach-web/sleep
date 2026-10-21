<?php

use WPML\Support\Initializer;

$data = Initializer::getData();

?>


<div class="wrap">

	<h2><?php /* translators: Title of the screen that gathers facts for the support team, and the item in the WPML menu that opens it. Noun: help from the WPML support team. */ esc_html_e( 'Support', 'sitepress' ); ?></h2>

	<?php if ( $data['showMinRequirementsComponent'] ) : ?>
		<wc-minimum-requirements
			items="<?php echo $data['serializedInvalidRequirements']; ?>"
			compact-variant="true">
		</wc-minimum-requirements>
	<?php endif; ?>

	<p style="margin-top: 20px;">
		<?php
		printf(
			/* translators: Line on the Support screen. %1$s: the opening tag of a link to the WPML support pages, %2$s: its closing tag. */
			esc_html__( 'Technical support for clients is available via %1$sWPML support%2$s.', 'sitepress' ),
			'<a target="_blank" href="' . esc_url( \WPML\OutboundLinks\OutboundLinks::to( \WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl::URL, array( 'medium' => 'support', 'campaign' => 'support' ) ) ) . '">',
			'</a>'
		);
		?>
	</p>

	<?php
	$wpml_plugins_list = SitePress::get_installed_plugins();

	echo '
        <table class="widefat" style="width: auto;">
            <thead>
                <tr>
                    <th>' . /* translators: Column heading in the table of WPML plugins on the Support screen. */ esc_html__( 'Plugin Name', 'sitepress' ) . '</th>
                    <th style="text-align:right">' . /* translators: Column heading in tables of the WPML admin, above the cells that say how far something has got. Noun, singular. */ esc_html__( 'Status', 'sitepress' ) . '</th>
                    <th>' . /* translators: Value in a table saying that the thing the row is about is turned on and in use. Adjective. */ esc_html__( 'Active', 'sitepress' ) . '</th>
                    <th>' . /* translators: Label of the row that gives the version number of a piece of software, on the Support screen. */ esc_html__( 'Version', 'sitepress' ) . '</th>
                </tr>
            </thead>
            <tbody>
        ';

	foreach ( $wpml_plugins_list as $name => $plugin_data ) {
		$plugin_name = $name;
		$file        = $plugin_data['file'];
		$dir         = dirname( $file );

		echo '<tr>';
		echo '<td><i class="otgs-ico-' . esc_attr( $plugin_data['slug'] ) . '"></i> ' . esc_html( $plugin_name ) . '</td>';
		echo '<td align="right">';
		if ( empty( $plugin_data['plugin'] ) ) {
			/* translators: Value in the table of WPML plugins on the Support screen: the plugin is not on this site. Past participle used as a state. */
			echo esc_html__( 'Not installed', 'sitepress' );
		} else {
			/* translators: Value in the table of WPML plugins on the Support screen: the plugin is on this site. Past participle used as a state. */
			echo esc_html__( 'Installed', 'sitepress' );
		}
		echo '</td>';
		echo '<td align="center">';
		echo isset( $file ) && is_plugin_active( $file ) ? /* translators: Option in a dropdown, and the value shown in a table cell, meaning that the setting is turned on. */ esc_html__( 'Yes', 'sitepress' ) : /* translators: Option in a dropdown, and the value shown in a table cell, meaning that the setting is turned off. */ esc_html__( 'No', 'sitepress' );
		echo '</td>';
		echo '<td align="right">';
		/* translators: Shown in a table cell in place of a value that is not there. Keep the short form your language uses for "not available". */
		echo isset( $plugin_data['plugin']['Version'] ) ? esc_html( $plugin_data['plugin']['Version'] ) : esc_html__( 'n/a', 'sitepress' );
		echo '</td>';
		echo '</tr>';
	}

	echo '
            </tbody>
        </table>
    ';

	?>

	<p style="margin-top: 20px;">
		<?php
		/* translators: Line on the Support screen. %1$s: the opening tag of a link to the debug information screen, %2$s: its closing tag. The words between them are the name of that screen. */
		printf( esc_html__( 'For retrieving debug information if asked by support person, use the %1$sdebug information%2$s page.', 'sitepress' ), '<a href="' . esc_url( admin_url( 'admin.php?page=' . WPML_PLUGIN_FOLDER . '/menu/debug-information.php' ) ) . '">', '</a>' );
		?>
	</p>

	<?php
	$support_info_factory = new WPML_Support_Info_UI_Factory();
	$support_info_ui      = $support_info_factory->create();
	echo $support_info_ui->show();

	$xml_config_log_factory = new WPML_XML_Config_Log_Factory();
	$xml_config_log_ui      = $xml_config_log_factory->create_ui();
	echo $xml_config_log_ui->show();

	do_action( 'wpml_support_page_after' );

	do_action( 'otgs_render_installer_support_link' );
	?>

</div>
