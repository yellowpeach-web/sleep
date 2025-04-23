<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('full-w-content');
$block
    ->addTrueFalse('bg_graphic', [
        'label' => 'display background texture?',
        'ui' => 1,
    ])
    ->addTrueFalse('narrow_width', [
        'label' => 'Narrow content width?',
        'ui' => 1,
    ])
    ->addTrueFalse('central_content', [
        'label' => 'Centralise text content?',
        'ui' => 1,
    ])
    ->getClone('heading')
    ->addWysiwyg('content')
    ->getClone('buttons')
    ->setLocation('block', '==', 'acf/full-w-content')
    ->setFields();
