<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('contact');
$block
    ->addText('subheading')
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addNumber('form_id')
    ->setLocation('block', '==', 'acf/contact')
    ->setFields();
