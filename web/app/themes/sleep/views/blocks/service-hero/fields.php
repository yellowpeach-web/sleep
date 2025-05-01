<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('service-hero');
$block
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addImage('image')
    ->addImage('hero_image_primary', ['label' => 'Primary screen visual'])
    ->addImage('hero_image_secondary', ['label' => 'Secondary screen visual'])
    ->addRepeater('points', ['max' => 10])
    ->addImage('icon')
    ->addText('point')
    ->endRepeater()
    ->setLocation('block', '==', 'acf/service-hero')
    ->setFields();
