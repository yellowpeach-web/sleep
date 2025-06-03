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
    ->addTaxonomy('faq_terms', [
        'label' => 'FAQ Categories to include',
        'instructions' => 'Select one or more FAQ categories to include.',
        'taxonomy' => 'filter',
        'field_type' => 'multi_select',
        'add_term' => 0,
        'return_format' => 'id',
    ])
    ->setLocation('block', '==', 'acf/faqs')
    ->setFields();
