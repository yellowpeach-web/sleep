<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('partnerships-display');
$block
    ->getClone("heading")
    ->addWysiwyg('content')
    ->addRepeater('partnership_category')
    ->addText('label')
    ->addGallery('logos')
    ->endRepeater('')
    ->setLocation('block', '==', 'acf/partnerships-display')
    ->setFields();
