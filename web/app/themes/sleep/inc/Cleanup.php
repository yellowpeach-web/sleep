<?php
namespace YPTheme;

class Cleanup
{
    public static function init()
    {
        add_action('template_redirect', [self::class, 'disable_author_pages_404']);
        add_action('template_redirect', [self::class, 'wpml_lang_redirect']);

    }


    public static function disable_author_pages_404()
    {
        if (is_author()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            include(get_template_directory() . '/404.php');
            exit;
        }
    }


    public static function wpml_lang_redirect()
    {
        if (is_admin() || defined('DOING_AJAX') && DOING_AJAX) {
            return; // Don't interfere with admin or AJAX requests
        }

        // Only trigger if 'lang' query parameter is set
        if (!empty($_GET['lang'])) {

            $lang = sanitize_text_field($_GET['lang']);

            $supported_langs = apply_filters('wpml_active_languages', []);

            if (!array_key_exists($lang, $supported_langs)) {
                return; // Invalid or unsupported language
            }


            $current_url = home_url($_SERVER['REQUEST_URI']);
            $parsed_url = wp_parse_url($current_url);

            parse_str($parsed_url['query'] ?? '', $query_args);
            unset($query_args['lang']);

            $path = ltrim($parsed_url['path'] ?? '', '/');

            // Prevent redirect loop if already in the new format
            if (preg_match('#^' . preg_quote($lang, '#') . '(/|$)#', $path)) {
                return;
            }

            // Assemble final URL
            $new_url = trailingslashit(home_url()) . trailingslashit($lang) . $path;
            
            if (!empty($query_args)) {
                $new_url = add_query_arg($query_args, $new_url);
            }

            wp_redirect($new_url, 301);
            exit;
        }

    }

}