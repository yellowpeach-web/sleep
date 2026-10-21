<?php

use WPML\FP\Str;


class WPML_Attachment_Action implements IWPML_Action {

	private $sitepress;

	private $wpdb;

	public function __construct( SitePress $sitepress, wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	public function add_hooks() {
		if ( $this->is_admin_or_xmlrpc() ) {

			$active_languages = $this->sitepress->get_active_languages();

			if ( count( $active_languages ) > 1 ) {
				add_filter( 'views_upload', array( $this, 'views_upload_actions' ) );
			}
		}

		add_filter( 'attachment_link', array( $this->sitepress, 'convert_url' ), 10, 1 );
		add_filter( 'wp_delete_file', array( $this, 'delete_file_filter' ) );
	}

	private function is_admin_or_xmlrpc() {
		$is_admin  = is_admin();
		$is_xmlrpc = ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST );

		return $is_admin || $is_xmlrpc;
	}

	public function views_upload_actions( $views ) {
		$wpdb = $this->wpdb;

		global $pagenow;

		if ( 'upload.php' === $pagenow ) {
			$lang = $this->sitepress->get_current_language();

			foreach ( $views as $key => $view ) {
				$href_count = preg_match( '/(href=["\'])([\s\S]+?)\?([\s\S]+?)(["\'])/', $view, $href_matches );
				if ( $href_count && isset( $href_args ) ) {
					$href_base = $href_matches[2];
					wp_parse_str( $href_matches[3], $href_args );
				} else {
					$href_base = 'upload.php';
					$href_args = array();
				}

				if ( 'all' !== $lang ) {
					$res = apply_filters( 'wpml-media_view-upload-count', null, $key, $view, $lang );

					if ( null === $res ) {
						switch ( $key ) {
							case 'all':
								$res = $wpdb->get_col(
									$wpdb->prepare(
										"SELECT COUNT(p.id)
										 FROM {$wpdb->posts} AS p
										 JOIN {$wpdb->prefix}icl_translations AS t ON p.id = t.element_id
										 WHERE p.post_type = 'attachment'
											AND t.element_type = 'post_attachment'
											AND t.language_code = %s
											AND p.post_status != 'trash'",
										$lang
									)
								);
								break;
							case 'detached':
								$res = $wpdb->get_col(
									$wpdb->prepare(
										"SELECT COUNT(p.id)
										 FROM {$wpdb->posts} AS p
										 JOIN {$wpdb->prefix}icl_translations AS t ON p.id = t.element_id
										 WHERE p.post_type = 'attachment'
											AND t.element_type = 'post_attachment'
											AND t.language_code = %s
											AND p.post_status != 'trash'
											AND p.post_parent = 0",
										$lang
									)
								);
								break;
							case 'trash':
								$res = $wpdb->get_col(
									$wpdb->prepare(
										"SELECT COUNT(p.id)
										 FROM {$wpdb->posts} AS p
										 JOIN {$wpdb->prefix}icl_translations AS t ON p.id = t.element_id
										 WHERE p.post_type = 'attachment'
											AND t.element_type = 'post_attachment'
											AND t.language_code = %s
											AND p.post_status = 'trash'",
										$lang
									)
								);
								break;
							default:
								if ( isset( $href_args['post_mime_type'] ) ) {
									$mime_where = wp_post_mime_type_where( $href_args['post_mime_type'], 'p' );
									$sql        = "SELECT COUNT(p.id)
											 FROM {$wpdb->posts} AS p
											 JOIN {$wpdb->prefix}icl_translations AS t ON p.id = t.element_id
											 WHERE p.post_type = 'attachment'
												AND t.element_type = 'post_attachment'
												AND t.language_code = %s
												AND p.post_status != 'trash' " . $mime_where;
									$res = $wpdb->get_col( $wpdb->prepare( $sql, $lang ) );
								} else {
									$res = $wpdb->get_col(
										$wpdb->prepare(
											"SELECT COUNT(p.id)
											 FROM {$wpdb->posts} AS p
											 JOIN {$wpdb->prefix}icl_translations AS t ON p.id = t.element_id
											 WHERE p.post_type = 'attachment'
												AND t.element_type = 'post_attachment'
												AND t.language_code = %s
												AND p.post_status != 'trash'
												AND p.post_mime_type LIKE %s",
											$lang,
											$key . '%'
										)
									);
								}
						}
					}
					$view = preg_replace( '/\((\d+)\)/', '(' . $res[0] . ')', $view );
				}

				$href_args['lang'] = $lang;
				$href_args         = array_map( 'urlencode', $href_args );
				$new_href          = add_query_arg( $href_args, $href_base );
				$views[ $key ]     = preg_replace( '/(href=["\'])([\s\S]+?)(["\'])/', '$1' . $new_href . '$3', $view );
			}
		}

		return $views;
	}

	public function delete_file_filter( $file ) {
		if ( $file && $this->is_in_uploads_dir( $file ) ) {
			$file_name           = $this->get_file_name( $file );
			$scaled_file_name    = $this->get_file_name( $file, true );
			$wpdb                = $this->wpdb;
			$attachment          = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT pm.meta_id, pm.post_id FROM {$wpdb->postmeta} AS pm WHERE pm.meta_value IN ( %s, %s ) AND pm.meta_key='_wp_attached_file'",
					$file_name,
					$scaled_file_name
				)
			);

			if ( ! empty( $attachment ) ) {
				$file = '';
			}
		}

		return $file;
	}

	private function is_in_uploads_dir( $file ) {
		$upload_dir = wp_get_upload_dir();
		$basedir    = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';

		if ( '' === $basedir ) {
			return true;
		}

		$normalized_base = rtrim( wp_normalize_path( $basedir ), '/' ) . '/';

		return 0 === strpos( wp_normalize_path( (string) $file ), $normalized_base );
	}

	private function get_file_name( $file, $scaled = false ) {
		$file_name  = $this->get_file_name_without_size_from_full_name( $file, $scaled );
		$upload_dir = wp_upload_dir();
		$path_parts = $file ? explode( "/", Str::replace( $upload_dir['basedir'], '', $file ) ) : [];

		if ( $path_parts ) {
			$path_parts[ count( $path_parts ) - 1 ] = $file_name;
		}

		return ltrim( implode( '/', $path_parts ), '/' );
	}

	private function get_file_name_without_size_from_full_name( $file, $scaled = false ) {
		$extension       = pathinfo( $file, PATHINFO_EXTENSION );
		$replace_pattern = '/(-\d+x\d+)?\.' . preg_quote( $extension, '/' ) . '$/';
		$replacement     = ( $scaled ? '-scaled' : '' ) . ".$extension";
		$filename_parts  = explode( '/', preg_replace( $replace_pattern, $replacement, $file ) );

		return array_pop( $filename_parts );
	}
}
