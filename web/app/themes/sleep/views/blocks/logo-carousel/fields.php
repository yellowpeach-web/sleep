<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('logo-carousel');
$block
    // ->addGallery('logos')
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addTrueFalse('show_selected', [
        'label' => 'Select specific Logos?',
        'instructions' => '*if not all logos from general settings will be displayed',
        'ui' => 1,
    ])
    ->addGallery('logos')
    ->conditional('show_selected', '==', '1')
    ->setLocation('block', '==', 'acf/logo-carousel')
    ->setFields();
