<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('gravity-grid-cta');
$block
    ->addLink('cta', ['label' => 'Call To Action'])
    ->setLocation('block', '==', 'acf/gravity-grid-cta')
    ->setFields();
