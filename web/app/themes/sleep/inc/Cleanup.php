<?php
namespace YPTheme;

class Cleanup
{
    public static function init()
    {
        add_action('template_redirect', [self::class, 'disable_author_pages_404']);
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
}