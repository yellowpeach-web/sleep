<?php
?>
<div class="otgs-installer-repo-header">
	<h3 id="repository-<?php echo esc_attr( $repository_id ); ?>"><?php echo esc_html( strtoupper( $repository_id ) ); ?></h3>
</div>

<table class="widefat otgs_wp_installer_table" id="installer_repo_<?php echo esc_attr( $repository_id ); ?>">
	<tr>
		<td class="otgsi_register_product_wrap" colspan="2">
			<p>
				<?php _e( 'Information about new versions is invalid. It may be a temporary communication problem, please check for updates again.', 'installer' ); ?>
			</p>
			<p>
				<a class="button-secondary" href="<?php echo esc_url( admin_url( 'update-core.php?force-check=1' ) ); ?>">
					<?php esc_html_e( 'Check again', 'installer' ); ?>
				</a>
			</p>
		</td>
	</tr>
</table>
