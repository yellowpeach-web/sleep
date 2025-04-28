<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('text-and-image-cards');
$block
    ->getClone('heading')
    ->addWysiwyg('content')
    ->getClone('buttons')
    ->addRepeater('cards')
    ->addText('heading')
    ->addImage('image')
    ->addWysiwyg('content')
    ->addLink('button')
    ->endRepeater()
    ->setLocation('block', '==', 'acf/text-and-image-cards')
    ->setFields();
