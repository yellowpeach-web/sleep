<?php

add_action('init', function () {
    \YPTheme\HelperFunctions::register_custom_post_type([
        'name' => 'Media Entries',
        'slug' => 'media-entries',
        'singular_name' => 'Media Entry',
        'plural_name' => 'Media Entries',
        'icon' => 'dashicons-portfolio',
        'has_archive' => true,
        'public' =>  true,
        'rewrite' => ['slug' => 'media-entries'],
        'supports' => ['title', 'editor', 'thumbnail'],
        'show_in_rest' => true
    ]);
    register_taxonomy('media_type', 'media-entries', [
        'labels' => [
            'name' => 'Media Category',
        ],
        'hierarchical' => true,
        'show_in_rest' => true,
        'public' => true,
    ]);
}, 0);
