<?php

class WPML_Media_File {

	private $attachment_id;
	private $wp_filesystem;
	private $wpdb;

	public function __construct( $attachment_id, WP_Filesystem_Base $wp_filesystem, wpdb $wpdb ) {
		$this->wp_filesystem = $wp_filesystem;
		$this->attachment_id = $attachment_id;
		$this->wpdb          = $wpdb;
	}

	public function delete() {
		$relative_file_path = get_post_meta( $this->attachment_id, '_wp_attached_file', true );

		if ( ! $relative_file_path || $this->file_is_shared( $relative_file_path, $this->attachment_id ) ) {
			return;
		}

		$this->wp_filesystem->delete( $this->get_full_file_upload_path( $relative_file_path ), false, 'f' );

		$subdir = dirname( $relative_file_path );
		$subdir = '.' === $subdir ? '' : $subdir . '/';

		$files = array();

		$attachment_meta_data = wp_get_attachment_metadata( $this->attachment_id );

		if ( is_array( $attachment_meta_data ) ) {
			if ( ! empty( $attachment_meta_data['sizes'] ) ) {
				$files = $this->add_size_files( $files, $attachment_meta_data['sizes'] );
			}

			if ( ! empty( $attachment_meta_data['original_image'] ) ) {
				$files[ $attachment_meta_data['original_image'] ] = true;
			}
		}

		$backup_sizes = get_post_meta( $this->attachment_id, '_wp_attachment_backup_sizes', true );

		if ( is_array( $backup_sizes ) ) {
			$files = $this->add_size_files( $files, $backup_sizes );
		}

		$stem = $this->get_stem( basename( $relative_file_path ) );

		foreach ( array_keys( $files ) as $file ) {
			if ( $this->is_derived_from( $file, $stem ) ) {
				$this->wp_filesystem->delete( $this->get_full_file_upload_path( $subdir . $file ), false, 'f' );
			}
		}
	}

	private function get_stem( $file_name ) {
		$stem = pathinfo( $file_name, PATHINFO_FILENAME );

		do {
			$previous = $stem;
			$stem     = (string) preg_replace( '/-(?:scaled|rotated|e\d{13})$/', '', $stem );
		} while ( $stem !== $previous && '' !== $stem );

		return '' === $stem ? $previous : $stem;
	}

	private function is_derived_from( $file_name, $stem ) {
		$pattern = '/^' . preg_quote( $stem, '/' ) . '(?:-scaled|-rotated|-e\d{13}|-pdf(?:-\d+)?)*(?:-\d+x\d+)?\.[a-z0-9]+$/i';

		return 1 === preg_match( $pattern, $file_name );
	}

	private function add_size_files( array $files, array $sizes ) {
		foreach ( $sizes as $size ) {
			if ( is_array( $size ) && ! empty( $size['file'] ) ) {
				$files[ $size['file'] ] = true;
			}
		}

		return $files;
	}

	private function get_full_file_upload_path( $relative_file_path ) {
		$upload_dir         = wp_upload_dir();
		$relative_file_path = trim( $relative_file_path, ' /' );
		$file_path          = $upload_dir['basedir'] . '/' . $relative_file_path;

		return $file_path;
	}

	private function file_is_shared( $relative_file_path, $attachment_id ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE post_id <> %d AND meta_key='_wp_attached_file' AND meta_value=%s
				LIMIT 1",
				$attachment_id,
				$relative_file_path
			)
		);
	}

}
