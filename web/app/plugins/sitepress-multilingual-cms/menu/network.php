<?php

$wp_list_table = _get_list_table( 'WP_MS_Sites_List_Table' );
$pagenum       = $wp_list_table->get_pagenum();
$id            = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;
$wp_list_table->prepare_items();

$total_pages = $wp_list_table->get_pagination_arg( 'total_pages' );
if ( $pagenum > $total_pages && $total_pages > 0 ) {
	wp_redirect( add_query_arg( 'paged', $total_pages ) );
	exit;
}


$msg = '';
if ( isset( $_REQUEST['updated'] ) && 'true' === $_REQUEST['updated'] && ! empty( $_REQUEST['action'] ) ) {
	switch ( $_REQUEST['action'] ) {
		case 'deactivatewpml':
			$msg = esc_html__( 'WPML has been deactivated for the selected site.', 'sitepress' );
			break;
		case 'activatewpml':
			$msg = esc_html__( 'WPML has been activated for the selected site.', 'sitepress' );
			break;
		default:
			$msg = false;
	}

	if ( $msg ) {
		$msg = '<div class="updated" id="message"><p>' . $msg . '</p></div>';
	}
}

$text = isset( $text ) ? $text : '';

?>

<div class="wrap">
	<h2><?php echo esc_html__( 'WPML Network Setup', 'sitepress' ); ?>
		<?php
		if ( isset( $_REQUEST['s'] ) && $_REQUEST['s'] ) {
			printf( '<span class="subtitle">' . esc_html__( 'Search results for &#8220;%s&#8221;' ) . '</span>', filter_var( $_REQUEST['s'], FILTER_SANITIZE_SPECIAL_CHARS ) );
		}
		?>
	</h2>

	<form action="" method="get" id="ms-search">
		<p class="search-box">
			<label class="screen-reader-text" for="icl_ss"><?php echo esc_html( $text ); ?>:</label>
			<input type="hidden" name="page" value="<?php echo esc_attr( \WPML\SuperGlobals\Request::page() ); ?>"/>
			<input type="text" id="icl_ss" name="s" value="<?php _admin_search_query(); ?>"/>
			<?php submit_button( /* translators: Button label above a list, and the text inside the search field, for looking something up. Verb, imperative. */ __( 'Search', 'sitepress' ), 'button', '', false, array( 'id' => 'search-submit' ) ); ?>

		</p>
	</form>

	<?php echo $msg; ?>

	<p class="description">
		<?php
		/* translators: Help text for super admins on Network Admin → WPML → Network settings. "WPML → Support → Reset WPML" is the menu path inside one site's dashboard. */
		esc_html_e( "To reset WPML on a site, install the WPML Troubleshooting plugin. Then go to that site's dashboard and open WPML → Support → Reset WPML. If WPML is inactive on the site, activate it here first.", 'sitepress' );
		?>
	</p>

	<br/>

	<?php if ( empty( $wp_list_table->items ) ) : ?>
		<?php $wp_list_table->no_items(); ?>
	<?php else : ?>

        <div class="tablenav top">
			<?php
			$wp_list_table->pagination( 'bottom' );
			?>
        </div>

        <table class="wp-list-table widefat">
            <thead>
            <tr>
                <th><?php /* translators: Column heading in the table of sites of the network: one site of the network. */ esc_html_e( 'Site', 'sitepress' ); ?></th>
                <th><?php /* translators: Column heading in tables of the WPML admin, above the cells that say how far something has got. Noun, singular. */ esc_html_e( 'Status', 'sitepress' ); ?></th>
            </tr>
            </thead>
            <tfoot>
            <tr>
                <th><?php /* translators: Column heading in the table of sites of the network: one site of the network. */ esc_html_e( 'Site', 'sitepress' ); ?></th>
                <th><?php /* translators: Column heading in tables of the WPML admin, above the cells that say how far something has got. Noun, singular. */ esc_html_e( 'Status', 'sitepress' ); ?></th>
            </tr>
            </tfoot>
            <tbody>
			<?php
			$status_list = array(
				'archived' => array( 'site-archived', esc_html__( 'Archived' ) ),
				'spam'     => array( 'site-spammed', esc_html_x( 'Spam', 'site' ) ),
				'deleted'  => array( 'site-deleted', esc_html__( 'Deleted' ) ),
				'mature'   => array( 'site-mature', esc_html__( 'Mature' ) ),
			);
			$class       = '';

			foreach ( $wp_list_table->items as $blog ) :

				if ( class_exists( 'WP_Site' ) && $blog instanceof WP_Site ) {
					$blog = object_to_array( $blog );
				}

				$class = ( 'alternate' == $class ) ? '' : 'alternate';

				$blog_states = array();
				foreach ( $status_list as $status => $col ) {
					if ( 1 === get_blog_status( $blog['blog_id'], $status ) ) {
						$class         = $col[0];
						$blog_states[] = $col[1];
					}
				}

				$blog_state = '';
				if ( ! empty( $blog_states ) ) {
					$state_count = count( $blog_states );
					$i           = 0;
					$blog_state .= ' - ';
					foreach ( $blog_states as $state ) {
						++ $i;
						( $i == $state_count ) ? $sep = '' : $sep = ', ';
						$blog_state                  .= "<span class='post-state'>" . esc_html( $state . $sep ) . '</span>';
					}
				}

				?>
				<tr class="<?php echo esc_attr( $class ); ?>">
					<td class='column-blogname blogname'>
						<?php
						$current_site = get_current_site();
						$blog_name    = $blog['path'];
						if ( ( is_subdomain_install() ) ) {
							$blog_name = str_replace( '.' . $current_site->domain, '', $blog['domain'] );
						}
						?>
						<a href="<?php echo esc_url( network_admin_url( 'site-info.php?id=' . $blog['blog_id'] ) ); ?>"
						   class="edit"><?php echo esc_html( $blog_name ) . $blog_state; ?></a>
						<?php
						$actions = array(
							'edit'       => '',
							'backend'    => '',
							'activate'   => '',
							'deactivate' => '',
							'archive'    => '',
							'unarchive'  => '',
							'spam'       => '',
							'unspam'     => '',
							'delete'     => '',
							'visit'      => '',
						);

						/* translators: Link text that opens something for changing: a date, a language or a translation. Verb, imperative. */
						$actions['edit']    = '<span class="edit"><a href="' . esc_url( network_admin_url( 'site-info.php?id=' . $blog['blog_id'] ) ) . '">' . esc_html__( 'Edit', 'sitepress' ) . '</a></span>';
						/* translators: Link in the table of sites of the network that opens the admin of that site. */
						$actions['backend'] = "<span class='backend'><a href='" . esc_url( get_admin_url( $blog['blog_id'] ) ) . "' class='edit'>" . esc_html__( 'Dashboard', 'sitepress' ) . '</a></span>';
						/* translators: Link in the table of sites of the network that opens the site itself. Verb, imperative. */
						$actions['visit']   = "<span class='view'><a href='" . esc_url( get_home_url( $blog['blog_id'] ) ) . "' rel='permalink'>" . esc_html__( 'Visit', 'sitepress' ) . '</a></span>';

						$actions = apply_filters( 'manage_sites_action_links', array_filter( $actions ), $blog['blog_id'], $blog_name );
						echo $wp_list_table->row_actions( $actions );
						?>
                    </td>
                    <td>
						<?php switch_to_blog( $blog['blog_id'] ); ?>
						<?php if ( get_option( '_wpml_inactive', false ) ) : ?>
							<?php /* translators: Value in the table of sites of the network: WPML is not turned on for this site. Adjective. */ esc_html_e( 'Inactive', 'sitepress' ); ?>
                            <div class="row-actions">
                                <a href="<?php echo esc_url( wp_nonce_url( network_admin_url( 'sites.php?action=activatewpml&amp;id=' . (int) $blog['blog_id'] ), 'activatewpml' ) ); ?>"><?php /* translators: Link text that opens the plugins screen so an add-on can be turned on. Verb, imperative. */ esc_html_e( 'Activate', 'sitepress' ); ?></a>
                            </div>
						<?php else : ?>
							<?php /* translators: Value in a table saying that the thing the row is about is turned on and in use. Adjective. */ esc_html_e( 'Active', 'sitepress' ); ?>
							<div class="row-actions">
								<?php
								global $current_blog;
								if ( $blog['blog_id'] != $current_blog->blog_id ) {
									?>
									<a href="<?php echo esc_url( wp_nonce_url( network_admin_url( 'sites.php?action=deactivatewpml&amp;id=' . (int) $blog['blog_id'] ), 'deactivatewpml' ) ); ?>"><?php /* translators: Button label on the translation services screen: stop using this translation service. Verb, imperative. */ esc_html_e( 'Deactivate', 'sitepress' ); ?></a>
									<?php
								}
								?>
							</div>
						<?php endif; ?>
						<?php restore_current_blog(); ?>
					</td>
                </tr>
			<?php endforeach; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
			<?php
			$wp_list_table->pagination( 'bottom' );
			?>
        </div>

	<?php endif ?>

</div>
