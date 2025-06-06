<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$menu_item_footer = new ThemeFieldBuilder('Menu fields -footer');
$menu_item_product = new ThemeFieldBuilder('Menu fields -product');
$menu_item_legal = new ThemeFieldBuilder('Menu fields -legal');
$menu_item_prime = new ThemeFieldBuilder('Menu fields -primary');

$menu_item_prime
  ->addTrueFalse('mobile_only', [
    'label' => 'only display on mobile screen size?',
    'ui' => 1,
  ])
  ->addTrueFalse('none_link', [
    'label' => 'Navigational only?',
    'ui' => 1,
  ])
  ->addTrueFalse('mega_menu', [
    'label' => 'Mega Menu Item?',
    'ui' => 1,
  ])
  ->addTrueFalse('insight_mega_menu', [
    'label' => 'Mega Menu for insights?',
    'ui' => 1,
  ])
  ->addTaxonomy('insight_mega_categories', [
    'label' => 'Select Categories for Mega Menu',
    'instructions' => 'Select up to 4 categories to show in the mega menu.',
    'taxonomy' => 'category',
    'field_type' => 'multi_select',
    'add_term' => 0,
    'return_format' => 'id',
    'multiple' => 1,
  ])
  ->conditional('insight_mega_menu', '==', 1)
  ->conditional('mega_menu', '==', '1')
  ->addGroup('menu_options',)
  ->addSelect('menu_cols', [
    'label' => 'Menu Columns',
    'choices' => [
      '1' => '1',
      '2' => '2',
      '3' => '3',
      '4' => '4',
    ],
    'default_value' => 'item',
  ])
  ->conditional('mega_menu', '==', '1')
  ->conditional('insight_mega_menu', '==', '0')
  ->addRepeater('page_cols', ['label' => 'Page Columns', 'instructions' => 'Add up to 4 page columns',   'layout' => 'row', 'max' => 4])
  ->addSelect('col_type', [
    'label' => 'Column Type',
    'choices' => [
      'list' => 'List',
      'item' => 'Item',
    ],
    'default_value' => 'item',
  ])
  ->addLink('heading')
  ->addImage('image')
  ->conditional('col_type', '==', 'item')
  ->addText('page_synopsis')
  ->conditional('col_type', '==', 'item')
  ->addRepeater('sub_pages', ['layout' => 'row'])
  ->conditional('col_type', '==', 'list')
  ->addLink('sub_page')
  ->addText('sub_page_synopsis')
  ->endRepeater()
  ->endRepeater()
  ->endGroup()
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
