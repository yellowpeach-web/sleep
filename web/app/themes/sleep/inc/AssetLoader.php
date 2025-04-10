<?php
namespace YPTheme;

class AssetLoader
{
    public static function init()
    {
        add_action('wp_footer', [self::class, 'remove_wp_embed']);
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        add_action('wp_default_scripts', [self::class, 'remove_jquery_migrate']);
        add_action('wp_footer', [self::class, 'remove_core_block_support_styles']);
        add_action('wp_enqueue_scripts', [self::class, 'dequeue_block_styles'], 100);

        add_action('wp_enqueue_scripts', [self::class, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('admin_head', [self::class, 'add_inline_styles']);
    }

    public static function enqueue_frontend_assets()
    {
        $theme = wp_get_theme();
        wp_enqueue_script('jquery');
        wp_enqueue_style('app', self::get_mix_compiled_asset_url('build/css/app.css'), [], $theme->get('Version'));
        wp_enqueue_script('app', self::get_mix_compiled_asset_url('build/js/app.js'), ['jquery'], $theme->get('Version'));
    }

    public static function enqueue_admin_assets($hook)
    {
        if (in_array($hook, ['post.php', 'post-new.php'])) {
            $theme = wp_get_theme();
            wp_enqueue_style('yp-editor-style', self::get_mix_compiled_asset_url('build/css/admin/editor-style.css'), [], $theme->get('Version'));
            wp_enqueue_style('yp-gutenberg-style', self::get_mix_compiled_asset_url('build/css/admin/gutenberg-style.css'), [], $theme->get('Version'));
        }
    }

    public static function add_inline_styles()
    {
        echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;700&display=swap" type="text/css">';
        echo '<style>
            .wp-block.wp-block-post-title {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                margin-bottom: 0.8em !important;
                font-size: 2.5em;
                font-weight: 800;
            }
            #yp-dashboard-widget .inside {
                background:#151515;
                color:white;
                margin-top:0;
                padding:30px;        
            }
        </style>';
    }

    public static function get_mix_compiled_asset_url($path)
    {
        $path = '/' . $path;
        $stylesheet_dir_uri = get_stylesheet_directory_uri();
        $stylesheet_dir_path = get_stylesheet_directory();

        if (!file_exists($stylesheet_dir_path . '/mix-manifest.json')) {
            return $stylesheet_dir_uri . $path;
        }

        $manifest = json_decode(file_get_contents($stylesheet_dir_path . '/mix-manifest.json'), true);
        $asset_path = $manifest[$path] ?? $path;
        return $stylesheet_dir_uri . $asset_path;
    }


    public static function remove_wp_embed()
    {
        wp_deregister_script('wp-embed');
    }

    public static function dequeue_block_styles()
    {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-block-style');
        wp_dequeue_style('global-styles');
    }

    public static function remove_jquery_migrate($scripts)
    {
        if (!is_admin() && isset($scripts->registered['jquery'])) {
            $script = $scripts->registered['jquery'];
            if ($script->deps) {
                $script->deps = array_diff($script->deps, ['jquery-migrate']);
            }
        }
    }

    public static function remove_core_block_support_styles()
    {
        wp_dequeue_style('core-block-supports');
    }



}