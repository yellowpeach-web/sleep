<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$testimonial_fields = new ThemeFieldBuilder('testimonial_fields', [
  'position' => 'acf_after_title'
]);
$testimonial_fields
  ->addImage(
    'blue_company_logo',
    ['instructions' => 'logo for use on white Background blocks']
  )
  ->addImage(
    'white_company_logo',
    ['instructions' => 'logo for use on Blue Background blocks']
  )
  ->addText('role', ['label' => 'Role & company'])
  ->addTextarea('quote')
  ->setLocation('post_type', '==', 'testimonial')
  ->setFields();
