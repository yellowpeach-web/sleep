<?php

use WPML\EditHistory\InternalWrite;
use WPML\FP\Obj;
use WPML\TM\XLIFF\ExportLeftOut;
use WPML\TM\XLIFF\PortableFileName;
use function WPML\PHP\Logger\error as logError;

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once WPML_TM_PATH . '/inc/wpml_zip.php';

class WPML_TM_Xliff_Frontend extends WPML_TM_Xliff_Shared {

	private $success;

	private $error_files = array();

	private $attachments = array();

	private $temp_files = array();

	private $sitepress;

	private $export_archive_name;

	private $export_left_out;

	private $late_init_priority = 9999;

	private $simplexml_on;

	public function __construct( WPML_Translation_Job_Factory $job_factory, SitePress $sitepress, $simplexml_on ) {
		parent::__construct( $job_factory );
		$this->sitepress    = $sitepress;
		$this->simplexml_on = $simplexml_on;
	}

	protected function keeps_source_of_untranslated_units() {
		return false;
	}

	public function get_available_xliff_versions() {

		return array(
			'10' => '1.0',
			'11' => '1.1',
			'12' => '1.2',
		);
	}

	public function get_init_priority() {
		return isset( $_POST['xliff_upload'] ) ||
			   ( isset( $_GET['wpml_xliff_action'] ) && $_GET['wpml_xliff_action'] === 'download' ) ||
			   isset( $_POST['wpml_xliff_export_all_filtered'] ) ?
			$this->get_late_init_priority() : 10;
	}

	public function get_late_init_priority() {
		return $this->late_init_priority;
	}

