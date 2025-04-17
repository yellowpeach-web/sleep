<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('logo-carousel');
$block
    ->addText('text')
    ->addGallery('logos')
    ->setLocation('block', '==', 'acf/logo-carousel')
    ->setFields();
