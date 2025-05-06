<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('text-alongside-image');
$block
    ->addTrueFalse('swap_layout', [
        'label' => 'Swap layout?',
        'ui' => 1,
    ])
    ->getClone('heading')
    ->addWysiwyg('content')
    ->getClone('buttons')
    ->addImage('image')
    ->addTrueFalse('contain_image', [
        'label' => 'Contain Image?',
        'ui' => 1,
    ])
    ->setLocation('block', '==', 'acf/text-alongside-image')
    ->setFields();
