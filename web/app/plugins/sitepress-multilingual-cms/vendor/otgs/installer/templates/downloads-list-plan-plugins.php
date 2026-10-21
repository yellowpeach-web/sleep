<?php

use OTGS\Installer\CommercialTab\DownloadFilter;
use OTGS\Installer\CommercialTab\DownloadsList;
use OTGS\Installer\CommercialTab\PlanPluginsVisibility;

$plan_downloads = [];
foreach ( $plan_plugins['downloads'] as $download_id => $download ) {
	if ( DownloadFilter::shouldDisplayRecord( $download_id ) ) {
		$plan_downloads[ $download_id ] = $download;
	}
}

if ( ! $plan_downloads ) {
	return;
}

$plan_hidden  = PlanPluginsVisibility::isHidden();
$plan_rows_id = 'installer-plan-plugins-' . $repository_id;
$hide_label   = __( 'Hide plugins not in your plan', 'installer' );
$show_label   = __( 'Show plugins not in your plan', 'installer' );
/* translators: %1$s: the plan's name from the products feed, e.g. "Multilingual CMS"; %2$d: how many plugins are listed */
$plan_title = sprintf( __( 'Plugins included with the %1$s plan (%2$d)', 'installer' ), $plan_plugins['plan'], count( $plan_downloads ) );
?>
<tbody class="installer-plugins-group">
<tr>
	<th>&nbsp;</th>
	<th scope="rowgroup" colspan="6">
		<div class="installer-plugins-group-bar">
			<span class="installer-plugins-group-title"><?php echo esc_html( $plan_title ); ?></span>
			<a href="<?php echo esc_url( $plan_plugins['url'] ); ?>"><?php esc_html_e( 'See the CMS plan', 'installer' ); ?></a>
			<button type="button" class="button-link installer-plugins-group-toggle js-installer-plan-plugins-toggle"
					aria-expanded="<?php echo $plan_hidden ? 'false' : 'true'; ?>"
					aria-controls="<?php echo esc_attr( $plan_rows_id ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( PlanPluginsVisibility::NONCE_ACTION ) ); ?>"
					data-label-hide="<?php echo esc_attr( $hide_label ); ?>"
					data-label-show="<?php echo esc_attr( $show_label ); ?>"><?php echo esc_html( $plan_hidden ? $show_label : $hide_label ); ?></button>
		</div>
	</th>
</tr>
</tbody>
<tbody id="<?php echo esc_attr( $plan_rows_id ); ?>" class="installer-plugins-unavailable"<?php echo $plan_hidden ? ' hidden' : ''; ?>>
<?php
foreach ( $plan_downloads as $download_id => $download ) {
	echo DownloadsList::getUnavailableRow( (string) $download_id, $download );
}
?>
</tbody>
