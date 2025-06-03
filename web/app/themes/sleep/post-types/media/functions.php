<?php

add_action('init', function () {
    \YPTheme\HelperFunctions::register_custom_post_type([
        'name' => 'Media Entries',
        'slug' => 'media-entry',
        'singular_name' => 'Media Entry',
        'plural_name' => 'Media Entries',
        'icon' => 'dashicons-portfolio',
        'supports' => ['title', 'editor'],
        'show_in_rest' => false
    ]);
    register_taxonomy('media_type', 'media-entry', [
        'labels' => [
            'name' => 'Media Category',
        ],
        'hierarchical' => true,
        'show_in_rest' => true,
        'public' => true,
    ]);
}, 0);
