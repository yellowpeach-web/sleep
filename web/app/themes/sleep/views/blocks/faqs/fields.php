<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('faqs');
$block
    ->getClone('heading')
    ->addTextarea('content')
    ->addTrueFalse('swap_layout', [
        'label' => 'Swap layout?',
        'ui' => 1,
    ])
    ->addTrueFalse('show_selected', [
        'label' => 'Select specific FAQs?',
        'instructions' => '*if not selected all FAQs will be displayed',
        'ui' => 1,
    ])
    ->addRelationship('faqs', [
        'label' => 'FAQs',
        'post_type' => ['faq'],
        'filters' => [0 => 'search'],
        'return_format' => 'id'
    ])
    ->conditional('show_selected', '==', '1')
    ->setLocation('block', '==', 'acf/faqs')
    ->setFields();
