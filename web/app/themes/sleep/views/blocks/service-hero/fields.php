<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('service-hero');
$block
    ->addText('subheading')
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addImage('image', ['label' => 'Hero Image'])
    ->addRepeater('points', ['max' => 10])
    ->addImage('icon')
    ->addText('point')
    ->endRepeater()
    ->addRepeater('stamps', ['label' => 'App store Stamps'])
    ->addImage('stamp')
    ->addUrl("link")
    ->endRepeater()
    ->setLocation('block', '==', 'acf/service-hero')
    ->setFields();
