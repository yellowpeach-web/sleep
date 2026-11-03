<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('secondary-hero');
$block
    ->addText('secondary_heading')
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addImage('image')
    ->addLink('button', [
        'label' => 'Button',
        'wrapper' => ['width' => '50%'],
    ])
    ->addLink('secondary_button', [
        'label' => 'Secondary button',
        'wrapper' => ['width' => '50%'],
    ])
    ->setLocation('block', '==', 'acf/secondary-hero')
    ->setFields();
