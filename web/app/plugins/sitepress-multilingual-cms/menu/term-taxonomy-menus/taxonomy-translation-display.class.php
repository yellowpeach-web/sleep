<?php

use WPML\Localization\SentenceSegments;

new WPML_Taxonomy_Translation_Sync_Display();

class WPML_Taxonomy_Translation_Table_Display {

	private static function get_strings_translation_array() {
		$st_plugin = '<a href="' . get_admin_url( null, 'plugins.php' ) . '" target="_blank" class="wpml-external-link">WPML String Translation</a>';

		$labels = array(
			'filterSentence'                  => SentenceSegments::split(
				/* translators: The filter on the taxonomy translation screen when only the terms are filtered, e.g. "Show [all categories]". %1$s: dropdown that picks which terms to show. Show is a verb, imperative. */
				__( 'Show %1$s', 'sitepress' ),
				1,
				'Show %1$s'
			),
			'filterSentenceWithLanguage'      => SentenceSegments::split(
				/* translators: The filter on the taxonomy translation screen, e.g. "Show [untranslated categories] in [any language]". %1$s: dropdown that picks which terms to show, %2$s: language dropdown. Show is a verb, imperative. Keep both placeholders; you can change their order. */
				__( 'Show %1$s in %2$s', 'sitepress' ),
				2,
				'Show %1$s in %2$s'
			),
			/* translators: Accessible name of the dropdown on the taxonomy translation screen that picks all terms or only the untranslated ones. */
			'filterByStatus'                  => __( 'Filter by translation status', 'sitepress' ),
			/* translators: Accessible name of the language dropdown on the taxonomy translation screen. */
			'filterByLanguage'                => __( 'Filter by language', 'sitepress' ),
			/* translators: First word of the filter option "untranslated categories" on the taxonomy translation screen; the name of the taxonomy follows it. Adjective: the terms that have no translation yet. */
			'untranslated'                    => __( 'untranslated', 'sitepress' ),
			/* translators: First word of the filter option "all categories" on the taxonomy translation screen; the name of the taxonomy follows it. */
			'all'                             => __( 'all', 'sitepress' ),
			/* translators: Word between two numbers above a list, as in "Displaying 1 of 20": the first is what is shown, the second is how many there are in all. */
			'of'                              => __( 'of', 'sitepress' ),
			/* translators: Column heading and label for the kind of grouping a term belongs to, for example Category or Tag. Singular. */
			'taxonomy'                        => __( 'Taxonomy', 'sitepress' ),
			/* translators: First option in the language dropdown on the taxonomy translation screen: do not filter by language. */
			'anyLang'                         => __( 'any language', 'sitepress' ),
			/* translators: Button label next to the filters on the taxonomy translation screen: load the list again with the chosen filters. Verb, imperative. */
			'apply'                           => __( 'Refresh', 'sitepress' ),
			'synchronizeBtn'                  => __( 'Update Taxonomy Hierarchy', 'sitepress' ),
			/* translators: Button label above a list, and the text inside the search field, for looking something up. Verb, imperative. */
			'searchPlaceHolder'               => __( 'Search', 'sitepress' ),
			/* translators: First option in the dropdown that picks a parent term on the taxonomy translation screen, shown between dashes. */
			'selectParent'                    => __( 'select parent', 'sitepress' ),
			/* translators: Label in front of the dropdown that picks the taxonomy to work on. The dropdown follows the colon, so keep the trailing space. */
			'taxToTranslate'                  => __( 'Select the taxonomy to translate: ', 'sitepress' ),
			/* translators: Heading of the term translation section on the taxonomy translation screen. %1$s: the name of the taxonomy in the singular, for example Category. */
			'translate'                       => sprintf( __( '%1$s Translation', 'sitepress' ), '%taxonomy%' ),
			/* translators: Tab label on the taxonomy translation screen: the view where the term hierarchy of a language is made to match the original. */
			'Synchronize'                     => __( 'Hierarchy Synchronization', 'sitepress' ),
			/* translators: Link text inside a sentence that opens the screen where the text is translated. It starts in lower case because it sits inside the sentence. Verb, imperative. */
			'lowercaseTranslate'              => __( 'translate', 'sitepress' ),
			'copyToAllLanguages'              => __( 'Copy to all languages', 'sitepress' ),
			/* translators: Question asked before a term is copied into every other language, on the taxonomy translation screen. %1$s: the name of the original language. */
			'copyToAllMessage'                => sprintf( __( 'Copy this term from original: %1$s to all other languages?', 'sitepress' ), '%language%' ),
			'copyAllOverwrite'                => __( 'Overwrite existing translations', 'sitepress' ),
			'willBeRemoved'                   => __( 'Will be removed', 'sitepress' ),
			'willBeAdded'                     => __( 'Will be added', 'sitepress' ),
			/* translators: Word in front of the colour key under the hierarchy synchronization table. */
			'legend'                          => __( 'Legend:', 'sitepress' ),
			/* translators: Line above the hierarchy synchronization table. %1$s: a dropdown where the user picks the language whose hierarchy is copied. */
			'refLang'                         => sprintf( __( 'Synchronize taxonomy hierarchy according to: %1$s language.', 'sitepress' ), '%language%' ),
			/* translators: Heading on the taxonomy translation screen for the language a term is translated into. */
			'targetLang'                      => __( 'Target Language', 'sitepress' ),
			/* translators: Title of the dialog where a single term is translated. */
			'termPopupDialogTitle'            => __( 'Term translation', 'sitepress' ),
			/* translators: Title of the dialog that shows a term in its original language. */
			'originalTermPopupDialogTitle'    => __( 'Original term', 'sitepress' ),
			/* translators: Title of the dialog where the name of a taxonomy is translated. Noun phrase: the translation of a label, not an instruction to label something. */
			'labelPopupDialogTitle'           => __( 'Label translation', 'sitepress' ),
			'copyFromOriginal'                => __( 'Copy from original', 'sitepress' ),
			/* translators: Heading in the term translation dialog, followed by the flag and the name of the original language. */
			'original'                        => __( 'Original:', 'sitepress' ),
			/* translators: Heading in the term translation dialog, followed by the flag and the name of the language being translated into. */
			'translationTo'                   => __( 'Translation to:', 'sitepress' ),
			/* translators: Label of the field holding the name of a term in the term translation dialog. Noun. */
			'Name'                            => __( 'Name', 'sitepress' ),
			/* translators: Label of the field holding the part of the address that stands for a term, in the term translation dialog. */
			'Slug'                            => __( 'Slug', 'sitepress' ),
			/* translators: Column heading and field label for the longer text that describes something. */
			'Description'                     => __( 'Description', 'sitepress' ),
			/* translators: Button label that confirms a dialog and carries out what it asks about. */
			'Ok'                              => __( 'OK', 'sitepress' ),
			/* translators: Button label that keeps what was entered. Verb, imperative. */
			'save'                            => __( 'Save', 'sitepress' ),
			/* translators: Label of the field holding the singular name of a taxonomy in the label translation dialog. */
			'Singular'                        => __( 'Singular', 'sitepress' ),
			/* translators: Label of the field holding the plural name of a taxonomy in the label translation dialog. */
			'Plural'                          => __( 'Plural', 'sitepress' ),
			/* translators: Link in a term row on the taxonomy translation screen that opens the list of languages, so the term can be moved to another language. Verb, imperative. */
			'changeLanguage'                  => __( 'Change language', 'sitepress' ),
			/* translators: Button label that closes a dialog without doing anything, or stops what is going on. Verb, imperative, not the noun "a cancellation". */
			'cancel'                          => __( 'Cancel', 'sitepress' ),
			/* translators: Text shown while the taxonomy translation screen is waiting for data. */
			'loading'                         => __( 'loading', 'sitepress' ),
			/* translators: Button label that keeps what was entered. Verb, imperative. */
			'Save'                            => __( 'Save', 'sitepress' ),
			/* translators: Screen reader name of the field that holds the number of the page being shown. */
			'currentPage'                     => __( 'Current page', 'sitepress' ),
			'goToPreviousPage'                => __( 'Go to previous page', 'sitepress' ),
			'goToNextPage'                    => __( 'Go to the next page', 'sitepress' ),
			'goToFirstPage'                   => __( 'Go to the first page', 'sitepress' ),
			'goToLastPage'                    => __( 'Go to the last page', 'sitepress' ),
			'hieraSynced'                     => __( 'The taxonomy hierarchy is now synchronized.', 'sitepress' ),
			'hieraAlreadySynced'              => __( 'The taxonomy hierarchy is already synchronized.', 'sitepress' ),
			/* translators: Message shown instead of the table when no term matches the filters. %1$s: the name of the taxonomy in the plural, for example categories. */
			'noTermsFound'                    => sprintf( __( 'No %1$s found.', 'sitepress' ), '%taxonomy%' ),
			/* translators: Word after the number of terms under the table on the taxonomy translation screen, as in "25 items". Plural. */
			'items'                           => __( 'items', 'sitepress' ),
			/* translators: The word for one piece of content, used inside sentences about deleting and restoring, as in "1 item restored". Singular, in lower case. */
			'item'                            => __( 'item', 'sitepress' ),
			/* translators: Heading of the term translation section on the taxonomy translation screen. %1$s: the name of the taxonomy in the plural, in bold, for example Categories. */
			'summaryTerms'                    => sprintf( __( 'Translation of %1$s', 'sitepress' ), '%taxonomy%' ),
			/* translators: Heading of the section where the name and the address part of a taxonomy are translated. %1$s: the name of the taxonomy in the singular, in bold, for example Category. */
			'summaryLabels'                   => sprintf( __( 'Translations of taxonomy %1$s labels and slug', 'sitepress' ), '%taxonomy%' ),
			/* translators: Notice shown when the plugin that translates taxonomy names is missing. %s: a link, already wrapped in its tags, whose text is the name of that plugin. */
			'activateStringTranslation'       => sprintf( __( 'To translate taxonomy labels and slug you need %s plugin.', 'sitepress' ), $st_plugin ),
			/* translators: Text shown while the taxonomy translation screen is waiting for the terms. */
			'preparingTermsData'              => __( 'Loading ...', 'sitepress' ),
			/* translators: Error notice on the taxonomy translation screen when the list of terms could not be loaded. */
			'termsLoadFailed'                 => __( 'The terms could not load. Reload the page to try again.', 'sitepress' ),
			/* translators: Heading of the first column of the term table, holding the terms as they are written in the original language. %1$s: the name of the taxonomy in the singular, for example Category. */
			'firstColumnHeading'              => sprintf( __( '%1$s terms (in original language)', 'sitepress' ), '%taxonomy%' ),
			'wpml_save_term_nonce'            => wp_create_nonce( 'wpml_save_term_nonce' ),
			'wpml_tt_sync_hierarchy_nonce'    => wp_create_nonce( 'wpml_tt_sync_hierarchy_nonce' ),
			'wpml_generate_unique_slug_nonce' => wp_create_nonce( 'wpml_generate_unique_slug_nonce' ),
			'wpml_taxonomy_translation_nonce' => wp_create_nonce( 'wpml_taxonomy_translation_nonce' ),

			/* translators: Name of the plus icon in a term row on the taxonomy translation screen, used as its tooltip and its screen reader name; the language name comes before it. Verb, imperative. */
			'addTranslation'                  => __( 'Add translation', 'sitepress' ),
			/* translators: Name of the pencil icon that opens a translation for editing, used as its tooltip and its screen reader name. Verb, imperative. */
			'editTranslation'                 => __( 'Edit translation', 'sitepress' ),
			/* translators: Tooltip and screen reader name of the spinning status icon while WPML checks whether a translation is done; the language name comes before it. */
			'refreshingTranslation'           => __( 'Refreshing translation status. Please wait.', 'sitepress' ),
			/* translators: Status of a translation: the original changed after it was translated, so the translation has to be gone over again. */
			'needsUpdate'                     => __( 'Needs update', 'sitepress' ),
			/* translators: Name of the icon that marks the language a term was written in, used as its tooltip and its screen reader name; the language name comes before it. */
			'originalLanguage'                => __( 'Original language', 'sitepress' ),

			'editTranslationOfTerm'           => sprintf(
				/* translators: Name of the edit icon in a term row on the taxonomy translation screen, used as its tooltip and its screen reader name. %1$s: the language name, %2$s: the translated term, for example "Edit the German translation: Bücher". */
				__( 'Edit the %1$s translation: %2$s', 'sitepress' ),
				'%language%',
				'%term%'
			),
			'needsUpdateOfTerm'               => sprintf(
				/* translators: Name of the icon that marks a term whose translation is out of date, used as its tooltip and its screen reader name. %1$s: the language name, %2$s: the translated term, for example "The German translation needs an update: Bücher". */
				__( 'The %1$s translation needs an update: %2$s', 'sitepress' ),
				'%language%',
				'%term%'
			),
			'termMetaLabel'                   => __( 'This term has additional meta fields:', 'sitepress' ),
		);

		return $labels;
	}

