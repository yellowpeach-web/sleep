<?php

namespace YPTheme;

class GutenbergBlocks
{
    public static function init()
    {
        add_filter('allowed_block_types_all', [self::class, 'allow_custom_gutenberg_blocks'], 10, 2);
        add_filter('block_categories_all', [self::class, 'allow_custom_block_category'], 1, 2);
        remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
        add_filter('block_editor_settings_all', [self::class, 'modify_block_editor_settings'], 10, 2);
        add_filter('use_block_editor_for_post_type', [self::class, 'disable_gutenberg_editor_by_post_type'], 10, 2);
    }

    public static function allow_custom_gutenberg_blocks($allowed_block_types, $editor_context)
    {
        $files = glob(get_template_directory() . "/views/blocks/*.*");
        $sub_directory_files = glob(get_template_directory() . "/views/blocks/**/*.twig");
        $all_files = array_merge($files, $sub_directory_files);
        $files_name = [];

        foreach ($all_files as $file) {
            $file = pathinfo($file);
            $files_name[] = "acf/" . $file['filename'];
        }

        if (!empty($editor_context->post)) {
            return $files_name;
        }

        return $allowed_block_types;
    }

    public static function allow_custom_block_category($categories, $post)
    {
        return array_merge([
            [
                'slug' => 'yellow-peach',
                'title' => get_bloginfo('name') . ' Blocks',
                'icon' => 'admin-appearance',
            ]
        ], $categories);
    }

    public static function modify_block_editor_settings($editor_settings, $editor_context)
    {
        $editor_settings['blockInspectorTabs'] = ['default' => false];
        unset($editor_settings['styles']);

        if (!empty($editor_context->post)) {
            unset($editor_settings['defaultEditorStyles'][0]);
        }

        return $editor_settings;
    }

    public static function disable_gutenberg_editor_by_post_type($can_edit, $post_type)
    {
        $use_classic = ['post', 'faq', 'testimonial', 'team', 'news_media', 'papers'];

        return in_array($post_type, $use_classic, true) ? false : $can_edit;
    }

    public static function get_block_spacing($spacing): string
    {
        if (str_contains($spacing['padding']['top'], 'px')) {
            return "section-wrapper padding-top-regular padding-bottom-regular";
        }

        $padding_top = isset($spacing['padding']['top']) ? 'padding-top-' . explode('|', $spacing['padding']['top'])[2] : '';
        $padding_bottom = isset($spacing['padding']['bottom']) ? 'padding-bottom-' . explode('|', $spacing['padding']['bottom'])[2] : '';

        return "section-wrapper $padding_top $padding_bottom";
    }
}
