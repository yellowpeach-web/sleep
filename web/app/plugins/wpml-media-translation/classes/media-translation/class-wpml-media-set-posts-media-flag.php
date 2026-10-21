<?php

class WPML_Media_Set_Posts_Media_Flag implements IWPML_Action {
	const HAS_MEDIA_POST_FLAG = '_wpml_media_has_media';

	const BATCH_SIZE = 100;

	const SCAN_TOTAL_SETTING     = 'posts_media_flag_scan_total';
	const SCAN_PROCESSED_SETTING = 'posts_media_flag_scan_processed';

	const SCAN_LAST_ID_SETTING = 'posts_media_flag_scan_last_id';

	private $wpdb;
	private $wpml_notices;
	private $post_media_usage;
	private $post_with_media_files_factory;

	public function __construct(
		wpdb $wpdb,
		WPML_Notices $wpml_notices,
		WPML_Media_Post_Media_Usage $post_media_usage,
		WPML_Media_Post_With_Media_Files_Factory $post_with_media_files_factory
	) {
		$this->wpdb                          = $wpdb;
		$this->wpml_notices                  = $wpml_notices;
		$this->post_media_usage              = $post_media_usage;
		$this->post_with_media_files_factory = $post_with_media_files_factory;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( WPML_Media_Posts_Media_Flag_Notice::PREPARE_ACTION, \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( WPML_Media_Posts_Media_Flag_Notice::PREPARE_ACTION, 'nonce' ) ), [ $this, 'clear_flags_action' ] );
		\WPML\Request\Adapter\Ajax::register( WPML_Media_Posts_Media_Flag_Notice::PROCESS_ACTION, \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( WPML_Media_Posts_Media_Flag_Notice::PROCESS_ACTION, 'nonce' ) ), [ $this, 'process_batch_action' ] );
		add_action( 'save_post', array( $this, 'update_post_flag' ) );
	}

	public function clear_flags_action() {
		if ( $this->verify_nonce( WPML_Media_Posts_Media_Flag_Notice::PREPARE_ACTION ) ) {
			$this->clear_flags();
			/* translators: Status shown on the Media Translation page while the one-off setup is running. */
			wp_send_json_success( array( 'status' => __( 'Running setup...', 'wpml-media' ) ) );

		} else {
			wp_send_json_error( array( 'status' => 'Invalid nonce' ) );
		}
	}

	public function clear_flags() {
		if ( ! WPML_Media::has_setup_started() ) {
			$this->wpdb->delete( $this->wpdb->postmeta, array( 'meta_key' => self::HAS_MEDIA_POST_FLAG ), array( '%s' ) );
			$this->reset_progress();
		}
	}

	public function process_batch_action() {
		if ( $this->verify_nonce( WPML_Media_Posts_Media_Flag_Notice::PROCESS_ACTION ) ) {

			$offset = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
			list( $status, $offset, $continue ) = $this->process_batch( $offset );

			wp_send_json_success( array(
				'status'   => $status,
				'offset'   => $offset,
				'continue' => $continue
			) );

		} else {
			wp_send_json_error( array( 'status' => 'Invalid nonce' ) );
		}
	}

	public function process_batch( $offset ) {
		$this->mark_started();

		$last_id  = max( (int) $offset, (int) WPML_Media::get_setting( self::SCAN_LAST_ID_SETTING, 0 ) );
		$continue = false;
		/* translators: Status shown on the Media Translation page when the one-off setup has finished. */
		$status   = __( 'Setup complete!', 'wpml-media' );

		if ( ! WPML_Media::has_setup_run() ) {

			$posts = $this->get_unflagged_posts_after( $last_id );

			if ( $continue = ( count( $posts ) > 0 ) ) {
				$progress          = $this->track_progress( count( $posts ) );
				$media_ids_by_post = $this->get_media_ids_by_post( $posts );
				$this->flag_posts( $posts, $media_ids_by_post );
				$this->record_media_usage( $posts, $media_ids_by_post );
				$last_id = (int) end( $posts )->ID;
				WPML_Media::update_setting( self::SCAN_LAST_ID_SETTING, $last_id );
				/* translators: Status shown on the Media Translation page while the one-off setup runs. %d: how far it has got, as a percentage. The doubled percent sign prints one percent sign, so keep it doubled. */
				$status  = sprintf( __( 'Setup in progress: %d%% complete...', 'wpml-media' ), $progress );
			}
		}

		if ( ! $continue ) {
			$this->mark_complete();
		}

		return [ $status, $last_id, $continue ];
	}

	private function get_unflagged_posts_after( $last_id ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_results(
			$wpdb->prepare( "
				SELECT p.ID, p.post_content
				FROM {$wpdb->posts} p
				JOIN {$wpdb->prefix}icl_translations t
					ON t.element_id = p.ID AND t.element_type LIKE %s
				LEFT JOIN {$wpdb->prefix}postmeta m ON p.ID = m.post_id AND m.meta_key=%s
				WHERE p.post_type NOT IN ( 'auto-draft', 'attachment', 'revision' )
					AND t.source_language_code IS NULL AND m.meta_id IS NULL
					AND p.ID > %d
				ORDER BY ID ASC
				LIMIT %d
			", 'post_%', self::HAS_MEDIA_POST_FLAG, $last_id, self::BATCH_SIZE )
		);
	}

	private function track_progress( $processed_in_batch ) {
		$total = (int) WPML_Media::get_setting( self::SCAN_TOTAL_SETTING );
		if ( ! $total ) {
			$wpdb  = $this->wpdb;
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "
					SELECT COUNT(*)
					FROM {$wpdb->posts} p
					JOIN {$wpdb->prefix}icl_translations t
						ON t.element_id = p.ID AND t.element_type LIKE %s
					LEFT JOIN {$wpdb->prefix}postmeta m ON p.ID = m.post_id AND m.meta_key=%s
					WHERE p.post_type NOT IN ( 'auto-draft', 'attachment', 'revision' )
						AND t.source_language_code IS NULL AND m.meta_id IS NULL
				", 'post_%', self::HAS_MEDIA_POST_FLAG )
			);
			WPML_Media::update_setting( self::SCAN_TOTAL_SETTING, $total );
		}

		$processed = (int) WPML_Media::get_setting( self::SCAN_PROCESSED_SETTING ) + $processed_in_batch;
		WPML_Media::update_setting( self::SCAN_PROCESSED_SETTING, $processed );

		return $total > 0 ? (int) round( 100 * min( $processed, $total ) / $total ) : 100;
	}

	private function reset_progress() {
		WPML_Media::update_setting( self::SCAN_TOTAL_SETTING, 0 );
		WPML_Media::update_setting( self::SCAN_PROCESSED_SETTING, 0 );
		WPML_Media::update_setting( self::SCAN_LAST_ID_SETTING, 0 );
	}

	private function verify_nonce( $action ) {
		return isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], $action );
	}

	private function get_media_ids_by_post( array $posts ) {
		$post_ids           = array_map( function ( $post ) {
			return (int) $post->ID;
		}, $posts );
		$attached_media_ids = $this->get_attached_media_ids_by_batch( $post_ids );

		$media_ids_by_post = [];
		foreach ( $post_ids as $post_id ) {
			$post_id                = (int) $post_id;
			$post_with_media_files  = $this->post_with_media_files_factory->create(
				$post_id,
				$attached_media_ids[ $post_id ]
			);
			$media_ids_by_post[ $post_id ] = $post_with_media_files->get_media_ids();
		}

		return $media_ids_by_post;
	}

	private function get_attached_media_ids_by_batch( array $post_ids ) {
		$attached_media_ids = array_fill_keys( $post_ids, [] );

		if ( ! $post_ids ) {
			return $attached_media_ids;
		}

		$attachments = get_posts( array(
			'post_parent__in' => $post_ids,
			'post_status'     => 'inherit',
			'post_type'       => 'attachment',
			'posts_per_page'  => -1,
		) );

		foreach ( $attachments as $attachment ) {
			$attached_media_ids[ (int) $attachment->post_parent ][] = (int) $attachment->ID;
		}

		return $attached_media_ids;
	}

	private function flag_posts( $posts, $media_ids_by_post = null ) {
		foreach ( $posts as $post ) {
			$media_ids = null !== $media_ids_by_post ? $media_ids_by_post[ (int) $post->ID ] : null;
			$this->update_post_flag( $post->ID, $media_ids );
		}
	}

	public function update_post_flag( $post_id, $media_ids = null ) {

		if ( null === $media_ids ) {
			$media_ids = $this->post_with_media_files_factory->create( $post_id )->get_media_ids();
		}

		if ( $media_ids ) {
			update_post_meta( $post_id, self::HAS_MEDIA_POST_FLAG, 1 );
		} else {
			delete_post_meta( $post_id, self::HAS_MEDIA_POST_FLAG );
		}
	}

	private function record_media_usage( $posts, $media_ids_by_post = null ) {
		foreach ( $posts as $post ) {
			$media_ids = null !== $media_ids_by_post ? $media_ids_by_post[ (int) $post->ID ] : null;
			$this->post_media_usage->update_media_usage( $post->ID, null, $media_ids );
		}
	}

	private function mark_complete() {
		WPML_Media::set_setup_run();
		$this->reset_progress();
		$this->wpml_notices->remove_notice(
			WPML_Media_Posts_Media_Flag_Notice::NOTICE_GROUP,
			WPML_Media_Posts_Media_Flag_Notice::NOTICE_ID
		);
	}

	private function mark_started() {
		WPML_Media::set_setup_started();
	}


}