	public static function enqueue_taxonomy_table_resources( $sitepress ) {

		WPML_Simple_Language_Selector::enqueue_scripts();

		wp_enqueue_style( 'translate-taxonomy', ICL_PLUGIN_URL . '/res/css/taxonomy-translation.css', array(), ICL_SITEPRESS_SCRIPT_VERSION );

		$core_dependencies = array( 'jquery', 'jquery-ui-dialog', 'backbone', 'wpml-underscore-template-compiler' );
		wp_register_script(
			'templates-compiled',
			ICL_PLUGIN_URL . '/res/js/templates-compiled.js',
			$core_dependencies,
			ICL_SITEPRESS_SCRIPT_VERSION
		);
		$core_dependencies[] = 'templates-compiled';
		wp_register_script( 'main-util', ICL_PLUGIN_URL . '/res/js/taxonomy-translation/util.js', $core_dependencies, ICL_SITEPRESS_SCRIPT_VERSION );

		wp_register_script( 'main-model', ICL_PLUGIN_URL . '/res/js/taxonomy-translation/main.js', $core_dependencies, ICL_SITEPRESS_SCRIPT_VERSION );
		$core_dependencies[] = 'main-model';

		$dependencies = $core_dependencies;
		wp_register_script(
			'term-rows-collection',
			ICL_PLUGIN_URL . '/res/js/taxonomy-translation/collections/term-rows.js',
			array_merge( $core_dependencies, array( 'term-row-model' ) ),
			ICL_SITEPRESS_SCRIPT_VERSION
		);
		$dependencies[] = 'term-rows-collection';
		wp_register_script(
			'term-model',
			ICL_PLUGIN_URL . '/res/js/taxonomy-translation/models/term.js',
			$core_dependencies,
			ICL_SITEPRESS_SCRIPT_VERSION
		);
		$dependencies[] = 'term-model';
		wp_register_script(
			'taxonomy-model',
			ICL_PLUGIN_URL . '/res/js/taxonomy-translation/models/taxonomy.js',
			$core_dependencies,
			ICL_SITEPRESS_SCRIPT_VERSION
		);
		$dependencies[] = 'taxonomy-model';
		wp_register_script(
			'term-row-model',
			ICL_PLUGIN_URL . '/res/js/taxonomy-translation/models/term-row.js',
			$core_dependencies,
			ICL_SITEPRESS_SCRIPT_VERSION
		);
		$dependencies[] = 'term-row-model';

		foreach ( array(
			'filter-view',
			'nav-view',
			'table-view',
			'taxonomy-view',
			'term-popup-view',
			'original-term-popup-view',
			'label-popup-view',
			'term-row-view',
			'label-row-view',
			'term-rows-view',
			'term-view',
			'term-original-view',
			'copy-all-popup-view',
		) as $script ) {

			wp_register_script(
				$script,
				ICL_PLUGIN_URL . '/res/js/taxonomy-translation/views/' . $script . '.js',
				$core_dependencies,
				ICL_SITEPRESS_SCRIPT_VERSION
			);
			$dependencies[] = $script;
		}

		wp_localize_script( 'main-model', 'labels', self::get_strings_translation_array() );
		wp_localize_script( 'main-model', 'wpml_taxonomies', self::wpml_get_table_taxonomies( $sitepress ) );

		$need_enqueue   = $dependencies;
		$need_enqueue[] = 'main-model';
		$need_enqueue[] = 'main-util';
		$need_enqueue[] = 'templates';

		foreach ( $need_enqueue as $handle ) {
			wp_enqueue_script( $handle );
		}

		wp_register_script( 'taxonomy-hierarchy-sync-message', ICL_PLUGIN_URL . '/res/js/taxonomy-hierarchy-sync-message.js', array( 'jquery' ), ICL_SITEPRESS_SCRIPT_VERSION );
		wp_enqueue_script( 'taxonomy-hierarchy-sync-message' );

	}

