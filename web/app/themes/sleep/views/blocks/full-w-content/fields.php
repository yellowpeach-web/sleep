<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('full-w-content');
$block
    ->addTrueFalse('narrow_width', [
        'label' => 'Narrow content width?',
        'instructions' => '*if not selected static graphic will be used',
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
