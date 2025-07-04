<?php

namespace YPTheme;

class HelperFunctions
{
    public static function init()
    {
        add_filter('timber/twig', [self::class, 'register_twig_filters']);
        // Initialization logic if needed
    }

    public static function register_twig_filters($twig)
    {
        //add wp translation abilty to twig files
        $twig->addFilter(new \Twig\TwigFilter('__', function ($text) {
            return __($text, 'sleep');
        }));

        return $twig;
    }

    public static function theme_error_handling($error, $backtrace)
    {
        $called_from = $backtrace[0]['file'];
        $line = $backtrace[0]['line'];
        throw new \Exception("$error error in $called_from line $line");
    }

    public static function get_link_attr($obj, $include_title = false)
    {
        if ($obj instanceof \Timber\Post) {
            $link = $obj->link();
            $target = 'target="_self"';
            $title = $include_title ? 'title="Read: ' . $obj->title . '"' : '';
            return 'href="' . $link . '" ' . $title . ' ' . $target;
        } elseif ($obj instanceof \WP_Post) {
            $link = get_permalink($obj->ID);
            $target = 'target="_self"';
            $title = $include_title ? 'title="Read: ' . $obj->post_title . '"' : '';
            return 'href="' . $link . '" ' . $title . ' ' . $target;
        } elseif (is_array($obj) && isset($obj['url'])) {
            $link = $obj['url'];
            $target = !empty($obj['target']) ? 'target="' . $obj['target'] . '"' : 'target="_self"';
            $title = ($include_title && !empty($obj['title'])) ? 'title="' . $obj['title'] . '"' : '';
            return 'href="' . $link . '" ' . $title . ' ' . $target;
        }
        return '';
    }


    public static function get_general_settings()
    {

        if (! class_exists('ACF')) {
            return;
        }

        return [
            'company_name' => get_field('company_name', 'options') ? get_field('company_name', 'options') : '',
            'email_address' => get_field('email_address', 'options') ? get_field('email_address', 'options') : '',
            'location' => get_field('location', 'options') ? get_field('location', 'options') : '',
            'facebook' => get_field('facebook_url', 'options') ? get_field('facebook_url', 'options') : '',
            'twitter' => get_field('twitter_url', 'options') ? get_field('twitter_url', 'options') : '',
            'instagram' => get_field('instagram_url', 'options') ? get_field('instagram_url', 'options') : '',
            'linkedin' => get_field('linkedin_url', 'options') ? get_field('linkedin_url', 'options') : '',
            'pinterest' => get_field('pinterest_url', 'options') ? get_field('pinterest_url', 'options') : '',
            'header_button' => get_field('header_button', 'options') ? get_field('header_button', 'options') : '',
            'footer_button' => get_field('footer_button', 'options') ? get_field('footer_button', 'options') : '',
            'left_column' => get_field('left_column', 'options') ? get_field('left_column', 'options') : '',
            'header_color' => get_field('header_bg_color', 'options') ? get_field('header_bg_color', 'options') : '',
            'announcement_bar' => get_field('announcement_bar', 'options') ? get_field('announcement_bar', 'options') : '',
        ];
    }

    public static function get_insight_fields()
    {
        if (! class_exists('ACF')) {
            return;
        }
        return [
            'heading' => get_field('insights_heading', 'options') ? get_field('insights_heading', 'options') : '',
            'content' => get_field('insights_content', 'options') ? get_field('insights_content', 'options') : '',
            'cta' => get_field('insights_cta', 'options') ? get_field('insights_cta', 'options') : '',
        ];
    }

    public static function get_media_fields()
    {
        if (! class_exists('ACF')) {
            return;
        }
        return [
            'heading' => get_field('media_entries_heading', 'options') ? get_field('media_entries_heading', 'options') : '',
            'content' => get_field('media_entries_content', 'options') ? get_field('media_entries_content', 'options') : '',
            'cta' => get_field('media_entries_cta', 'options') ? get_field('media_entries_cta', 'options') : '',
        ];
    }

    public static function get_paper_fields()
    {
        if (! class_exists('ACF')) {
            return;
        }
        return [
            'heading' => get_field('papers_heading', 'options') ? get_field('papers_heading', 'options') : '',
            'content' => get_field('papers_content', 'options') ? get_field('papers_content', 'options') : '',
            'cta' => get_field('papers_cta', 'options') ? get_field('papers_cta', 'options') : '',
        ];
    }


    public static function get_insight_single_fields()
    {
        if (! class_exists('ACF')) {
            return;
        }
        return [
            'cta' => get_field('single_cta', 'options') ? get_field('single_cta', 'options') : '',
            'subheading' => get_field('single_subheading', 'options') ? get_field('single_subheading', 'options') : '',
            'feed' => get_field('insights_feed', 'options') ? get_field('insights_feed', 'options') : '',
            'gravity_cta' => get_field('insight_single_cta', 'options') ? get_field('insight_single_cta', 'options') : '',
        ];
    }

    public static function get_media_single_fields()
    {
        if (! class_exists('ACF')) {
            return;
        }
        return [
            'cta' => get_field('media_single_cta', 'options') ? get_field('media_single_cta', 'options') : '',
            'subheading' => get_field('media_single_subheading', 'options') ? get_field('media_single_subheading', 'options') : '',
            'feed' => get_field('media_feed', 'options') ? get_field('media_feed', 'options') : '',
            'gravity_cta' => get_field('media_single_cta_gravity', 'options') ? get_field('media_single_cta_gravity', 'options') : '',
        ];
    }




    public static function get_breadcrumb()
    {
        return function_exists('yoast_breadcrumb') ? yoast_breadcrumb('<p class="mb-0" id="breadcrumbs">', '</p>', false) : '';
    }

    public static function register_custom_post_type($args): void
    {
        $required_keys = ['name', 'slug', 'singular_name', 'plural_name', 'icon'];
        foreach ($required_keys as $key) {
            if (empty($args[$key])) {
                self::theme_error_handling("Custom post type requires the argument '$key'", debug_backtrace());
            }
        }

        $labels = array(
            'name' => __($args['name'], 'timber-starter'),
            'singular_name' => __($args['singular_name'], 'timber-starter'),
            'menu_name' => __($args['menu_name'] ?? $args['name'], 'timber-starter'),
            'name_admin_bar' => __($args['name_admin_bar'] ?? $args['name'], 'timber-starter'),
            'archives' => __($args['singular_name'] . ' Archives', 'timber-starter'),
            'all_items' => __('All ' . $args['plural_name'], 'timber-starter'),
            'add_new_item' => __('Add New ' . $args['singular_name'], 'timber-starter'),
            'edit_item' => __('Edit ' . $args['singular_name'], 'timber-starter'),
            'view_item' => __('View ' . $args['singular_name'], 'timber-starter'),
            'search_items' => __('Search ' . $args['singular_name'], 'timber-starter'),
            'not_found' => __('Not found', 'timber-starter'),
        );

        $post_args = array(
            'label' => __($args['singular_name'], 'timber-starter'),
            'labels' => $labels,
            'menu_icon' => $args['icon'],
            'public' => $args['public'] ?? true,
            'show_ui' => $args['show_ui'] ?? true,
            'supports' => $args['supports'] ?? [],
            'has_archive' => $args['has_archive'] ?? true,
            'show_in_rest' => $args['show_in_rest'] ?? false,
            // ✅ Add this line:
            'rewrite' => $args['rewrite'] ?? ['slug' => $args['slug']],
        );

        register_post_type($args['slug'], $post_args);
    }

}