	public static function wpml_get_table_taxonomies( SitePress $sitepress ) {
		$taxonomies = $sitepress->get_wp_api()->get_taxonomies( array(), 'objects' );

		$result = array(
			'taxonomies'      => array(),
			'activeLanguages' => array(),
			'allLanguages'    => array(),
		);
		$sitepress->set_admin_language();
		$active_langs = $sitepress->get_active_languages();
		$default_lang = $sitepress->get_default_language();

		$result['activeLanguages'][ $default_lang ] = array(
			'label' => esc_js( $active_langs[ $default_lang ]['display_name'] ),
			'flag'  => esc_url( $sitepress->get_flag_url( $default_lang ) ),
		);
		foreach ( $active_langs as $code => $lang ) {
			if ( $code !== $default_lang ) {
				$result['activeLanguages'][ $code ] = array(
					'label' => esc_js( $lang['display_name'] ),
					'flag'  => esc_url( $sitepress->get_flag_url( $code ) ),
				);
			}
		}

		$all_languages = $sitepress->get_languages();
		foreach ( $all_languages as $code => $lang ) {
			$result['allLanguages'][ $code ] = array(
				'label' => esc_js( $lang['display_name'] ),
				'flag'  => esc_url( $sitepress->get_flag_url( $code ) ),
			);
		}

		foreach ( $result['activeLanguages'] as $code => $lang_data ) {
			if ( ! isset( $result['allLanguages'][ $code ] ) ) {
				$result['allLanguages'][ $code ] = $lang_data;
			}
		}

		foreach ( $taxonomies as $key => $tax ) {
			if ( $sitepress->is_translated_taxonomy( $key ) ) {
				$result['taxonomies'][ $key ] = array(
					'label'         => $tax->label,
					'singularLabel' => $tax->labels->singular_name,
					'hierarchical'  => $tax->hierarchical,
					'name'          => $key,
				);
			}
		}

		return $result;
	}

