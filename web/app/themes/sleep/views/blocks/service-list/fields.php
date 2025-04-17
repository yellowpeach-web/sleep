<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('service-list');
$block
    ->addRepeater('services')
    ->addLink('service')
    ->addImage('image', ['label' => 'Related Image'])
    ->endRepeater()
    ->setLocation('block', '==', 'acf/service-list')
    ->setFields();
