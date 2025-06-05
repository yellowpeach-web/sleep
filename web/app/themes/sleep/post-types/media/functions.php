<?php

add_action('init', function () {
    \YPTheme\HelperFunctions::register_custom_post_type([
        'name' => 'Media Entries',
        'slug' => 'news_media',
        'singular_name' => 'Media Entry',
        'plural_name' => 'Media Entries',
        'icon' => 'dashicons-portfolio',
        'has_archive' => true,
        'public' =>  true,
        'rewrite' => array(
            'slug' => 'news',
            'with_front' => false,
        ),
        'supports' => ['title', 'editor', 'thumbnail'],
        'show_in_rest' => true
    ]);
    register_taxonomy('news_media_categories', 'news_media', [
        'labels' => [
            'name' => 'Media Category',
        ],
        'rewrite' => array(
            'slug' => 'media-entries',
            'with_front' => false,
            'hierarchical' => true
        ),
        'hierarchical' => true,
        'show_in_rest' => true,
        'public' => true,
    ]);
}, 0);
