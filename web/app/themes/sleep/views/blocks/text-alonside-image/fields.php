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
    ->setLocation('block', '==', 'acf/text-alongside-image')
    ->setFields();
