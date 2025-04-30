<?php

add_action('init', function () {
    \YPTheme\HelperFunctions::register_custom_post_type([
        'name' => 'Testimonials',
        'slug' => 'testimonial',
        'has_archive' => false,
        'singular_name' => 'Testimonial',
        'plural_name' => 'Testimonials',
        'icon' => 'dashicons-testimonial',
        'supports'  => ['title'],
        'show_in_rest' => true,
    ]);
}, 0);
