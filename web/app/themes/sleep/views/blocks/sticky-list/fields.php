<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('sticky-list');
$block
    ->getClone('heading')
    ->addImage('image')
    ->addRepeater('list')
    ->addImage('icon')
    ->addText('heading')
    ->addText('copy')
    ->endRepeater()
    ->setLocation('block', '==', 'acf/sticky-list')
    ->setFields();
