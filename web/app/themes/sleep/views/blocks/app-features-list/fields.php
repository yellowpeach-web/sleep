<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('app-features-list');
$block
    ->getClone('heading')
    ->addRepeater('app_features')
    ->addImage('icon')
    ->addText('heading')
    ->addText('copy')
    ->endRepeater()
    ->addImage('image')
    ->addRepeater('stamps', ['label' => 'App store Stamps'])
    ->addImage('stamp')
    ->addUrl("link")
    ->endRepeater()
    ->setLocation('block', '==', 'acf/app-features-list')
    ->setFields();
