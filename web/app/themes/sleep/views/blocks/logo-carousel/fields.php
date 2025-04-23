<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('logo-carousel');
$block
    ->addGallery('logos')
    ->setLocation('block', '==', 'acf/logo-carousel')
    ->setFields();
