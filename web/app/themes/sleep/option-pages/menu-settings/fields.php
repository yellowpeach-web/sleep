<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$menu_item_footer = new ThemeFieldBuilder('Menu fields -footer');
$menu_item_product = new ThemeFieldBuilder('Menu fields -product');
$menu_item_legal = new ThemeFieldBuilder('Menu fields -legal');
$menu_item_prime = new ThemeFieldBuilder('Menu fields -primary');

$menu_item_prime
  ->addTrueFalse('none_link', [
    'label' => 'None link item?',
    'ui' => 1,
  ])
  ->addTrueFalse('mobile_only', [
    'label' => 'only display on mobile screen size?',
    'ui' => 1,
  ])
  ->setLocation('nav_menu_item', '==', 'location/primary-navigation')
  ->setFields();

$menu_item_footer
  ->addTextarea('synopsis', [
    'label' => 'Page synopsis',
  ])
  ->setLocation('nav_menu_item', '==', 'location/footer-navigation')
  ->setFields();

$menu_item_legal
  ->addTextarea('synopsis', [
    'label' => 'Page synopsis',
  ])
  ->setLocation('nav_menu_item', '==', 'location/legal-navigation')
  ->setFields();

$menu_item_product
  ->addTextarea('synopsis', [
    'label' => 'Page synopsis',
  ])
  ->setLocation('nav_menu_item', '==', 'location/products-navigation')
  ->setFields();
