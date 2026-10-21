<?php

namespace WPML\UserInterface\Web\Core\Component\Settings\Application;

class SearchIndex {


  public function getIndex(): array {
    return $this->getDefaultSections();
  }


  private function getDefaultSections(): array {
    return array(
      array(
        /* translators: Name of the Languages section of WPML → Settings: its entry in the settings search list and its heading. */
        'section'     => __( 'Languages', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=languages',
        'icon'        => 'languages',
        'capability'  => 'wpml_manage_languages',
        'description' => __( 'Site languages, hide languages, language mapping for translation engines.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Manage and Hide Site Languages', 'wpml' ),
            'anchor' => '',
          ),
          array(
            'label'  => __( 'Change default language', 'wpml' ),
            'anchor' => 'lang-sec-1',
            'target' => 'icl_change_default_button',
          ),
          array(
            'label'  => __( 'Add / Remove languages', 'wpml' ),
            'anchor' => 'lang-sec-1',
            'target' => 'icl_add_remove_button',
          ),
        ),
      ),
      array(
        /* translators: Name of the Language Switchers section of WPML → Settings: its entry in the settings search list and its heading. */
        'section'     => __( 'Language Switchers', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=language-switchers',
        'icon'        => 'language-switchers',
        'capability'  => 'wpml_manage_languages',
        'description' => __( 'Menu, widget, footer language switchers, switcher options and ordering.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Menu language switcher', 'wpml' ),
            'anchor' => 'wpml-language-switcher-menus',
          ),
          array(
            'label'  => __( 'Widget language switcher', 'wpml' ),
            'anchor' => 'wpml-language-switcher-sidebars',
          ),
          array(
            'label'  => __( 'Footer language switcher', 'wpml' ),
            'anchor' => 'wpml-language-switcher-footer',
          ),
          array(
            'label'  => __( 'Default flag format', 'wpml' ),
            'anchor' => 'lang-sec-2-1',
          ),
          array(
            'label'  => __( 'Language switcher options', 'wpml' ),
            'anchor' => 'wpml-language-switcher-options',
          ),
          array(
            'label'  => __( 'Order of languages', 'wpml' ),
            'anchor' => 'wpml-language-switcher-options',
          ),
          array(
            'label'  => __( 'How to handle languages without translation', 'wpml' ),
            'anchor' => 'wpml-language-switcher-options',
          ),
          array(
            'label'  => __( 'Skip language', 'wpml' ),
            'anchor' => 'wpml-language-switcher-options',
          ),
          array(
            'label'  => __( 'Link to home of language for missing translations', 'wpml' ),
            'anchor' => 'wpml-language-switcher-options',
          ),
          array(
            'label'  => __( 'Preserve URL arguments', 'wpml' ),
            'anchor' => 'wpml-language-switcher-options',
          ),
          array(
            'label'  => __( 'Additional CSS', 'wpml' ),
            'anchor' => 'wpml-language-switcher-options',
          ),
          array(
            'label'  => __( 'Backwards compatibility', 'wpml' ),
            'anchor' => 'wpml-language-switcher-options',
          ),
          array(
            'label'  => __( 'Skip backwards compatibility', 'wpml' ),
            'anchor' => 'wpml-language-switcher-options',
          ),
          array(
            'label'  => __( 'Show language switcher in footer', 'wpml' ),
            'anchor' => 'wpml-language-switcher-footer',
          ),
          array(
            'label'  => __( 'Links to translation of posts', 'wpml' ),
            'anchor' => 'wpml-language-switcher-post-translations',
          ),
          array(
            'label'  => __( 'Show links above or below posts, offering them in other languages', 'wpml' ),
            'anchor' => 'wpml-language-switcher-post-translations',
          ),
          array(
            'label'  => __( 'Custom language switchers', 'wpml' ),
            'anchor' => 'wpml-language-switcher-shortcode-action',
          ),
          array(
            'label'  => __( 'Enable', 'wpml' ),
            'anchor' => 'wpml-language-switcher-shortcode-action',
          ),
          array(
            'label'  => __( 'Choose the image file type for flags', 'wpml' ),
            'anchor' => 'lang-sec-2-1',
          ),
          array(
            'label'  => __( 'PNG format', 'wpml' ),
            'anchor' => 'lang-sec-2-1',
          ),
          array(
            'label'  => __( 'SVG format', 'wpml' ),
            'anchor' => 'lang-sec-2-1',
          ),
        ),
      ),
      array(
        'section'     => __( 'URLs and SEO', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=urls-and-seo',
        'icon'        => 'urls-and-seo',
        'capability'  => 'wpml_manage_languages',
        'description' => __( 'Language URL format, page URL, slug translations, SEO, browser redirect.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Language URL format', 'wpml' ),
            'anchor' => 'lang-sec-2',
          ),
          array(
            /* translators: Name of the Page URL section of WPML → Settings: its entry in the settings search list, its heading, and a row label in the worked example under it. */
            'label'  => __( 'Page URL', 'wpml' ),
            'anchor' => 'page-url',
          ),
          array(
            /* translators: Entry in the settings search list of WPML → Settings, pointing at the Slug translations block. A slug is the part of a web address that names one page. */
            'label'  => __( 'Slug translations', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-4',
          ),
          array(
            /* translators: Entry in the settings search list of WPML → Settings, pointing at the SEO options block. */
            'label'  => __( 'SEO Options', 'wpml' ),
            'anchor' => 'lang-sec-9-5',
          ),
          array(
            'label'  => __( 'Browser language redirect', 'wpml' ),
            'anchor' => 'lang-sec-9',
          ),
          array(
            'label'  => __( 'Choose how to determine which language visitors see contents in', 'wpml' ),
            'anchor' => 'lang-sec-2',
          ),
          array(
            'label'  => __( 'Different languages in directories', 'wpml' ),
            'anchor' => 'lang-sec-2',
            'target' => 'icl_language_negotiation_type_1',
          ),
          array(
            'label'  => __( 'Use directory for default language', 'wpml' ),
            'anchor' => 'lang-sec-2',
            'target' => 'icl_use_directory',
          ),
          array(
            'label'  => __( 'A different domain per language', 'wpml' ),
            'anchor' => 'lang-sec-2',
            'target' => 'icl_lnt_domains',
          ),
          array(
            'label'  => __( 'Language name added as a parameter', 'wpml' ),
            'anchor' => 'lang-sec-2',
          ),
          array(
            'label'  => __( 'Auto-generate once from the title (WordPress default)', 'wpml' ),
            'anchor' => 'page-url',
            'target' => 'icl_translated_document_page_url',
          ),
          array(
            'label'  => __( 'Always auto-generate from the title', 'wpml' ),
            'anchor' => 'page-url',
            'target' => 'icl_translated_document_page_url',
          ),
          array(
            'label'  => __( 'Translate the slug in the translation editor', 'wpml' ),
            'anchor' => 'page-url',
            'target' => 'icl_translated_document_page_url',
          ),
          array(
            'label'  => __( 'Copy the original slug for languages with encoded URLs', 'wpml' ),
            'anchor' => 'page-url',
            'target' => 'icl_translated_document_page_url',
          ),
          array(
            'label'  => __( 'Translate base slugs of custom post types and taxonomies', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-4',
          ),
          array(
            'label'  => __( 'Display alternative languages in the HEAD section.', 'wpml' ),
            'anchor' => 'lang-sec-9-5',
          ),
          array(
            'label'  => __( 'Position of hreflang links', 'wpml' ),
            'anchor' => 'lang-sec-9-5',
          ),
          array(
            'label'  => __( 'Disable browser language redirect', 'wpml' ),
            'anchor' => 'lang-sec-9',
          ),
          array(
            'label'  => __( 'Redirect visitors based on browser language only if translations exist', 'wpml' ),
            'anchor' => 'lang-sec-9',
          ),
          array(
            'label'  => __( 'Always redirect visitors based on browser language (redirect to home page if translations are missing)', 'wpml' ),
            'anchor' => 'lang-sec-9',
          ),
          array(
            'label'  => __( "Remember visitors' language preference for hours", 'wpml' ),
            'anchor' => 'lang-sec-9',
            'target' => 'icl_remember_language',
          ),
        ),
      ),
      array(
        /* translators: Name of the AI Translation section of WPML → Settings: its entry in the settings search list and its heading. */
        'section'     => __( 'AI Translation', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=ai-translation',
        'icon'        => 'ai-translation',
        'description' => __( 'Translation engine, automatic translation behavior, access control.', 'wpml' ),
        'subs'        => array(),
      ),
      array(
        /* translators: Name of the Translation Editor section of WPML → Settings: its entry in the settings search list and its heading. */
        'section'     => __( 'Translation Editor', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=translation-editor',
        'icon'        => 'translation-editor',
        'description' => __( 'Advanced and Classic Translation Editor, glossary, memory, spell checker.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Choose translation editor', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-1',
          ),
          array(
            'label'  => __( 'Advanced Translation Editor', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-1',
          ),
          array(
            'label'  => __( 'Classic Translation Editor', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-1',
          ),
          array(
            'label'  => __( 'Use also for old translations created with the classic editor', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-1',
            'target' => 'wpml-old-jobs-editor',
          ),
        ),
      ),
      array(
        /* translators: Name of the Translators section of WPML → Settings: its entry in the settings search list and its heading. The people who translate the site. */
        'section'     => __( 'Translators', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=translators',
        'icon'        => 'translators',
        'description' => __( 'Local translators, translation managers, notifications, translation services.', 'wpml' ),
        'subs'        => array(
          array(
            /* translators: Entry in the settings search list of WPML → Settings, pointing at the block for translators who are users of this site. */
            'label'  => __( 'Local translators', 'wpml' ),
            'anchor' => 'wpml-translation-roles-ui-container',
          ),
          array(
            /* translators: Entry in the settings search list of WPML → Settings, pointing at the block about e-mails sent to translators. */
            'label'  => __( 'Translation Notifications', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Translation pickup mode', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-5',
          ),
          array(
            'label'  => __( 'XLIFF file options', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-5-1',
          ),
          array(
            'label'  => __( 'Translation Services', 'wpml' ),
            'anchor' => 'translation-services',
          ),
          array(
            'label'  => __( 'Notifications', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Notification emails to translators', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Notify translators when new jobs are waiting for them', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Limit number of jobs included in the email to', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Include XLIFF files in the notification emails', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Notify translators when jobs are removed from their queue', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Notification emails to the translation manager', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Send the translation manager a completed-jobs summary', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Notify the translation manager when a translator resigns from a job', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Notify the translation manager when the translation service updates or cancels a job', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'Notify the translation manager when jobs are late by 7 days', 'wpml' ),
            'anchor' => 'translation-notifications',
          ),
          array(
            'label'  => __( 'XLIFF version', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-5-1',
          ),
          array(
            'label'  => __( 'Choose default format for XLIFF file:', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-5-1',
          ),
          array(
            'label'  => __( 'New lines character', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-5-1',
          ),
          array(
            'label'  => __( 'Do nothing - all new line characters will stay untouched.', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-5-1',
          ),
          array(
            'label'  => __( 'All new lines should be replaced by HTML element <br class="xliff-newline" />.', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-5-1',
          ),
        ),
      ),
      array(
        'section'     => __( 'Posts and Pages Synchronization', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=posts-pages-sync',
        'icon'        => 'posts-pages-sync',
        'capability'  => 'wpml_manage_languages',
        'description' => __( 'How original posts and pages stay in sync with their translations.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Posts and pages synchronization', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
          ),
          array(
            'label'  => __( 'Synchronize page order for translations', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_page_ordering',
          ),
          array(
            'label'  => __( 'Set page parent for translation according to page parent of the original language', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_page_parent',
          ),
          array(
            'label'  => __( 'Synchronize page template', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_page_template',
          ),
          array(
            'label'  => __( 'Synchronize comment status', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'keywords' => array( __( 'Sync comment status', 'wpml' ) ),
            'target' => 'icl_sync_comment_status',
          ),
          array(
            'label'  => __( 'Synchronize ping status', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_ping_status',
          ),
          array(
            'label'  => __( 'Synchronize sticky flag', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_sticky_flag',
          ),
          array(
            'label'  => __( 'Synchronize password for password protected posts', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_password',
          ),
          array(
            'label'  => __( 'Synchronize private flag', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_private_flag',
          ),
          array(
            'label'  => __( 'Synchronize posts format', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_post_format',
          ),
          array(
            'label'  => __( 'Copy taxonomy to translations', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_post_taxonomies',
          ),
          array(
            'label'  => __( 'Copy publishing date to translations', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_post_date',
          ),
          array(
            'label'  => __( 'Synchronize comments on duplicate content', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'icl_sync_comments_on_duplicates',
          ),
          array(
            'label'  => __( 'Page builders options', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
          ),
          array(
            'label'  => __( 'Send to translation the content of raw HTML cells', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-2',
            'target' => 'wpml_pb_translate_raw_html',
          ),
        ),
      ),
      array(
        /* translators: Name of the Deleting content section of WPML → Settings: its entry in the settings search list, its sub-entry and its heading. */
        'section'     => __( 'Deleting content', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=deleting-content',
        'icon'        => 'deleting-content',
        'capability'  => 'wpml_manage_languages',
        'description' => __( 'What happens to the other languages when you delete a page, an image, a category or a menu.', 'wpml' ),
        'subs'        => array(
          array(
            /* translators: Name of the Deleting content section of WPML → Settings: its entry in the settings search list, its sub-entry and its heading. */
            'label'  => __( 'Deleting content', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Pages and posts', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Images', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Categories and tags', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Menus', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Patterns', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Navigation Menus', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Templates', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Deleting an original', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Deleting a translation', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Ask me', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Only that one', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
          array(
            'label'  => __( 'Delete all languages', 'wpml' ),
            'anchor' => 'wpml-deleting-content-container',
          ),
        ),
      ),
      array(
        'section'     => __( 'Translated Documents Options', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=translated-documents',
        'icon'        => 'translated-documents',
        'capability'  => 'wpml_manage_languages',
        'description' => __( 'The status of translations when you receive them and when the status of the original changes.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Translated documents options', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-3',
          ),
          array(
            'label'  => __( 'When you receive completed translations', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-3',
          ),
          array(
            'label'  => __( 'Publish the translated post when original is also published (default)', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-3',
            'target' => 'icl_translated_document_status',
          ),
          array(
            'label'  => __( 'Save the translated post as a draft', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-3',
            'target' => 'icl_translated_document_status',
          ),
          array(
            'label'  => __( 'When you change the status of the original post', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-3',
          ),
          array(
            'label'  => __( 'Translations get the same status as the original', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-3',
            'target' => 'icl_translated_document_status_sync',
          ),
          array(
            'label'  => __( 'Translations keep their own status', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-3',
            'target' => 'icl_translated_document_status_sync',
          ),
          array(
            'label'  => __( 'Translated taxonomies', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-3',
          ),
          array(
            'label'  => __( 'Show translated taxonomies in Translation Editor', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-3',
            'target' => 'tm_block_retranslating_terms',
          ),
        ),
      ),
      array(
        'section'     => __( 'Post Types Translation', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=post-types',
        'icon'        => 'post-types',
        'description' => __( 'Choose which post types are translatable.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Custom posts synchronization options', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-7',
          ),
          array(
            'label'  => __( 'Post types', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-7',
          ),
          array(
            'label'  => __( 'Translatable', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-7',
          ),
          array(
            'label'  => __( 'Not translatable', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-7',
          ),
          array(
            'label'  => __( 'only show translated items', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-7',
          ),
          array(
            'label'  => __( 'use translation if available or fallback to default language', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-7',
          ),
        ),
      ),
      array(
        'section'     => __( 'Custom Fields Translation', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=custom-fields',
        'icon'        => 'custom-fields',
        'description' => __( 'Translation preferences for custom fields.', 'wpml' ),
        'subs'        => array(
          array(
            /* translators: Entry in the settings search list of WPML → Settings, pointing at the block about custom fields — the extra fields attached to a page or a post. */
            'label'  => __( 'Field translation preferences', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-cf',
          ),
        ),
      ),
      array(
        /* translators: Name of the Taxonomies Translation section of WPML → Settings: its entry in the settings search list, and link text pointing at it. */
        'section'     => __( 'Taxonomies Translation', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=taxonomies',
        'icon'        => 'taxonomies',
        'description' => __( 'Categories, tags, custom taxonomies.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Custom taxonomies synchronization options', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-8',
          ),
          array(
            'label'  => __( 'Taxonomy', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-8',
          ),
          array(
            'label'  => __( 'Translatable', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-8',
          ),
          array(
            'label'  => __( 'Not translatable', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-8',
          ),
          array(
            'label'  => __( 'only show translated items', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-8',
          ),
          array(
            'label'  => __( 'use translation if available or fallback to default language', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-8',
          ),
          array(
            'label'  => __( 'Show translated taxonomies in Translation Editor', 'wpml' ),
            'anchor' => 'tm_block_retranslating_terms_card',
            'target' => 'tm_block_retranslating_terms',
          ),
        ),
      ),
      array(
        'section'     => __( 'Custom Term Meta Translation', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=custom-term-meta',
        'icon'        => 'custom-term-meta',
        /* translators: Description of a section of WPML → Settings. A term is a category or a tag; term meta are the extra fields attached to it. */
        'description' => __( 'Term meta translation preferences.', 'wpml' ),
        'subs'        => array(
          array(
            /* translators: Entry in the settings search list of WPML → Settings, pointing at that same section. A term is a category or a tag; term meta are the extra fields attached to it. */
            'label'  => __( 'Term meta translation preferences', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-tcf',
          ),
        ),
      ),
      array(
        /* translators: Name of the Media Translation section of WPML → Settings: its entry in the settings search list and its heading. */
        'section'     => __( 'Media Translation', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=media',
        'icon'        => 'media',
        'description' => __( 'Image text detection, duplication, Media Library texts.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Automatically detect best options for translating image texts (alt, caption, title)', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-media',
            'target' => 'shouldhandlemediaauto',
          ),
          array(
            'label'  => __( 'Translate Media Library texts (alt, caption, title) when translating content', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-media',
            'target' => 'translate_media_library_texts',
          ),
          array(
            'label'  => __( 'Duplicate texts (alt, caption, title) for all media to all languages', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-media',
            'target' => 'translate_media',
          ),
          array(
            'label'  => __( 'Duplicate image texts (alt, caption, title) for all feature images to all languages', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-media',
            'target' => 'duplicate_featured',
          ),
          array(
            'label'  => __( 'How to handle Media Library texts (alt, caption, title)', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-media',
          ),
          array(
            'label'  => __( 'Start the process', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-media',
            'target' => 'set_defaults',
          ),
        ),
      ),
      array(
        /* translators: Name of the String Translation section of WPML → Settings: its entry in the settings search list and its heading. */
        'section'     => __( 'String Translation', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=string-translation',
        'icon'        => 'string-translation',
        'capability'  => 'manage_translations',
        'description' => __( 'Site-wide string discovery preferences. The full list of strings is available under WPML → Translations → String Translation.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Detect strings in JavaScript files', 'wpml' ),
            'anchor' => 'detect-js-strings',
          ),
        ),
      ),
      array(
        'section'     => __( 'Custom XML Configuration', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=custom-xml',
        'icon'        => 'custom-xml',
        'capability'  => 'wpml_manage_languages',
        /* translators: Description of a section of WPML → Settings. It is a noun phrase with no verb. wpml-config.xml is a file name and stays as it is. */
        'description' => __( 'wpml-config.xml override and remote XML config.', 'wpml' ),
        'subs'        => array(),
      ),
      array(
        /* translators: Name of the Compatibility section of WPML → Settings: its entry in the settings search list and its heading. */
        'section'     => __( 'Compatibility', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=compatibility',
        'icon'        => 'compatibility',
        'capability'  => 'wpml_manage_languages',
        'description' => __( 'Theme and plugin localization options, login pages, AJAX language filtering.', 'wpml' ),
        'subs'        => array(
          array(
            'label'  => __( 'Theme and plugins localization', 'wpml' ),
            'anchor' => 'localization-options',
          ),
          array(
            /* translators: Entry in the settings search list of WPML → Settings, pointing at the Localization options block. */
            'label'  => __( 'Localization options', 'wpml' ),
            'anchor' => 'localization-options',
          ),
          array(
            'label'  => __( "Automatically load the theme's .mo file using 'load_textdomain'", 'wpml' ),
            'anchor' => 'localization-options',
            'target' => 'theme_localization_load_textdomain',
          ),
          array(
            'label'  => __( 'Enter textdomain:', 'wpml' ),
            'anchor' => 'localization-options',
            'target' => 'gettext_theme_domain_name',
          ),
          array(
            'label'  => __( 'Use theme or plugin text domains when gettext calls do not use a string literal', 'wpml' ),
            'anchor' => 'localization-options',
          ),
          array(
            'label'  => __( 'Make themes work multilingual', 'wpml' ),
            'anchor' => 'lang-sec-8',
          ),
          array(
            'label'  => __( 'Adjust IDs for multilingual functionality', 'wpml' ),
            'anchor' => 'lang-sec-8',
            'target' => 'icl_adjust_ids',
          ),
          array(
            'label'  => __( 'Language filtering for AJAX operations', 'wpml' ),
            'anchor' => 'cookie',
          ),
          array(
            'label'  => __( 'Store a language cookie for visitors', 'wpml' ),
            'anchor' => 'cookie',
          ),
          array(
            'label'  => __( 'Login and registration pages', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-wp-login',
          ),
          array(
            'label'  => __( 'Allow translating the login and registration pages', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-wp-login',
            'target' => 'login_page_translation',
          ),
          array(
            'label'  => __( 'Show Language Switcher on login and registration pages', 'wpml' ),
            'anchor' => 'ml-content-setup-sec-wp-login',
            'target' => 'show_login_page_language_switcher',
          ),
        ),
      ),
      array(
        /* translators: Name of the Data sharing section of WPML → Settings: its entry in the settings search list and its heading. */
        'section'     => __( 'Data sharing', 'wpml' ),
        'href'        => 'admin.php?page=tm/menu/settings&section=data-sharing',
        'icon'        => 'data-sharing',
        'capability'  => 'wpml_manage_languages',
        /* translators: Description of a section of WPML → Settings. It is a noun phrase with no verb. */
        'description' => __( 'Plugin, theme and content-stats reporting to wpml.org.', 'wpml' ),
        'subs'        => array(
          array(
            /* translators: Heading of the opt-in in the Data sharing section of WPML → Settings: its entry in the settings search list. */
            'label'    => __( 'Information shared with wpml.org', 'wpml' ),
            'anchor'   => 'ml-content-setup-sec-reporting',
            'keywords' => array(
              __( 'data sharing', 'wpml' ),
              'wpml.org',
              __( 'statistics', 'wpml' ),
              __( 'plugins and themes', 'wpml' ),
              __( 'privacy', 'wpml' ),
            ),
          ),
        ),
      ),
    );
  }


}
