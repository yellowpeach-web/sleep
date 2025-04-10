<?php
namespace YPTheme;

class AdminSettings
{
    public static function init()
    {
        // Disable avatars
        add_filter('option_show_avatars', '__return_false');

        // Remove admin bar for administrators
        if (current_user_can('administrator')) {
            add_filter('show_admin_bar', '__return_false');
        }

        add_action('admin_init', [self::class, 'modify_user_capabilities']);
        add_filter('redirection_role', fn($role) => 'edit_posts');
        add_filter('admin_footer_text', [self::class, 'branded_wp_admin_footer']);
        add_action('admin_menu', [self::class, 'disable_admin_footer_wp_version']);
        add_filter('login_errors', fn() => 'Incorrect login details.');
        add_action('login_head', fn() => remove_action('login_head', 'wp_shake_js', 12));
        add_action('wp_dashboard_setup', [self::class, 'modify_admin_dashboard_widgets']);
    }



    public static function modify_user_capabilities () {
        $role = get_role('editor');
        if ($role) {
            $role->add_cap('gravityforms_create_form');
            $role->add_cap('gravityforms_delete_forms');
            $role->add_cap('gravityforms_edit_forms');
            $role->add_cap('gravityforms_preview_forms');
            $role->add_cap('gravityforms_view_entries');
            $role->add_cap('gravityforms_edit_entries');
            $role->add_cap('gravityforms_delete_entries');
            $role->add_cap('gravityforms_view_entry_notes');
            $role->add_cap('gravityforms_edit_entry_notes');
            $role->add_cap('gravityforms_export_entries');
        }
    }

    public static function branded_wp_admin_footer()
    {
        return '<span id="footer-thankyou">Developed by <a class="yp-copyright" href="https://yellowpeach.co.uk" target="_blank">Yellow Peach</a> for ' . get_bloginfo('name') . ' </span>';
    }


    public static function disable_admin_footer_wp_version()
    {
        remove_filter('update_footer', 'core_update_footer');
    }


    public static function modify_admin_dashboard_widgets()
    {
        global $wp_meta_boxes;
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_activity']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);
        unset($wp_meta_boxes['welcome_panel']);

        wp_add_dashboard_widget('yp-dashboard-widget', 'Need Support?', [self::class, 'yp_dashboard_help_widget']);

    }

    public static function yp_dashboard_help_widget()
    {
        echo '<img src="' . get_template_directory_uri() . '/resources/images/yellow-peach-logo.png" style="max-width: 120px; display: block; margin: 0 0 20px;"/>';
        echo '<p class="admin-yp-logo" style="font-size:15px;">If you require any assistance with your site or would like to see what else we can do for you, head over to <a href="https://yellowpeach.co.uk/contact-us/" style="color:#ffe0c9" target="_blank">our contact page</a></p>';
    }
}