<?php

use WPML\Media\Option;

class WPML_Media_Translations_UI extends WPML_Templates_Factory {
	const PREVIEW_MAX_WIDTH  = 800;
	const PREVIEW_MAX_HEIGHT = 600;
	private $sitepress;
	private $wpdb;
	private $wp_locale;
	private $wpml_query_filter;
	private $pagination;
	private $query_args = array();

	public function __construct(
		SitePress $sitepress,
		wpdb $wpdb,
		WP_Locale $wp_locale,
		WPML_Query_Filter $wpml_query_filter,
		WPML_Admin_Pagination $pagination
	) {
		parent::__construct();
		$this->sitepress         = $sitepress;
		$this->wpdb              = $wpdb;
		$this->wp_locale         = $wp_locale;
		$this->wpml_query_filter = $wpml_query_filter;
		$this->pagination        = $pagination;
	}

	public function get_model() {

		$this->set_query_args();

		$languages = $this->get_languages();

		$model = array(
			'strings'                  => array(
				/* translators: Name of the Media Translation page in the WPML menu, and the heading at the top of that page. */
				'heading'                        => __( 'Media Translation', 'wpml-media' ),
				'filter_by_date'                 => __( 'Filter by date', 'wpml-media' ),
				/* translators: Option in the date filter on the Media Translation page: do not filter by date. */
				'all_dates'                      => __( 'All dates', 'wpml-media' ),
				'filter_sentence'                => self::get_filter_sentence(),
				'filter_by_status'               => __( 'Filter by translation status', 'wpml-media' ),
				'status_all'                     => __( 'All translation statuses', 'wpml-media' ),
				'status_not'                     => __( 'Media file not translated', 'wpml-media' ),
				'status_translated'              => __( 'Translated media uploaded', 'wpml-media' ),
				'status_needs_translation'       => __( 'Needs media file translation', 'wpml-media' ),
				/* translators: Accessible name of the dropdown on the Media Translation page that picks the language the media was uploaded in. */
				'filter_by_source_language'      => __( 'Filter by original language', 'wpml-media' ),
				/* translators: Accessible name of the dropdown on the Media Translation page that picks the language the media is translated into. */
				'filter_by_target_language'      => __( 'Filter by translation language', 'wpml-media' ),
				/* translators: Option in the language filter on the Media Translation page: do not filter by language. */
				'any_language'                   => __( 'Any language', 'wpml-media' ),
				/* translators: Label of the search field on the Media Translation page. */
				'search_media'                   => __( 'Search Media:', 'wpml-media' ),
				'search_placeholder'             => __( 'Title, caption, description or file name', 'wpml-media' ),
				/* translators: Button label next to the search field on the Media Translation page: it applies the filters above. Verb, imperative. */
				'search_button_label'            => __( 'Filter', 'wpml-media' ),
				/* translators: Column heading on the Media Translation page: the language the media file was uploaded in. */
				'original_language'              => __( 'Original language', 'wpml-media' ),
				/* translators: Column heading on the Media Translation page: what kind of file it is, for example an image or a document. */
				'media_type'                     => __( 'Media Type', 'wpml-media' ),
				/* translators: Label on the Media Translation page for media whose texts were copied into the translation and are edited separately. */
				'copied_media'                   => __( 'Copied media', 'wpml-media' ),
				/* translators: Label on the Media Translation page for media whose texts are read from the media library every time the page loads. */
				'referenced_media'               => __( 'Connected media', 'wpml-media' ),
				'media_text_is_copy'             => __( 'This media text is a copy', 'wpml-media' ),
				'media_text_is_copy_notice'      => __(
					'Some builders like Block Editor, the Divi Image module, or WooCommerce product images, copy the image texts (alt, caption, title) into the translations as soon as you insert the image. Each translation then keeps its own copy of these texts. Changing the translations here will not update those existing translations automatically.',
					'wpml-media'
				),
				/* translators: Label on the Media Translation page shown above the texts of a media file: "This" is the text being edited, which is read from the media library rather than copied. */
				'media_text_is_reference'        => __( 'This is connected media text', 'wpml-media' ),
				'media_text_is_reference_notice' => __(
					'Most builders, like Elementor, Beaver Builder, Cornerstone, and many gallery plugins, simply point to the text in the Media library every time a page loads. When you update the texts here, every page (and its translations) will instantly reflect the changes.',
					'wpml-media'
				),
				'media_text_is_copy_ref'         => __( 'This media text is both copied and connected', 'wpml-media' ),
				'media_text_is_copy_ref_notice'  => __(
					'When you update the media texts here, every page (and its translations) will instantly reflect the changes.',
					'wpml-media'
				),
				'media_text_cannot_edit_notice'  => __(
					'This media\'s texts were automatically translated, and you cannot edit them here. Instead, use the Advanced Translation Editor to edit the translation of the page where this image is inserted.',
					'wpml-media'
				),
				'no_attachments'                 => __( 'No attachments found', 'wpml-media' ),
				'add_translation'                => __( 'Add media file translation', 'wpml-media' ),
				/* translators: Tooltip on the edit icon on the Media Translation page. %s: the language name the media file is translated into. */
				'edit_translation'               => __( 'Edit %s translation', 'wpml-media' ),
				/* translators: Accessible name of the add icon on the Media Translation page, for a language with no translation yet. %s: the language name. */
				'add_translation_for'            => __( 'Add %s translation', 'wpml-media' ),
				/* translators: Label above the original text of a media file on the Media Translation page. Noun. */
				'original'                       => __( 'Original:', 'wpml-media' ),
				/* translators: Label above the translated text of a media file on the Media Translation page. Noun. */
				'translation'                    => __( 'Translation:', 'wpml-media' ),
				/* translators: Label of the file field of a media item on the Media Translation page. Noun. */
				'file'                           => __( 'File', 'wpml-media' ),
				/* translators: Label of the name field of a media file on the Media Translation page. Noun, not the verb "to name". */
				'name'                           => __( 'Name', 'wpml-media' ),
				/* translators: Label of the caption field of a media item on the Media Translation page. Noun. */
				'caption'                        => __( 'Caption', 'wpml-media' ),
				/* translators: Label of the alternative-text field of a media item on the Media Translation page: the text screen readers read in place of the image. */
				'alt_text'                       => __( 'Alt text', 'wpml-media' ),
				/* translators: Label of the description field of a media file on the Media Translation page. Noun. */
				'description'                    => __( 'Description', 'wpml-media' ),
				'copy_from_original'             => __( 'Copy from original', 'wpml-media' ),
				'upload_translated_media'        => __( 'Upload translated media file', 'wpml-media' ),
				'use_different_file'             => __( 'Use a different file', 'wpml-media' ),
				'revert_to_original'             => __( 'Revert to original', 'wpml-media' ),
				'restore_original_media'         => __( 'Restore original media file', 'wpml-media' ),
				'statuses'                       => self::get_translation_status_labels(),
			),
			'months'                   => $this->get_months(),
			'selected_month'           => isset( $this->query_args['m'] ) ? (int) $this->query_args['m'] : 0,
			'selected_status'          => $this->should_filter_by_status() ? $this->query_args['status'] : '',
			'from_language'            => isset( $this->query_args['slang'] ) ? $this->query_args['slang'] : '',
			'to_language'              => isset( $this->query_args['tlang'] ) ? $this->query_args['tlang'] : '',
			'statuses'                 => array(
				'not_translated'    => WPML_Media_Translation_Status::NOT_TRANSLATED,
				'translated'        => WPML_Media_Translation_Status::TRANSLATED,
				'needs_translation' => WPML_Media_Translation_Status::NEEDS_MEDIA_TRANSLATION,
			),
			'search'                   => isset( $this->query_args['s'] ) ? $this->query_args['s'] : '',
			'languages'                => $languages,
			'attachments'              => $this->get_attachments( $languages ),
			'nonce'                    => wp_nonce_field( 'media-translation', 'wpnonce', false, false ),
			'pagination'               => $this->get_pagination(),
			'target_language'          => $this->should_filter_by_target_language() ? $this->query_args['tlang'] : '',

			'batch_translation'        => $this->get_batch_translation(),

			'should_handle_media_auto' => Option::shouldHandleMediaAuto(),
		);

		return $model;
	}

