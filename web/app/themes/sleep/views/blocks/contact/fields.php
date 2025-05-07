<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('contact');
$block
    ->addText('subheading')
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addTrueFalse('gravity_form', [
        'label' => 'Use Gravity form?',
        'instructions' => 'if not hubspot embed will be used',
        'ui' => 1,
    ])
    ->addNumber('form_id')
    ->conditional('gravity_form', '==', '1')
    ->setLocation('block', '==', 'acf/contact')
    ->setFields();
