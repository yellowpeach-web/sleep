<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$posts = new ThemeFieldBuilder('team_fields', [
  'position' => 'acf_after_title'
]);

$posts
  ->addText('role', ['label' => 'Role'])
  ->addUrl('linkedin', ['label' => 'linkedIn URL'])
  ->addUrl('x', ['label' => 'X URL'])
  ->addUrl('medium', ['label' => 'Medium URL'])
  ->setLocation('post_type', '==', 'team')
  ->setFields();
