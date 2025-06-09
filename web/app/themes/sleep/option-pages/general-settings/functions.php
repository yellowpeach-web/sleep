<?php
add_action('acf/init', function () {
    if (function_exists('acf_add_options_page')) {
        acf_add_options_page(array(
            'page_title' => 'General Settings',
            'menu_title' => 'General Settings',
            'menu_slug' => 'general-settings',
            'capability' => 'edit_posts',
            'parent_slug' => '',
            'position' => false,
            'icon_url' => false,
            'redirect' => false
        ));
    }
});