	private static function get_filter_sentence() {
		$english = '%1$s in %2$s to %3$s';

		return \WPML\Localization\SentenceSegments::split(
			/* translators: The filter bar on the Media Translation page, e.g. "[All dates] in [Any language] to [Any language]". %1$s: date dropdown, %2$s: dropdown with the language the media was uploaded in, %3$s: dropdown with the language it is translated into. Keep all three placeholders; you can change their order. */
			__( '%1$s in %2$s to %3$s', 'wpml-media' ),
			3,
			$english
		);
	}

	public static function get_translation_status_labels() {
		return array(
			/* translators: Status value on the Media Translation page: the media file has no translation yet. */
			WPML_Media_Translation_Status::NOT_TRANSLATED => __( 'Not translated', 'wpml-media' ),
			/* translators: Status value on the Media Translation page: the media file has been translated. Past participle used as a state, not a past-tense sentence. */
			WPML_Media_Translation_Status::TRANSLATED     => __( 'Translated', 'wpml-media' ),
			/* translators: Status value on the Media Translation page: the media file still has to be translated. */
			WPML_Media_Translation_Status::NEEDS_MEDIA_TRANSLATION => __( 'Needs translation', 'wpml-media' ),
		);
	}

	private function set_query_args() {
		$arg_keys = array( 'm', 'status', 'slang', 'tlang', 's', 'paged' );
		foreach ( $arg_keys as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$this->query_args[ $key ] = sanitize_text_field( $_GET[ $key ] );
			}
		}

