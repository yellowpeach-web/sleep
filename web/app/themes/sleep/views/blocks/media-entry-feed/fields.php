<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('media-entry-feed');
$block
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addTaxonomy('media_entry_cat', [
        'label' => 'Media Enty Category',
        'instructions' => 'Select the category you would like to display.',
        'taxonomy' => 'media_type',
        'field_type' => 'select',
        'add_term' => 0,
        'return_format' => 'id',
    ])
    ->setLocation('block', '==', 'acf/media-entry-feed')
    ->setFields();
