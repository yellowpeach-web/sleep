<?php

add_action('init', function () {
    \YPTheme\HelperFunctions::register_custom_post_type([
        'name' => 'Team',
        'slug' => 'team',
        'public' => false,
        'show_ui' => true,
        'has_archive' => false,
        'singular_name' => 'Team member',
        'plural_name' => 'Team',
        'icon' => 'dashicons-groups',
        'supports'  => ['title', 'editor', 'thumbnail'],
        'show_in_rest' => true,
    ]);

    // Register the 'team' taxonomy
    register_taxonomy('department', 'team', [
        'labels' => [
            'name' => 'Department',
            'singular_name' => 'Department',
        ],
        'hierarchical' => true,
        'show_in_rest' => true,
        'public' => true,
    ]);
}, 0);