		foreach ( array( 'slang', 'tlang' ) as $language_arg ) {
			if ( isset( $this->query_args[ $language_arg ] ) && ! $this->is_language_filter_value( $this->query_args[ $language_arg ] ) ) {
				unset( $this->query_args[ $language_arg ] );
			}
		}
	}

	private function is_language_filter_value( $value ) {
		if ( '' === $value || 'all' === $value ) {
			return true;
		}

		return is_string( $value ) && array_key_exists( $value, (array) $this->sitepress->get_active_languages() );
	}

	private function get_months() {
		$wpdb = $this->wpdb;

		$months = array();

		$month_results = $this->wpdb->get_results(
			"
			SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
			FROM {$wpdb->posts}
			WHERE post_type = 'attachment'
			ORDER BY post_date DESC
		"
		);

		foreach ( $month_results as $month ) {
			$months[] = array(
				'id'    => $month->year . zeroise( $month->month, 2 ),
				// translators: %1: month name, %2: year.
				'label' => sprintf( __( '%1$s %2$d' ), $this->wp_locale->get_month( $month->month ), $month->year ),
			);
		}

		return $months;
	}

	private function get_languages() {
		$languages = array();

		$active_languages = $this->sitepress->get_active_languages();
		foreach ( $active_languages as $language ) {
			$languages[ $language['code'] ] = array(
				'name' => $language['display_name'],
				'flag' => $this->sitepress->get_flag_url( $language['code'] ),
			);
		}

		return $languages;
	}

	private function get_items_per_page() {
		return get_option( 'wpml_media_translation_dashboard_items_per_page', 20 );
	}

	private function get_usages_of_media_file_in_posts( $post_id ) {
		if ( ! class_exists( '\WPML\MediaTranslation\UsageOfMediaFilesInPosts' ) ||
			! class_exists( '\WPML\MediaTranslation\PostWithMediaFilesFactory' ) ) {
			return [ [], [] ];
		}

		$postWithMediaFilesFactory = new \WPML\MediaTranslation\PostWithMediaFilesFactory();
		$postWithMediaFiles        = $postWithMediaFilesFactory->create( $post_id );
		return $postWithMediaFiles->get_usages_of_media_file_in_posts( $post_id );
	}

	private function get_attachments( $languages ) {
		$attachments = array();

		$args = array(
			'post_type'      => 'attachment',
			'orderby'        => 'ID',
			'order'          => 'DESC',
			'posts_per_page' => $this->get_items_per_page(),
		);
		$args = array_merge( $args, $this->query_args );

		$args['suppress_filters'] = false;

		$this->add_query_filters();

		$attachment_posts = get_posts( $args );

		$this->remove_query_filters();

		foreach ( $attachment_posts as $attachment ) {
			$attachment_id       = $attachment->ID;
			$post_element        = new WPML_Post_Element( $attachment_id, $this->sitepress );
			$media_file_original = get_post_meta( $attachment_id, '_wp_attached_file', true );
			$translations        = array();
			$media_custom_fields = WPML\MediaTranslation\AddMediaDataToTranslationPackage::get_media_custom_fields_to_translate( $attachment_id );

			$is_image = (int) ( 0 === strpos( $attachment->post_mime_type, 'image/' ) );

			foreach ( $languages as $code => $language ) {
				$translation = $post_element->get_translation( $code );

				if ( $translation ) {
					$translation_post      = $translation->get_wp_object();
					$media_file_translated = get_post_meta( $translation->get_id(), '_wp_attached_file', true );

					$media_custom_fields_translated = [];
					foreach ( $media_custom_fields as $field_key => $field_value ) {
						$media_custom_fields_translated[ $field_key ] = get_post_meta( $translation->get_id(), $field_key );
					}

					$media_is_translated = $media_file_translated && $media_file_translated !== $media_file_original;

					$translations[ $code ] = array(
						'id'                  => $translation->get_id(),
						'file_name'           => basename( $media_file_translated ),
						'title'               => $translation_post->post_title,
						'caption'             => $translation_post->post_excerpt,
						'description'         => $translation_post->post_content,
						'alt'                 => get_post_meta( $translation->get_id(), '_wp_attachment_image_alt', true ),
						'thumb'               => $this->get_attachment_thumb( $translation->get_id(), $is_image ),
						'media_is_translated' => $media_is_translated,
						'status'              => $this->get_translation_status( $media_is_translated ),
						'custom_fields'       => $media_custom_fields_translated,
					);

				} else {
					$translations[ $code ] = array(
						'status'        => WPML_Media_Translation_Status::NOT_TRANSLATED,
						'custom_fields' => array(),
					);
				}
			}

			$usages_in_posts = $this->get_usages_of_media_file_in_posts( $attachment->ID );

			$attachments[] = array(
				'post'                             => $attachment,
				'mime_type'                        => $is_image ? 'image/*' : $attachment->post_mime_type,
				'is_image'                         => $is_image,
				'file_name'                        => basename( $media_file_original ),
				'alt'                              => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'meta'                             => get_post_meta( $attachment_id, '_wp_attachment_metadata', true ),
				'language'                         => $post_element->get_language_code(),
				'thumb'                            => $this->get_attachment_thumb( $attachment_id, $is_image ),
				'url'                              => $is_image ? $this->get_attachment_url( $attachment_id ) : '',
				'translations'                     => $translations,
				'preview'                          => [
					'width'  => self::PREVIEW_MAX_WIDTH,
					'height' => self::PREVIEW_MAX_HEIGHT,
				],
				'custom_fields'                    => $media_custom_fields,
				'usages_in_posts'                  => [
					'by_copy'      => $usages_in_posts[0],
					'by_reference' => $usages_in_posts[1],
				],
				'has_usages_in_posts_by_copy'      => ! empty( $usages_in_posts[0] ),
				'has_usages_in_posts_by_reference' => ! empty( $usages_in_posts[1] ),
			);
		}

		return $attachments;
	}

	private function get_attachment_url( $attachment_id ) {
		$image = wp_get_attachment_image_src( $attachment_id, [ self::PREVIEW_MAX_WIDTH, self::PREVIEW_MAX_HEIGHT ] );

		return $image[0];
	}

	private function get_attachment_thumb( $attachment_id, $is_image = true ) {
		$image = wp_get_attachment_image_src( $attachment_id, 'thumbnail', true );

		$thumb = array(
			'src'    => $image ? $image[0] : '',
			'width'  => $is_image ? 60 : $image[1],
			'height' => $is_image ? 60 : $image[2],
		);

		return $thumb;
	}

	public function set_total_items_in_pagination( $query ) {

		$query_count = preg_replace(
			'/^SELECT /',
			'SELECT SQL_CALC_FOUND_ROWS ',
			trim( $query )
		);
		$this->wpdb->get_results( $query_count );

		$this->pagination->set_total_items( $this->wpdb->get_var( 'SELECT FOUND_ROWS()' ) );

		return $query;
	}

	private function get_pagination() {

		$this->pagination->set_items_per_page( $this->get_items_per_page() );
		$current_page = isset( $this->query_args['paged'] ) ? $this->query_args['paged'] : 1;
		$this->pagination->set_current_page( $current_page );

		$total_items = (int) $this->pagination->get_total_items();

		return array(
			'strings'     => WPML_Admin_Pagination_Render::get_strings( $total_items ),
			'pagination'  => array(
				'get_items_per_page'    => (int) $this->pagination->get_items_per_page(),
				'get_first_page_url'    => $this->pagination->get_first_page_url(),
				'get_previous_page_url' => $this->pagination->get_previous_page_url(),
				'get_current_page'      => (int) $this->pagination->get_current_page(),
				'get_total_pages'       => (int) $this->pagination->get_total_pages(),
				'get_next_page_url'     => $this->pagination->get_next_page_url(),
				'get_last_page_url'     => $this->pagination->get_last_page_url(),
			),
			'total_items' => $total_items,
		);
	}

	private function get_batch_translation() {
		$model = array(
			'scan_nonce' => wp_create_nonce( 'wpml_media_scan_prepare' ),
			'strings' => array(
				/* translators: Button label that closes the dialog on the Media Translation page. Verb, imperative, not the adjective "near". */
				'close'               => __( 'Close', 'wpml-media' ),
				'was_replaced'        => __( 'The media that you uploaded was replaced in these translated posts:', 'wpml-media' ),
				'other_posts'         => __( 'The same media might be used in other posts too. Do you want to scan and replace it?', 'wpml-media' ),
				'without_usage'       => __( 'The media that you uploaded will be used in future post translations. The same media might be used in already existing posts. Do you want to scan and replace it now?', 'wpml-media' ),
				'scan_for_this_media' => __( 'Scan for content that has specifically this media (faster)', 'wpml-media' ),
				'scan_for_all_media'  => __( 'Scan for content that has any of the media files that I translated (takes more time)', 'wpml-media' ),
				'button_label'        => _x( 'Scan & Replace', 'Button label (verb)', 'wpml-media' ),
			),
		);

		return $model;
	}

	private function add_query_filters() {
		remove_filter( 'posts_join', array( $this->wpml_query_filter, 'posts_join_filter' ), 10 );
		remove_filter( 'posts_where', array( $this->wpml_query_filter, 'posts_where_filter' ), 10 );

		add_filter( 'posts_join', array( $this, 'filter_request_clause_join' ), PHP_INT_MAX );
		add_filter( 'posts_where', array( $this, 'filter_request_clause_where' ), PHP_INT_MAX );
		add_filter( 'posts_search', array( $this, 'filter_request_clause_search' ), PHP_INT_MAX );
		add_filter( 'posts_request', array( $this, 'set_total_items_in_pagination' ), - PHP_INT_MAX );
	}

	private function remove_query_filters() {
		add_filter( 'posts_join', array( $this->wpml_query_filter, 'posts_join_filter' ), 10, 2 );
		add_filter( 'posts_where', array( $this->wpml_query_filter, 'posts_where_filter' ), 10, 2 );

		remove_filter( 'posts_join', array( $this, 'filter_request_clause_join' ), PHP_INT_MAX );
		remove_filter( 'posts_where', array( $this, 'filter_request_clause_where' ), PHP_INT_MAX );
		remove_filter( 'posts_search', array( $this, 'filter_request_clause_search' ), PHP_INT_MAX );
		remove_filter( 'posts_request', array( $this, 'set_total_items_in_pagination' ), - PHP_INT_MAX );
	}

	private function is_searching() {
		return isset( $this->query_args['s'] ) && '' !== trim( (string) $this->query_args['s'] );
	}

	public function filter_request_clause_search( $search ) {
		if ( ! $this->is_searching() || '' === trim( (string) $search ) ) {
			return $search;
		}

		$file_clause = $this->wpdb->prepare(
			'media_file_search.meta_value LIKE %s',
			'%' . $this->wpdb->esc_like( trim( (string) $this->query_args['s'] ) ) . '%'
		);

		return preg_replace( '/^\s*AND\s*\(/', ' AND ( ( ' . $file_clause . ' ) OR ', $search, 1 );
	}

	public function filter_request_clause_join( $join ) {

		$join .= " LEFT JOIN {$this->wpdb->prefix}icl_translations icl_translations_source
				   ON icl_translations_source.element_id = {$this->wpdb->posts}.ID ";

		if ( $this->is_searching() ) {
			$join .= " LEFT JOIN {$this->wpdb->postmeta} media_file_search
				       ON media_file_search.post_id = {$this->wpdb->posts}.ID
				      AND media_file_search.meta_key = '_wp_attached_file' ";
		}

		if ( $this->should_filter_by_status() ) {
			$join .= $this->wpdb->prepare(
				" LEFT JOIN {$this->wpdb->postmeta} media_file_original
				       ON media_file_original.post_id = {$this->wpdb->posts}.ID
				      AND media_file_original.meta_key = %s ",
				'_wp_attached_file'
			);
		} else {
			if ( $this->should_filter_by_target_language() ) {
				$join .= $this->wpdb->prepare(
					" JOIN {$this->wpdb->prefix}icl_translations icl_translations_target
					   ON icl_translations_target.trid = icl_translations_source.trid
					   AND icl_translations_target.language_code = %s ",
					$this->query_args['tlang']
				);
			}
		}

		return $join;
	}

	private function media_translation_exists_clause( $language_code, $file_test = '' ) {
		return $this->wpdb->prepare(
			"EXISTS (
				SELECT 1
				FROM {$this->wpdb->prefix}icl_translations media_translation
				LEFT JOIN {$this->wpdb->postmeta} media_file_translated
				       ON media_file_translated.post_id = media_translation.element_id
				      AND media_file_translated.meta_key = %s
				WHERE media_translation.trid = icl_translations_source.trid
				  AND media_translation.element_type = 'post_attachment'
				  AND media_translation.source_language_code IS NOT NULL
				  AND media_translation.element_id IS NOT NULL
				  AND media_translation.language_code = %s
				  {$file_test}
			)",
			'_wp_attached_file',
			$language_code
		);
	}

	private function media_file_is_translated_clause( $language_code ) {
		return $this->media_translation_exists_clause(
			$language_code,
			"AND media_file_translated.meta_value IS NOT NULL
			 AND media_file_translated.meta_value <> ''
			 AND CAST( media_file_translated.meta_value AS BINARY )
				 <> CAST( COALESCE( media_file_original.meta_value, '' ) AS BINARY )"
		);
	}

	private function status_clause( $status, $language_code ) {
		if ( WPML_Media_Translation_Status::TRANSLATED === $status ) {
			return '( ' . $this->media_file_is_translated_clause( $language_code ) . ' )';
		}

		if ( WPML_Media_Translation_Status::NEEDS_MEDIA_TRANSLATION === $status ) {
			return '( ' . $this->media_translation_exists_clause( $language_code )
				. ' AND NOT ' . $this->media_file_is_translated_clause( $language_code ) . ' )';
		}

		return '( NOT ' . $this->media_file_is_translated_clause( $language_code ) . ' )';
	}

	private function target_languages() {
		$default_language = $this->sitepress->get_default_language();
		$target_languages = array();

		foreach ( $this->sitepress->get_active_languages() as $language ) {
			if ( $language['code'] !== $default_language ) {
				$target_languages[] = $language['code'];
			}
		}

		return $target_languages;
	}

	public function filter_request_clause_where( $where ) {

		$where .= " AND icl_translations_source.element_type='post_attachment' 
					AND icl_translations_source.source_language_code IS NULL ";

		if ( ! empty( $this->query_args['slang'] ) && 'all' !== $this->query_args['slang'] ) {
			$where .= $this->wpdb->prepare( ' AND icl_translations_source.language_code = %s ', $this->query_args['slang'] );
		}

		if ( $this->should_filter_by_status() ) {
			$status = $this->query_args['status'];

			if ( $this->should_filter_by_target_language() ) {
				$where .= ' AND ' . $this->status_clause( $status, $this->query_args['tlang'] ) . ' ';
			} else {
				$target_languages = $this->target_languages();

				if ( ! $target_languages ) {
					$where .= WPML_Media_Translation_Status::NOT_TRANSLATED === $status ? ' AND 1 ' : ' AND 0 ';
				} elseif ( WPML_Media_Translation_Status::TRANSLATED === $status ) {
					foreach ( $target_languages as $language_code ) {
						$where .= ' AND ' . $this->status_clause( $status, $language_code ) . ' ';
					}
				} else {
					$clauses = array();
					foreach ( $target_languages as $language_code ) {
						$clauses[] = $this->status_clause( $status, $language_code );
					}
					$where .= ' AND ( ' . implode( ' OR ', $clauses ) . ' ) ';
				}
			}
		}

		return $where;
	}

	private function offered_statuses() {
		return array_keys( self::get_translation_status_labels() );
	}

	private function should_filter_by_status() {
		return isset( $this->query_args['status'] )
			&& in_array( $this->query_args['status'], $this->offered_statuses(), true );
	}

	private function should_filter_by_target_language() {
		return isset( $this->query_args['tlang'] ) && '' !== $this->query_args['tlang'];
	}

	protected function init_template_base_dir() {
		$this->template_paths = array(
			WPML_MEDIA_PATH . '/templates/menus/',
			WPML_PLUGIN_PATH . '/templates/pagination/',
		);
	}

	public function get_template() {
		return 'media-translation.twig';
	}

	private function get_translation_status( $media_file_is_translated ) {
		return $media_file_is_translated
			? WPML_Media_Translation_Status::TRANSLATED
			: WPML_Media_Translation_Status::NEEDS_MEDIA_TRANSLATION;
	}


}
