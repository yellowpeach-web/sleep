<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('posts-feed');
$block
    ->getClone('heading')
    ->addWysiwyg('content')
    ->setLocation('block', '==', 'acf/posts-feed')
    ->setFields();
