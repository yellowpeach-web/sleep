<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('testimonial-carousel');
$block
    ->addTrueFalse('show_selected', [
        'label' => 'Select specific Testimonials?',
        'instructions' => '*if not selected all Testimonials will be displayed',
        'ui' => 1,
    ])
    ->addRelationship('testimonials', [
        'label' => 'Testimonials',
        'post_type' => ['testimonial'],
        'filters' => [0 => 'search'],
        'return_format' => 'id'
    ])
    ->conditional('show_selected', '==', '1')
    ->setLocation('block', '==', 'acf/testimonial-carousel')
    ->setFields();
