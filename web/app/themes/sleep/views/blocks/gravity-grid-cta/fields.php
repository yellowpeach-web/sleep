<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('gravity-grid-cta');
$block
    ->addRepeater('services')
    ->addLink('statement_link')
    ->setLocation('block', '==', 'acf/gravity-grid-cta')
    ->setFields();
