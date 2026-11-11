<?php

namespace YPTheme;

class CampaignLanding
{
    public static function init()
    {
        add_action('init', [self::class, 'maybe_serve'], 0);
    }

    public static function maybe_serve()
    {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || (defined('WP_CLI') && WP_CLI)) {
            return;
        }

        $request_path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        $home = defined('WP_HOME') ? WP_HOME : get_option('home');
        $home_path = trim((string) parse_url($home, PHP_URL_PATH), '/');

        if ($home_path !== '' && str_starts_with($request_path, $home_path . '/')) {
            $request_path = substr($request_path, strlen($home_path) + 1);
        }

        if ($request_path !== 'de/kampagne') {
            return;
        }

        $file = get_template_directory() . '/landing/dein-schlaf.html';
        if (!is_readable($file)) {
            return;
        }

        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');
        readfile($file);
        exit;
    }
}