	public function init() {
		$this->attachments = array();
		$this->error       = null;
		if ( $this->sitepress->get_wp_api()->is_admin() ) {
			add_action( 'admin_head', array( $this, 'js_scripts' ) );
			\WPML\Request\Adapter\Ajax::register(
				'set_xliff_options',
				\WPML\Request\Policy\Policy::capability(
					'manage_translations',
					\WPML\Request\Policy\Authenticity::actionNonce( 'icl_xliff_options_form_nonce', 'security' )
				),
				array(
					$this,
					'ajax_set_xliff_options',
				),
				10,
				0
			);
			if ( ! $this->sitepress->get_setting( 'xliff_newlines' ) ) {
				$this->sitepress->set_setting( 'xliff_newlines', WPML_XLIFF_TM_NEWLINES_ORIGINAL, true );
			}
			if ( ! $this->sitepress->get_setting( 'tm_xliff_version' ) ) {
				$this->sitepress->set_setting( 'tm_xliff_version', '12', true );
			}

			if ( 1 < count( $this->sitepress->get_languages( false, true ) ) ) {
				add_action(
					'wpml_xliff_select_actions',
					array(
						$this,
						'translation_queue_xliff_select_actions',
					),
					10,
					4
				);
				add_filter(
					'wpml_new_job_notification',
					array(
						$this,
						'new_job_notification',
					),
					10,
					2
				);
				add_filter(
					'wpml_new_job_notification_attachments',
					array(
						$this,
						'new_job_notification_attachments',
					)
				);
			}
			if (
				isset( $_GET['wpml_xliff_action'] ) &&
				$_GET['wpml_xliff_action'] === 'download' &&
				wp_verify_nonce( $_GET['nonce'], 'xliff-export' )
			) {
				$archive = $this->get_xliff_archive(
					\WPML\SuperGlobals\Request::param( 'xliff_version' ),
					explode( ',', \WPML\SuperGlobals\Request::param( 'jobs' ) )
				);
				$this->stream_xliff_archive( $archive );
			}

			\WPML\Request\Adapter\Ajax::register( 'wpml_xliff_upload', \WPML\Request\Policy\Policy::capability( [ 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_xliff_upload', 'nonce' ) ), function () {
				check_ajax_referer( 'wpml_xliff_upload', 'nonce' );
				if ( ! current_user_can( 'translate' ) && ! current_user_can( 'manage_translations' ) ) {
					wp_send_json_error( __( 'You are not allowed to import XLIFF translations.', 'sitepress' ) );
				}
				$upload   = Obj::propOr( [], 'import', $_FILES );
				$imported = \WPML\TM\Jobs\JobLog::runInGroup(
					\WPML\TM\Jobs\JobLog::GROUP_ID_LOCAL_SAVES,
					'XLIFF upload',
					function () use ( $upload ) {
						return $this->import_xliff( $upload );
					}
				);
				if ( $imported ) {
					wp_send_json_success(
						[
							'jobs'   => $this->success,
							'errors' => $this->error_files,
						]
					);
				} else {
					wp_send_json_error( $this->error );
                }
			} );
		}

		return true;
	}

	public function ajax_set_xliff_options() {
		check_ajax_referer( 'icl_xliff_options_form_nonce', 'security' );
		$newlines = isset( $_POST['icl_xliff_newlines'] ) ? (int) $_POST['icl_xliff_newlines'] : 0;
		$this->sitepress->set_setting( 'xliff_newlines', $newlines, true );
		$version = isset( $_POST['icl_xliff_version'] ) ? (int) $_POST['icl_xliff_version'] : 0;
		$this->sitepress->set_setting( 'tm_xliff_version', $version, true );

		wp_send_json_success(
			array(
				'message'        => 'OK',
				'newlines_saved' => $newlines,
				'version_saved'  => $version,
			)
		);
	}

	public function new_job_notification( $mail, $job_id ) {
		$notification = wpml_get_tm_sub_setting( 'notification', array() );

		if ( isset( $notification['include_xliff'] ) && $notification['include_xliff'] ) {
			$xliff_version = $this->get_user_xliff_version();
			$xliff_version = $xliff_version ?: WPML_XLIFF_DEFAULT_VERSION;
			$xliff_file    = $this->get_xliff_file( $job_id, $xliff_version );
			$temp_dir      = get_temp_dir();
			$file_name     = $temp_dir . PortableFileName::forJob( get_bloginfo( 'name' ), $job_id );
			if ( $this->write_temp_file( $file_name, $xliff_file ) ) {
				$mail['attachment']           = $file_name;
				$this->attachments[ $job_id ] = $file_name;

				/* translators: Added at the end of the line of an email that tells the reader a file came with it. It follows the sentence before it, which is why it starts with a space and a dash. */
				$mail['body'] .= __( ' - A xliff file is attached.', 'sitepress' );
			}
		}

		return $mail;
	}

	private function get_zip_name_from_jobs( $job_ids ) {
		return PortableFileName::forArchive( get_bloginfo( 'name' ), $job_ids );
	}

	public function new_job_notification_attachments( $attachments ) {
		$found   = false;
		$archive = new wpml_zip();

		foreach ( $attachments as $index => $attachment ) {
			if ( in_array( $attachment, $this->attachments, true ) ) {
				$xliff_file = $this->read_temp_file( $attachment );
				unset( $attachments[ $index ] );
				$this->remove_temp_file( $attachment );

				if ( null !== $xliff_file ) {
					$archive->addFile( $xliff_file, basename( $attachment ) );
					$found = true;
				}
			}
		}

		if ( $found ) {
			$archive_data = $archive->getZipData();
			$temp_dir     = get_temp_dir();
			$file_name    = $temp_dir . $this->get_zip_name_from_jobs( array_keys( $this->attachments ) );
			if ( $this->write_temp_file( $file_name, $archive_data ) ) {
				$attachments[] = $file_name;
			}
		}

		return $attachments;
	}

	public function remove_temp_attachments() {
		foreach ( $this->temp_files as $file_name ) {
			$this->remove_temp_file( $file_name );
		}
	}

	private function write_temp_file( $file_name, $contents ) {
		$contents = (string) $contents;
		$written  = '' === $contents ? false : $this->write_temp_file_bytes( $file_name, $contents );

		if ( strlen( $contents ) === $written ) {
			$this->temp_files[ $file_name ] = $file_name;
			if ( false === has_action( 'shutdown', array( $this, 'remove_temp_attachments' ) ) ) {
				add_action( 'shutdown', array( $this, 'remove_temp_attachments' ) );
			}

			return true;
		}

		$this->remove_temp_file( $file_name );
		logError(
			sprintf(
				'XLIFF notification attachment not written, the email goes without it: %s (%s of %d bytes written) in %s:%d',
				$file_name,
				false === $written ? 'none' : (int) $written,
				strlen( $contents ),
				basename( __FILE__ ),
				__LINE__
			)
		);

		return false;
	}

	protected function write_temp_file_bytes( $file_name, $contents ) {
		$fh = @fopen( $file_name, 'w' );
		if ( ! $fh ) {
			return false;
		}
		$written = @fwrite( $fh, $contents );
		fclose( $fh );

		return $written;
	}

	private function read_temp_file( $file_name ) {
		$contents = is_readable( $file_name ) ? file_get_contents( $file_name ) : false;
		if ( false === $contents || '' === $contents ) {
			logError( sprintf( 'XLIFF notification attachment skipped, the email goes without it: %s is empty or cannot be read in %s:%d', $file_name, basename( __FILE__ ), __LINE__ ) );

			return null;
		}

		return $contents;
	}

	private function remove_temp_file( $file_name ) {
		unset( $this->temp_files[ $file_name ] );
		if ( file_exists( $file_name ) || is_link( $file_name ) ) {
			wp_delete_file( $file_name );
		}
	}

	private function get_xliff_file( $job_id, $xliff_version = WPML_XLIFF_DEFAULT_VERSION ) {
		return wpml_tm_xliff_factory()
			->create_writer( $xliff_version )
			->withOutdatedFieldSegments()
			->generate_job_xliff( $job_id );
	}

	public function get_xliff_archive( $xliff_version, $job_ids = array() ) {
		global $current_user;

		$job_ids = array_values( array_filter( (array) $job_ids, 'strlen' ) );

		if ( empty( $job_ids ) && isset( $_GET['xliff_export_data'] ) ) {
			$data = json_decode( base64_decode( $_GET['xliff_export_data'] ) );
			$job_ids = isset( $data->job ) ? array_keys( (array) $data->job ) : array();
		}

		if ( empty( $job_ids ) ) {
			return null;
		}

		$is_translation_manager = current_user_can( 'manage_translations' );

		$archive               = new wpml_zip();
		$exported              = 0;
		$this->export_left_out = new ExportLeftOut();
		foreach ( $job_ids as $job_id ) {
			$job = $this->job_factory->get_translation_job( (int) $job_id, false, 0, true );
			if ( ! $job ) {
				continue;
			}

			if ( ! $is_translation_manager && ! $job->user_can_translate( $current_user ) ) {
				continue;
			}

			$left_out_reason = $this->export_left_out->reasonFor( (int) $job_id );
			if ( null !== $left_out_reason ) {
				$this->export_left_out->leaveOut( (int) $job_id, $left_out_reason );
				continue;
			}

			$xliff_file = $this->get_xliff_file( (int) $job_id, $xliff_version );

			if ( 0 === (int) $job->get_translator_id() ) {
				$job->assign_to( (int) $current_user->ID );
			}

			$archive->addFile( $xliff_file, PortableFileName::forJob( get_bloginfo( 'name' ), $job_id ) );
			++$exported;
		}

		$this->export_left_out->writeToJobLog();
		if ( 0 === $exported && ! $this->export_left_out->isEmpty() ) {
			return null;
		}

		$this->export_archive_name = $this->get_zip_name_from_jobs( $job_ids );
		$archive->finalize();

		return $archive;
	}

	private function stream_xliff_archive( $archive ) {
		$left_out = $this->get_export_left_out();
		if ( $left_out && ! $left_out->isEmpty() && ! headers_sent() ) {
			header( ExportLeftOut::HEADER . ': ' . $left_out->headerValue() );
		}
		if ( is_a( $archive, 'wpml_zip' ) ) {
			if ( defined( 'WPML_SAVE_XLIFF_PATH' ) && trim( WPML_SAVE_XLIFF_PATH ) ) {
				$this->save_zip_file( WPML_SAVE_XLIFF_PATH, $archive );
			}
			$archive->sendZip( $this->export_archive_name );
		}
		exit;
	}

	public function get_export_left_out() {
		return $this->export_left_out;
	}

	private function save_zip_file( $path, $archive ) {
		$path = trailingslashit( $path );
		if ( ! is_dir( $path ) ) {
			$result = mkdir( $path );
			if ( ! $result ) {
				return;
			}
		}
		$archive->setZipFile( $path . $this->export_archive_name );
	}

	public function stop_redirect() {
		return false;
	}

	private function import_xliff( $file ) {
		global $current_user;

		add_filter( 'wp_redirect', array( $this, 'stop_redirect' ) );

		$this->success     = array();
		$this->error_files = array();
		$contents          = array();

		if ( 0 === (int) $file['size'] ) {
			$this->error = new WP_Error( 'empty_file', __( 'You are trying to import an empty file.', 'sitepress' ) );

			return false;
		} elseif ( isset( $file['tmp_name'] ) && $file['tmp_name'] ) {
			$fh   = fopen( $file['tmp_name'], 'r' );
			$data = false;
			if ( $fh ) {
				$data = fread( $fh, 4 );
				fclose( $fh );
			}
			if ( $data && $data[0] == 'P' && $data[1] == 'K' && $data[2] == chr( 03 ) && $data[3] == chr( 04 ) ) {
				if ( class_exists( 'ZipArchive' ) ) {
					$z     = new ZipArchive();
					$zopen = $z->open( $file['tmp_name'], 4 );
					if ( true !== $zopen ) {
						/* translators: Error message shown when the uploaded file is not of a kind WPML can read. */
						$this->error = new WP_Error( 'incompatible_archive', __( 'Incompatible Archive.', 'sitepress' ) );

						return false;
					}
					$empty_files = array();
					for ( $i = 0; $i < $z->numFiles; $i ++ ) {
						if ( ! $info = $z->statIndex( $i ) ) {
							$this->error = new WP_Error( 'stat_failed', __( 'Could not retrieve file from archive.', 'sitepress' ) );

							return false;
						}
						if ( $this->is_directory( $info['name'] ) ) {
							continue;
						}
						$content = $z->getFromIndex( $i );
						if ( false === (bool) $content ) {
							$empty_files[] = $info['name'];
						}
						$contents[ $info['name'] ] = $content;
					}
					if ( $empty_files ) {
						$this->error = new WP_Error( 'extract_failed', __( 'The archive contains one or more empty files.', 'sitepress' ), $empty_files );

						return false;
					}
				} else {
					require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
					$archive = new PclZip( $file['tmp_name'] );
					$archive_files = $archive->extract( PCLZIP_OPT_EXTRACT_AS_STRING );
					if ( false == $archive_files ) {
						$this->error = new WP_Error( 'incompatible_archive', __( 'You are trying to import an incompatible Archive.', 'sitepress' ), $archive->errorInfo( true ) );

						return false;
					}
					if ( 0 === count( $archive_files ) ) {
						$this->error = new WP_Error( 'empty_archive', __( 'You are trying to import an empty archive.', 'sitepress' ) );

						return false;
					}
					$empty_files = array();
					foreach ( $archive_files as $content ) {
						if ( $this->is_directory( $content['filename'] ) ) {
							continue;
						}
						if ( false === (bool) $content['content'] ) {
							$empty_files[] = $content['filename'];
						}
						$contents[ $content['filename'] ] = $content['content'];
					}
					if ( $empty_files ) {
						$this->error = new WP_Error( 'extract_failed', __( 'The archive contains one or more empty files.', 'sitepress' ), $empty_files );

						return false;
					}
				}
			} else {
				$fh   = fopen( $file['tmp_name'], 'r' );
				if ( $fh ) {
					$data = fread( $fh, $file['size'] );
					fclose( $fh );
					$contents[ $file['name'] ] = $data;
				}
			}

			foreach ( $contents as $name => $content ) {
				if ( ! $this->validate_file_name( (string) $name ) ) {
					continue;
				}

				$validated = $this->validate_file( $name, $content, $current_user );
				if ( null !== $this->error ) {
					$this->sitepress->get_wp_api()->error_log( sprintf( 'WPML XLIFF import: failed to import "%s" — %s', $name, $this->error->get_error_message() ) );

					/* translators: %s is the file that doesn't belong to this system */
					$line = sprintf( __( "The uploaded xliff file doesn't belong to this system: %s", 'sitepress' ), $name );
					$detail              = $this->import_error_detail( $this->error );
					$this->error_files[] = '' === $detail ? $line : $line . ' ' . esc_html( $detail );
					$this->error         = null;
					continue;
				}
				list( $job, $job_data ) = $validated;
				( new \WPML\TM\Jobs\TranslationRecord() )->closeOpenOfRecord( \WPML\TM\Jobs\TranslationRecord::rowToOpen( $job->job_id ), $job->job_id, false, true );
				kses_remove_filters();
				InternalWrite::run( InternalWrite::XLIFF_IMPORT, fn() => wpml_tm_save_data( $job_data ) );
				kses_init();
				$this->success[] = $job->job_id;
			}

			if ( ! empty( $this->error_files ) ) {
				$this->error = new WP_Error( 'xliff_name_not_belong', __( "The uploaded xliff files don't belong to this system.", 'sitepress' ), $this->error_files );
			}

			return ! empty( $this->success ) || empty( $this->error_files );
		}

		return false;
	}

	public function translation_queue_xliff_select_actions( $actions, $action_name, $translation_jobs, $action ) {
		if ( $this->has_translation_jobs( $translation_jobs ) && sizeof( $actions ) > 0 ) :
			$currentAction = $action ?: false;
			?>
			<div class="alignleft actions">
				<select name="<?php echo esc_html( $action_name ); ?>">
					<option
							value="-1" <?php echo $currentAction == false ? "selected='selected'" : ''; ?>><?php /* translators: First entry of the dropdown that acts on the ticked rows of a table, shown while nothing is picked. Noun phrase, plural. */ esc_html_e( 'Bulk Actions', 'sitepress' ); ?></option>
					<?php foreach ( $actions as $key => $action ) : ?>
						<option <?php disabled( ! $this->simplexml_on ); ?>
								value="<?php echo $key; ?>" <?php echo $currentAction == $key && $this->simplexml_on ? "selected='selected'" : ''; ?>><?php echo $action; ?></option>
					<?php endforeach; ?>
				</select>
				<input
						id="js-wpml-do-action"
						type="submit" value="<?php /* translators: Button label next to a dropdown: carry out the chosen action. Verb, imperative. */ esc_attr_e( 'Apply', 'sitepress' ); ?>"
						name="do<?php echo esc_html( $action_name ); ?>"
						class="button-secondary action"/>
			</div>
			<?php
		endif;
	}

	private function has_translation_jobs( $translation_jobs ) {
		return $translation_jobs && array_key_exists( 'jobs', $translation_jobs ) && $translation_jobs['jobs'];
	}

	public function admin_notices_error() {
		if ( is_wp_error( $this->error ) ) {
			?>
			<div class="message error">
				<p><?php echo $this->error->get_error_message(); ?></p>
				<?php
				if ( $this->error->get_error_data() ) {
					?>
					<ol>
						<li><?php echo implode( '</li><li>', $this->error->get_error_data() ); ?></li>
					</ol>
					<?php
				}
				?>
			</div>
			<?php
		}
	}

	public function admin_notices_success() {
		?>
		<div class="message updated"><p>
			<ul>
				<?php
				foreach ( $this->success as $message ) {
					echo '<li>' . $message . '</li>';
				}
				?>
			</ul>
			</p></div>
		<?php
	}

	public function js_scripts() {
		?>
		<script type="text/javascript">
			var wpml_xliff_ajax_nonce = '<?php echo wp_create_nonce( 'icl_xliff_options_form_nonce' ); ?>';
			var wpml_xliff_upload_nonce = '<?php echo esc_js( wp_create_nonce( 'wpml_xliff_upload' ) ); ?>';
		</script>
		<?php
	}

	private function get_user_xliff_version() {

		return $this->sitepress->get_setting( 'tm_xliff_version', false );
	}

	private function is_directory( $path ) {
		return '/' === substr( $path, -1 );
	}

	private function import_error_detail( $error ) {
		if ( 'xliff_does_not_match' === $error->get_error_code() ) {
			$data = $error->get_error_data();

			return is_array( $data ) && isset( $data['sentence'] ) ? (string) $data['sentence'] : '';
		}

		return (string) $error->get_error_message();
	}
}
