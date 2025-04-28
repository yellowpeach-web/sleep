<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('stats');
$block
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addRepeater('stats')
    ->addText('stat')
    ->addText('context')
    ->endRepeater()
    ->setLocation('block', '==', 'acf/stats')
    ->setFields();
