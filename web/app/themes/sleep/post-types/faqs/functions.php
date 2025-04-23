<?php

add_action('init', function () {
    \YPTheme\HelperFunctions::register_custom_post_type([
        'name' => 'FAQs',
        'slug' => 'faq',
        'singular_name' => 'FAQ',
        'plural_name' => 'FAQs',
        'icon' => 'dashicons-editor-help',
        'supports' => ['title', 'editor', 'thumbnail'],
        'show_in_rest' => false
    ]);
    register_taxonomy('filter', 'faq', [
        'labels' => [
            'name' => 'Filter',
            'singular_name' => 'Filters',
        ],
        'hierarchical' => true,
        'show_in_rest' => true,
        'public' => true,
    ]);
}, 0);
