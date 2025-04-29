<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('stats-figures');
$block
    ->getClone('heading')
    ->addWysiwyg('content')
    ->getClone("buttons")
    ->addRepeater('statistics', ['max' => 10])
    ->addText('prefix', [
        'label' => 'Prefix',
        'instructions' => '* eg +, -  if required',
    ])
    ->addNumber('number')
    ->addText('postfix', [
        'label' => 'Postfix',
        'instructions' => '* eg %, - if required',
    ])
    ->addText('copy')
    ->endRepeater()
    ->setLocation('block', '==', 'acf/stats-figures')
    ->setFields();