	public static function wpml_get_terms_and_labels_for_taxonomy_table() {
		global $sitepress;

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpml_taxonomy_translation_nonce' ) ) {
			/* translators: Error message returned when the taxonomy translation screen sends a request that the site cannot verify. */
			wp_send_json_error( __( 'Wrong nonce', 'sitepress' ) );
			return;
		}

		$taxonomy = false;

		$request_post_taxonomy = filter_input(
			INPUT_POST,
			'taxonomy',
			FILTER_SANITIZE_FULL_SPECIAL_CHARS,
			FILTER_NULL_ON_FAILURE
		);
		if ( $request_post_taxonomy ) {
			$taxonomy = html_entity_decode( $request_post_taxonomy );
		}

		if ( $taxonomy ) {
			$terms_data     = new WPML_Taxonomy_Translation_Screen_Data( $sitepress, $taxonomy );
			$term_results   = $terms_data->terms();
			$term_results['terms'] = self::add_in_progress_status( $term_results['terms'], $taxonomy );
			$labels         = apply_filters( 'wpml_label_translation_data', false, $taxonomy );
			$def_lang       = $sitepress->get_default_language();
			$bottom_content = apply_filters( 'wpml_taxonomy_translation_bottom', $html = '', $taxonomy, get_taxonomy( $taxonomy ) );
			wp_send_json(
				array(
					'terms'                => $term_results['terms'],
					'taxLabelTranslations' => $labels,
					'defaultLanguage'      => $def_lang,
					'bottomContent'        => $bottom_content,
					'taxLangSelector'      => self::render_tax_language_selector( $labels, $taxonomy ),
				)
			);
		} else {
			wp_send_json_error();
		}
	}

	private static function add_in_progress_status( $terms, $taxonomy ) {
		$in_progress_map  = self::get_in_progress_map( $taxonomy );
		$needs_update_map = self::get_needs_update_map( $taxonomy );

		foreach ( $terms as &$trid_group ) {
			$trid = isset( $trid_group['trid'] ) ? (int) $trid_group['trid'] : 0;

			$trid_group['inProgress']  = ( $trid && isset( $in_progress_map[ $trid ] ) )
				? $in_progress_map[ $trid ]
				: array();
			$trid_group['needsUpdate'] = ( $trid && isset( $needs_update_map[ $trid ] ) )
				? $needs_update_map[ $trid ]
				: array();
		}
		unset( $trid_group );

		return $terms;
	}

	private static function get_in_progress_map( $taxonomy ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT t.trid, t.language_code
				FROM {$wpdb->prefix}icl_translations t
				INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = t.translation_id
				INNER JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = ts.rid AND j.translated = 0
				WHERE t.element_type = %s AND j.editor = %s AND ts.status IN ( %d, %d )",
				'tax_' . $taxonomy,
				\WPML_TM_Editors::ATE,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS
			)
		);

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->trid ][ $row->language_code ] = true;
		}

		return $map;
	}

	private static function get_needs_update_map( $taxonomy ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT t.trid, t.language_code
				FROM {$wpdb->prefix}icl_translations t
				INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = t.translation_id
				LEFT JOIN {$wpdb->prefix}icl_translate_job j ON j.rid = ts.rid AND j.translated = 0
				WHERE t.element_type = %s AND ts.needs_update = 1 AND j.rid IS NULL",
				'tax_' . $taxonomy
			)
		);

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->trid ][ $row->language_code ] = true;
		}

		return $map;
	}

	private static function render_tax_language_selector( $labels, $taxonomy ) {
		global $sitepress;

		if ( ! isset( $labels['st_default_lang'] ) ) {
			return null;
		}

		$args = array(
			'selected'           => $labels['st_default_lang'],
			'name'               => 'string_lang[' . $taxonomy . ']',
			'show_please_select' => false,
			'echo'               => false,
			'class'              => 'js-tax-lang-selector',
		);

		$all    = (array) $sitepress->get_languages( $sitepress->get_admin_language() );
		$keep   = \WPML\Language\RequestedLanguage::configured();
		$keep[] = (string) $labels['st_default_lang'];

		$args['languages'] = array_intersect_key( $all, array_flip( $keep ) );

		$stored = (string) $labels['st_default_lang'];

		if ( ! isset( $args['languages'][ $stored ] ) ) {
			$args['languages'][ $stored ] = array(
				'code'         => $stored,
				'display_name' => $stored,
			);
		}

		$lang_selector = new WPML_Simple_Language_Selector( $sitepress );
		return $lang_selector->render( $args );
	}
}
