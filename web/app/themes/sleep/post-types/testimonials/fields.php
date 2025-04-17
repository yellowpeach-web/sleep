<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$testimonial_fields = new ThemeFieldBuilder('testimonial_fields', [
  'position' => 'acf_after_title'
]);
$testimonial_fields
  ->addText('role', ['label' => 'Role & company'])
  ->addTextarea('quote')
  ->setLocation('post_type', '==', 'testimonial')
  ->setFields();
