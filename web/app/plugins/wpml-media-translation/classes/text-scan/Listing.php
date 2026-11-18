<?php

namespace WPML\Media\TextScan;

use WPML\TM\Jobs\EditAccess\AutomaticTranslationPermission;

class Listing {

	const ARG = 'scan';

	const NOT_SCANNED = 'not_scanned';

	const FILTER_VALUES = [
		VerdictStore::STATE_TRANSLATE,
		VerdictStore::STATE_SKIP,
		VerdictStore::STATE_UNSURE,
		self::NOT_SCANNED,
	];

	const UNCHECKED_REASONS = [ 'unreadable', 'no_answer', 'error' ];

	const STATUS_ROUTE = '/wpml/v1/media/text-scan/status';

	const REST_BASE = 'wpml/v1/media/text-scan/';

	public static function queryArg( array $get ) {
		if ( ! isset( $get[ self::ARG ] ) || ! Feature::isEnabled() ) {
			return '';
		}

		$value = $get[ self::ARG ];

		return is_string( $value ) && in_array( $value, self::FILTER_VALUES, true ) ? $value : '';
	}

	public static function joinClause( \wpdb $wpdb, array $queryArgs ) {
		if ( '' === self::queryArg( $queryArgs ) ) {
			return '';
		}

		return $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} scan_state
			       ON scan_state.post_id = {$wpdb->posts}.ID
			      AND scan_state.meta_key = %s ",
			VerdictStore::STATE_META
		);
	}

	public static function whereClause( \wpdb $wpdb, array $queryArgs ) {
		$value = self::queryArg( $queryArgs );
		if ( '' === $value ) {
			return '';
		}

		if ( self::NOT_SCANNED === $value ) {
			return $wpdb->prepare(
				" AND ( scan_state.meta_value IS NULL OR scan_state.meta_value = %s )
				  AND {$wpdb->posts}.post_mime_type LIKE %s ",
				VerdictStore::STATE_QUEUED,
				'image/%'
			);
		}

		return $wpdb->prepare( ' AND scan_state.meta_value = %s ', $value );
	}

	public static function rowData( $isImage, $state, $reason, $changed = false ) {
		if ( ! $isImage ) {
			return [
				'is_image' => false,
				/* translators: Screen-reader text of the Scan result cell of a file that is not an image, on the Media Translation page. */
				'label'    => __( 'Not an image, not scanned', 'wpml-media' ),
			];
		}

		$reason = is_string( $reason ) ? $reason : '';
		$row    = [
			'is_image' => true,
			'state'    => self::NOT_SCANNED,
			'reason'   => $reason,
			'variant'  => 'none',
			'label'    => self::stateLabel( self::NOT_SCANNED ),
			'tooltip'  => $changed
				/* translators: Tooltip of the scan result "Not scanned" on the Media Translation page, for an image whose file changed after it was scanned. */
				? __( 'This image changed after the last scan. It is checked again in the next scan.', 'wpml-media' )
				/* translators: Tooltip of the scan result "Not scanned" on the Media Translation page. */
				: __( 'This image has not been scanned for text yet.', 'wpml-media' ),
		];

		if ( VerdictStore::STATE_TRANSLATE === $state ) {
			$row['state']   = VerdictStore::STATE_TRANSLATE;
			$row['variant'] = 'go';
			$row['label']   = self::stateLabel( VerdictStore::STATE_TRANSLATE );
			/* translators: Tooltip of the scan result "Worth translating" on the Media Translation page. */
			$row['tooltip'] = __( 'The text on this image reads as content for visitors, so it is worth translating.', 'wpml-media' );
		} elseif ( VerdictStore::STATE_SKIP === $state ) {
			list( $label, $tooltip ) = self::skipCopy( $reason );
			$row['state']   = VerdictStore::STATE_SKIP;
			$row['variant'] = 'skip';
			$row['label']   = $label;
			$row['tooltip'] = $tooltip;
		} elseif ( VerdictStore::STATE_UNSURE === $state ) {
			$row['state']   = VerdictStore::STATE_UNSURE;
			$row['variant'] = 'unsure';
			if ( in_array( $reason, self::UNCHECKED_REASONS, true ) ) {
				/* translators: Scan result of an image on the Media Translation page: the file could not be read, or the check did not answer. */
				$row['label'] = __( 'Not sure: could not be checked', 'wpml-media' );
				/* translators: Tooltip of the scan result "Not sure: could not be checked" on the Media Translation page. */
				$row['tooltip'] = __( 'WPML could not check this image: the file could not be read, or the check did not answer. It is never translated until you decide.', 'wpml-media' );
			} else {
				$row['label'] = self::stateLabel( VerdictStore::STATE_UNSURE );
				/* translators: Tooltip of the scan result "Not sure" on the Media Translation page. */
				$row['tooltip'] = __( 'WPML is not sure whether this image needs translating. It is never translated until you decide.', 'wpml-media' );
			}
		}

		return $row;
	}

	public static function noticeState( array $status ) {
		$progress = isset( $status['status'] ) ? $status['status'] : ScanProgress::IDLE;
		$modeOff  = ! isset( $status['mode'] ) || Mode::OFF === $status['mode'];

		if ( ScanProgress::RUNNING === $progress ) {
			return empty( $status['retry_at'] ) ? 'running' : 'interrupted';
		}

		if ( ScanProgress::PAUSED === $progress ) {
			return $modeOff ? 'paused-off' : 'running';
		}

		if ( ScanProgress::DONE === $progress ) {
			return 'finished';
		}

		return $modeOff ? 'off' : 'idle';
	}

	public static function addToModel( array $model, array $queryArgs ) {
		$services = self::services();
		if ( null === $services ) {
			return $model;
		}

		list( $store, $outdated ) = $services;

		/* translators: Text and tooltip of the marker on a translated image on the Media Translation page, whose original image was changed. */
		$changed = __( 'The original image changed after this translation was made. Update the translated image.', 'wpml-media' );

		$attachments = isset( $model['attachments'] ) && is_array( $model['attachments'] ) ? $model['attachments'] : [];
		foreach ( $attachments as $index => $attachment ) {
			$model['attachments'][ $index ]['text_scan'] = self::rowModel( $store, $outdated, $attachment, $changed );
		}

		$status = self::status();
		$canAct = self::canAct();

		$model['text_scan'] = [
			'strings' => [
				/* translators: Column heading on the Media Translation page: whether the image has text worth translating. */
				'column'       => __( 'Scan result', 'wpml-media' ),
				/* translators: Accessible name of the dropdown on the Media Translation page that filters the list by scan result. */
				'filter_label' => __( 'Filter by scan result', 'wpml-media' ),
			],
			'filter'  => [
				'selected' => self::queryArg( $queryArgs ),
				'options'  => self::filterOptions(),
			],
			'notice'  => null === $status ? null : self::noticeModel( $status, $canAct ),
			'panel'   => null !== $status && $canAct ? SettingControl::panel( Mode::get() ) : null,
		];

		return $model;
	}

	public static function scriptData() {
		$modeLabels = [];
		foreach ( [ Mode::ALL, Mode::LIKELY, Mode::OFF ] as $mode ) {
			$modeLabels[ $mode ] = SettingControl::label( $mode );
		}

		return [
			'root'       => rest_url( self::REST_BASE ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'canAct'     => self::canAct(),
			'modeLabels' => $modeLabels,
			'strings'    => [
				'notAllowed' => self::noPermissionText(),
				'modeOff'    => self::offTitle(),
				/* translators: Message on the Media Translation page when "Start scan" was pressed while a scan of the images was already running. */
				'running'    => __( 'A scan is already running.', 'wpml-media' ),
				/* translators: Message on the Media Translation page when starting the image scan, or trying it again, failed. */
				'failed'     => __( 'The request could not be completed. Check your connection and try again.', 'wpml-media' ),
			],
		];
	}

	public static function canAct() {
		$capable = false;
		foreach ( RestRoutes::CAPABILITIES as $capability ) {
			if ( current_user_can( $capability ) ) {
				$capable = true;
				break;
			}
		}

		return $capable && AutomaticTranslationPermission::isAllowedForCurrentUser();
	}

	private static function noticeModel( array $status, $canAct ) {
		$counts = isset( $status['counts'] ) && is_array( $status['counts'] ) ? $status['counts'] : [];
		$count  = function ( array $from, $key ) {
			return isset( $from[ $key ] ) ? max( 0, (int) $from[ $key ] ) : 0;
		};

		$notScanned = $count( $counts, self::NOT_SCANNED );
		$total      = $count( $status, 'total' );
		$processed  = min( $count( $status, 'processed' ), $total );
		$held       = $count( $status, 'held' );
		$mode       = isset( $status['mode'] ) && Mode::isValid( $status['mode'] ) ? $status['mode'] : Mode::OFF;
		$checked    = $count( $counts, VerdictStore::STATE_TRANSLATE ) + $count( $counts, VerdictStore::STATE_SKIP ) + $count( $counts, VerdictStore::STATE_UNSURE );

		$processedHtml = '<span data-ts="processed">' . esc_html( number_format_i18n( $processed ) ) . '</span>';
		$totalText     = number_format_i18n( $total );
		$totalHtml     = '<span data-ts="total">' . esc_html( $totalText ) . '</span>';

		/* translators: Spoken value of the progress bar of the image scan on the Media Translation page. %1$s: the number of images checked so far, %2$s: the number of images in the scan. */
		$progressForms = _n_noop( '%1$s of %2$s image checked', '%1$s of %2$s images checked', 'wpml-media' );
		/* translators: Spoken value of the progress bar of a paused image scan on the Media Translation page. %1$s: the number of images checked so far, %2$s: the number of images in the scan. */
		$pausedForms = _n_noop( '%1$s of %2$s image checked, paused', '%1$s of %2$s images checked, paused', 'wpml-media' );
		/* translators: Notice on the Media Translation page. %s: the number of images waiting for a check WPML does not have yet. */
		$heldForms = _n_noop( '%s image needs the on-site check, which is not available yet.', '%s images need the on-site check, which is not available yet.', 'wpml-media' );

		return [
			'state'          => self::noticeState( $status ),
			'status_json'    => (string) wp_json_encode( $status ),
			'can_act'        => (bool) $canAct,
			'mode_on'        => Mode::OFF !== $mode,
			'not_scanned'    => $notScanned,
			'processed'      => $processed,
			'total'          => $total,
			'held'           => $held,
			'percent'        => $total > 0 ? (int) floor( 100 * $processed / $total ) : 0,
			'strings'        => [
				/* translators: Title of the notice on the Media Translation page before the first image scan. %s: the number of images. */
				'idle_title'       => $notScanned ? sprintf( _n( '%s image has not been scanned for text yet', '%s images have not been scanned for text yet', $notScanned, 'wpml-media' ), number_format_i18n( $notScanned ) ) : '',
				'idle_text_html'   => sprintf(
					/* translators: Notice on the Media Translation page before the first image scan. %s: the current value of the setting "Find images with text", in bold. */
					esc_html__( 'The scan finds the images whose text is worth translating. Find images with text: %s.', 'wpml-media' ),
					'<strong data-ts="mode-label">' . esc_html( SettingControl::label( $mode ) ) . '</strong>'
				),
				/* translators: Button label on the Media Translation page that opens the setting "Find images with text". Verb, imperative. */
				'change'           => __( 'Change', 'wpml-media' ),
				/* translators: Accessible name of the "Change" button on the Media Translation page that opens the setting "Find images with text". */
				'change_label'     => __( 'Change: change the setting Find images with text', 'wpml-media' ),
				/* translators: Button label on the Media Translation page that starts the scan of the images for text. Verb, imperative. */
				'scan'             => __( 'Scan images', 'wpml-media' ),
				'running_html'     => sprintf(
					/* translators: Title of the notice on the Media Translation page while the images are scanned. %1$s: the number of images checked so far, %2$s: the number of images in the scan. */
					esc_html__( 'Scanning images for text… %1$s of %2$s checked', 'wpml-media' ),
					$processedHtml,
					$totalHtml
				),
				/* translators: Accessible name of the progress bar of the image scan on the Media Translation page. */
				'progress_label'   => __( 'Scan progress', 'wpml-media' ),
				'progress_text'    => sprintf( translate_nooped_plural( $progressForms, $total, 'wpml-media' ), number_format_i18n( $processed ), $totalText ),
				'progress_forms'   => self::countForms( $progressForms, $total ),
				'paused_text'      => sprintf( translate_nooped_plural( $pausedForms, $total, 'wpml-media' ), number_format_i18n( $processed ), $totalText ),
				'paused_forms'     => self::countForms( $pausedForms, $total ),
				/* translators: Notice on the Media Translation page while the images are scanned. */
				'running_text'     => __( 'You can leave this page. The scan keeps running in the background, and runs faster while this page is open.', 'wpml-media' ),
				'paused_html'      => sprintf(
					/* translators: Title of the notice on the Media Translation page when the image scan is paused. %1$s: the number of images checked so far, %2$s: the number of images in the scan. */
					esc_html__( 'The scan is paused at %1$s of %2$s', 'wpml-media' ),
					$processedHtml,
					$totalHtml
				),
				/* translators: Notice on the Media Translation page when the image scan is paused because the service that checks the images could not be reached. */
				'interrupted_text' => __( 'WPML could not reach the image check service. It tries again on its own, at no extra cost.', 'wpml-media' ),
				/* translators: Button label on the Media Translation page that retries the paused image scan at once. */
				'retry'            => __( 'Try again now', 'wpml-media' ),
				/* translators: Notice on the Media Translation page when the image scan is paused because the setting "Find images with text" is off. */
				'paused_off_text'  => __( 'Finding images with text is off, so the scan waits. It continues from here when you turn it back on.', 'wpml-media' ),
				/* translators: Button label on the Media Translation page that opens the setting "Find images with text" to turn it on. */
				'turn_on'          => __( 'Turn it on', 'wpml-media' ),
				/* translators: Accessible name of the "Turn it on" button on the Media Translation page that opens the setting "Find images with text". */
				'turn_on_label'    => __( 'Turn it on: turn on Find images with text', 'wpml-media' ),
				'off_title'        => self::offTitle(),
				/* translators: Notice on the Media Translation page when the setting "Find images with text" is off. */
				'off_text'         => __( 'WPML does not scan images on its own. You can still translate any image from its language cell.', 'wpml-media' ),
				/* translators: Title of the notice on the Media Translation page when the image scan has finished. */
				'finished_title'   => __( 'Scan finished', 'wpml-media' ),
				'finished_text'    => self::finishedLine( $status, $checked ),
				'since_text'       => $notScanned ? sprintf(
					/* translators: Notice on the Media Translation page after an image scan. %s: the number of images not scanned yet. */
					_n( '%s image was added or changed after this scan and is not scanned yet.', '%s images were added or changed after this scan and are not scanned yet.', $notScanned, 'wpml-media' ),
					number_format_i18n( $notScanned )
				) : '',
				/* translators: Button label on the Media Translation page that scans the images added after the last scan. Verb, imperative. */
				'scan_new'         => __( 'Scan new images', 'wpml-media' ),
				'mode_line_html'   => sprintf(
					/* translators: Line of the finished notice on the Media Translation page, before its "Change" button. %s: the current value of the setting "Find images with text", in bold. */
					esc_html__( 'Find images with text: %s.', 'wpml-media' ),
					'<strong data-ts="mode-label">' . esc_html( SettingControl::label( $mode ) ) . '</strong>'
				),
				'held_text'        => $held ? sprintf( translate_nooped_plural( $heldForms, $held, 'wpml-media' ), number_format_i18n( $held ) ) : '',
				'held_forms'       => self::countForms( $heldForms, $held ),
				/* translators: Title of the confirmation on the Media Translation page before the image scan starts. %s: the number of images. */
				'confirm_title'    => sprintf( _n( 'Scan %s image for text?', 'Scan %s images for text?', $notScanned, 'wpml-media' ), number_format_i18n( $notScanned ) ),
				/* translators: Confirmation on the Media Translation page before the image scan starts: what leaves the site. "512 pixels" is the size of the copy that is sent. */
				'confirm_text'     => __( "To check an image, WPML sends a small copy of it (512 pixels) to WPML's translation service. Camera photos, SVG files, animated GIFs and images under 64 pixels are skipped without being sent.", 'wpml-media' ),
				/* translators: Button label that closes the media translation dialog without saving. Verb, imperative, not the noun "a cancellation". */
				'cancel'           => __( 'Cancel', 'wpml-media' ),
				/* translators: Button label on the Media Translation page that confirms and starts the image scan. Verb, imperative. */
				'start'            => __( 'Start scan', 'wpml-media' ),
				'no_permission'    => $canAct ? '' : self::noPermissionText(),
			],
			'summary'        => self::summary( $counts ),
		];
	}

	private static function countForms( array $forms, $count ) {
		return [
			'one'   => translate_nooped_plural( $forms, 1, 'wpml-media' ),
			'other' => translate_nooped_plural( $forms, max( 2, (int) $count ), 'wpml-media' ),
		];
	}

	private static function summary( array $counts ) {
		$chips = [];
		foreach ( [ VerdictStore::STATE_TRANSLATE => 'go', VerdictStore::STATE_UNSURE => 'unsure', VerdictStore::STATE_SKIP => 'skip' ] as $state => $variant ) {
			$n = isset( $counts[ $state ] ) ? max( 0, (int) $counts[ $state ] ) : 0;
			if ( ! $n ) {
				continue;
			}

			$number = number_format_i18n( $n );
			if ( VerdictStore::STATE_TRANSLATE === $state ) {
				/* translators: Count on the Media Translation page after an image scan, linking to those images. %s: the number of images worth translating. */
				$label = sprintf( _n( '%s worth translating', '%s worth translating', $n, 'wpml-media' ), $number );
			} elseif ( VerdictStore::STATE_UNSURE === $state ) {
				/* translators: Count on the Media Translation page after an image scan, linking to those images. %s: the number of images WPML is not sure about. */
				$label = sprintf( _n( '%s not sure', '%s not sure', $n, 'wpml-media' ), $number );
			} else {
				/* translators: Count on the Media Translation page after an image scan, linking to those images. %s: the number of images skipped. */
				$label = sprintf( _n( '%s skipped', '%s skipped', $n, 'wpml-media' ), $number );
			}

			$chips[] = [
				'variant' => $variant,
				'label'   => $label,
				'url'     => esc_url_raw( add_query_arg( [ self::ARG => $state, 'paged' => false ] ) ),
			];
		}

		return $chips;
	}

	private static function finishedLine( array $status, $checked ) {
		if ( empty( $status['finished_at'] ) || ! is_numeric( $status['finished_at'] ) ) {
			return '';
		}

		$at = (int) $status['finished_at'];

		return sprintf(
			/* translators: Notice on the Media Translation page after an image scan, followed by the counts. %1$s: the date, %2$s: the time, %3$s: the number of images checked. */
			_n( '%1$s at %2$s. %3$s image checked:', '%1$s at %2$s. %3$s images checked:', $checked, 'wpml-media' ),
			wp_date( (string) get_option( 'date_format' ), $at ),
			wp_date( (string) get_option( 'time_format' ), $at ),
			number_format_i18n( $checked )
		);
	}

	private static function rowModel( VerdictStore $store, OutdatedMarker $outdated, array $attachment, $changed ) {
		if ( empty( $attachment['is_image'] ) || ! isset( $attachment['post']->ID ) ) {
			return self::rowData( false, null, null );
		}

		$id     = (int) $attachment['post']->ID;
		$record = $store->get( $id );
		$row    = self::rowData( true, $store->state( $id ), $store->reasonFor( $id ), is_array( $record ) && ! empty( $record['changed_at'] ) );

		$row['outdated'] = [];
		$translations    = isset( $attachment['translations'] ) && is_array( $attachment['translations'] ) ? $attachment['translations'] : [];
		foreach ( $translations as $code => $translation ) {
			if ( ! empty( $translation['id'] ) && $outdated->isOutdated( (int) $translation['id'] ) ) {
				$row['outdated'][ $code ] = $changed;
			}
		}

		return $row;
	}

	private static function status() {
		$request = new \WP_REST_Request( 'GET', self::STATUS_ROUTE );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		if ( 200 !== $response->get_status() ) {
			return null;
		}

		$data = $response->get_data();

		return is_array( $data ) ? $data : null;
	}

	private static function services() {
		$services = Services::instance();
		if ( null === $services ) {
			return null;
		}

		return [ $services->verdictStore(), $services->outdatedMarker() ];
	}

	private static function filterOptions() {
		return [
			[
				'value' => '',
				/* translators: Option in the scan-result filter on the Media Translation page: do not filter by scan result. */
				'label' => __( 'All scan results', 'wpml-media' ),
			],
			[
				'value' => VerdictStore::STATE_TRANSLATE,
				'label' => self::stateLabel( VerdictStore::STATE_TRANSLATE ),
			],
			[
				'value' => VerdictStore::STATE_SKIP,
				'label' => self::stateLabel( VerdictStore::STATE_SKIP ),
			],
			[
				'value' => VerdictStore::STATE_UNSURE,
				'label' => self::stateLabel( VerdictStore::STATE_UNSURE ),
			],
			[
				'value' => self::NOT_SCANNED,
				'label' => self::stateLabel( self::NOT_SCANNED ),
			],
		];
	}

	private static function stateLabel( $state ) {
		if ( VerdictStore::STATE_TRANSLATE === $state ) {
			/* translators: Scan result of an image on the Media Translation page, and an option of its filter: the text on the image is worth translating. */
			return __( 'Worth translating', 'wpml-media' );
		}

		if ( VerdictStore::STATE_SKIP === $state ) {
			/* translators: Scan result of an image on the Media Translation page, and an option of its filter: the image is not translated automatically. */
			return __( 'Skipped', 'wpml-media' );
		}

		if ( VerdictStore::STATE_UNSURE === $state ) {
			/* translators: Scan result of an image on the Media Translation page, and an option of its filter: WPML is not sure the image needs translating. */
			return __( 'Not sure', 'wpml-media' );
		}

		/* translators: Scan result of an image on the Media Translation page, and an option of its filter: the image was not checked yet. */
		return __( 'Not scanned', 'wpml-media' );
	}

	private static function skipCopy( $reason ) {
		switch ( $reason ) {
			case 'photo':
				return [
					/* translators: Scan result of an image on the Media Translation page: skipped because it is a camera photo. */
					__( 'Skipped: photo', 'wpml-media' ),
					/* translators: Tooltip of the scan result "Skipped: photo" on the Media Translation page. */
					__( 'Photo. Camera photos are skipped unless you pick them, because text in a photo (signs, packaging) is rarely content to translate.', 'wpml-media' ),
				];
			case 'screenshot':
				return [
					/* translators: Scan result of an image on the Media Translation page: skipped because it is a screenshot. */
					__( 'Skipped: screenshot', 'wpml-media' ),
					/* translators: Tooltip of the scan result "Skipped: screenshot" on the Media Translation page. */
					__( 'Screenshot. Screenshots show your product as it is, so WPML does not translate them automatically.', 'wpml-media' ),
				];
			case 'brand':
				return [
					/* translators: Scan result of an image on the Media Translation page: skipped because it shows a brand name or logo. */
					__( 'Skipped: brand', 'wpml-media' ),
					/* translators: Tooltip of the scan result "Skipped: brand" on the Media Translation page. */
					__( 'Brand imagery. A brand name or logo stays the same in every language.', 'wpml-media' ),
				];
			case 'no_text':
				return [
					/* translators: Scan result of an image on the Media Translation page: skipped because no text was found on it. */
					__( 'Skipped: no text', 'wpml-media' ),
					/* translators: Tooltip of the scan result "Skipped: no text" on the Media Translation page. */
					__( 'No text found on this image.', 'wpml-media' ),
				];
			case 'incidental':
				return [
					/* translators: Scan result of an image on the Media Translation page: skipped because its only text is in the background. */
					__( 'Skipped: background text', 'wpml-media' ),
					/* translators: Tooltip of the scan result "Skipped: background text" on the Media Translation page. */
					__( 'Text only in the background, such as a shop sign in a street scene. It is not content to translate.', 'wpml-media' ),
				];
			case 'format':
				return [
					/* translators: Scan result of an image on the Media Translation page: skipped because WPML does not check this file type. */
					__( 'Skipped: file type', 'wpml-media' ),
					/* translators: Tooltip of the scan result "Skipped: file type" on the Media Translation page. */
					__( 'File type not supported. WPML checks JPEG, PNG, WebP and static GIF images.', 'wpml-media' ),
				];
			case 'small':
				return [
					/* translators: Scan result of an image on the Media Translation page: skipped because the image is too small to hold readable text. */
					__( 'Skipped: too small', 'wpml-media' ),
					/* translators: Tooltip of the scan result "Skipped: too small" on the Media Translation page. */
					__( 'Too small for readable text: the image is under 64 pixels on a side.', 'wpml-media' ),
				];
			default:
				return [
					self::stateLabel( VerdictStore::STATE_SKIP ),
					/* translators: Tooltip of the scan result "Skipped" on the Media Translation page, when WPML names no reason. */
					__( 'WPML found no text worth translating on this image.', 'wpml-media' ),
				];
		}
	}

	private static function noPermissionText() {
		/* translators: Notice on the Media Translation page for a user who may not use automatic translation. */
		return __( 'You can see the scan results. Starting a scan or changing the setting needs permission to use automatic translation.', 'wpml-media' );
	}

	private static function offTitle() {
		/* translators: Title of the notice on the Media Translation page when the setting "Find images with text" is off. */
		return __( 'Finding images with text is off', 'wpml-media' );
	}
}
