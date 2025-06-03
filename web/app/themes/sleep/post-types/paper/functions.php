<?php

add_action('init', function () {
    \YPTheme\HelperFunctions::register_custom_post_type([
        'name' => 'Papers',
        'slug' => 'paper',
        'singular_name' => 'Paper',
        'plural_name' => 'Papers',
        'icon' => 'dashicons-media-default',
        'show_in_rest' => false
    ]);
    register_taxonomy('paper_type', 'paper', [
        'labels' => [
            'name' => 'Paper Type',
        ],
        'hierarchical' => true,
        'show_in_rest' => true,
        'public' => true,
    ]);
}, 0);
