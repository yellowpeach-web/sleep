<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('homepage-hero');
$block
    ->getClone('heading')
    ->addWysiwyg('content')
    ->getClone('buttons')
    ->addImage('image', ['label' => 'Hero Image'])
    // ->addImage('hero_image_primary', ['label' => 'Primary screen visual'])
    // ->addImage('hero_image_secondary', ['label' => 'Secondary screen visual'])
    ->addRepeater('statistics', ['max' => 10])
    ->addText('prefix', [
        'label' => 'Prefix',
        'instructions' => '* eg +, -  if required',
    ])
    ->addNumber('number')
    ->addText('postfix', [
        'label' => 'Postfix',
        'instructions' => '* eg %, - if required',
    ])
    ->addText('copy')
    ->endRepeater()
    ->setLocation('block', '==', 'acf/homepage-hero')
    ->setFields();
