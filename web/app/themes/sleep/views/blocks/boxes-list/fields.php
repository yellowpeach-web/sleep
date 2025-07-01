<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('boxes-list');
$block
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addRepeater('boxes')
    ->addImage('icon')
    ->addText('heading')
    ->addRepeater('point')
    ->addText('copy')
    ->endRepeater()
    ->addLink('button')
    ->endRepeater()
    ->getClone('buttons')
    ->setLocation('block', '==', 'acf/boxes-list')
    ->setFields();
