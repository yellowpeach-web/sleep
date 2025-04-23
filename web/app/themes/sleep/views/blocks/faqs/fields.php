<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('faqs');
$block
    ->getClone('heading')
    ->addLink('button')
    ->addWysiwyg('content')
    ->addTrueFalse('include_filter', [
        'label' => 'Include filters?',
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
