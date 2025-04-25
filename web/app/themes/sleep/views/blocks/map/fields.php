<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('map');
$block
    ->addTrueFalse('show_dublin', [
        'label' => 'Display dublin office?',
        'ui' => 1,
    ])
    ->addTrueFalse('show_us', [
        'label' => 'Display US office?',
        'ui' => 1,
    ])
    ->setLocation('block', '==', 'acf/map')
    ->setFields();
