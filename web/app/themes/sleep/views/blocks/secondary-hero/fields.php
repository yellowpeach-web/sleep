<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('secondary-hero');
$block
    ->addText('secondary_heading')
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addImage('image')
    ->addLink('button')
    ->setLocation('block', '==', 'acf/secondary-hero')
    ->setFields();
