<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$paper = new ThemeFieldBuilder('Paper Info', [
      'position' => 'acf_after_title'
]);


$paper
      ->addText('abstract')
      ->addUrl('external_link')
      ->setLocation('post_type', '==', 'papers')
      ->setFields();
