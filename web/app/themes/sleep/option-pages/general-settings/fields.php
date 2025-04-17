<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$general_settings = new ThemeFieldBuilder('general-settings');

$general_settings
    ->addTab('General settings')
    ->addText('company_name', [
        'label' => 'Company name'
    ])
    ->addText('telephone_number', [
        'label' => 'Telephone number'
    ])
    ->addEmail('email_address', [
        'label' => 'Email address'
    ])
    ->addRepeater('location')
    ->addText('heading')
    ->addTextarea('address', [
        'label' => 'Address',
        'rows' => '3'
    ])
    ->endRepeater()
    ->addUrl('instagram_url', [
        'label' => 'Instagram URL'
    ])
    ->addUrl('twitter_url', [
        'label' => 'Twitter URL'
    ])
    ->addUrl('linkedin_url', [
        'label' => 'LinkedIn URL'
    ])
    ->addTab('Header')
    ->addSelect('header_bg_color', [
        'label' => 'Header Background Color',
        'instructions' => 'Select a background color for the navigation.',
        'choices' => [
            'dark-theme' => 'Blue',
            'light-theme' => 'Cream',
        ],
        'default_value' => 'Blue',
    ])
    ->addLink('header_button')
    ->addTab('Footer')
    ->addLink('footer_button')
    ->addGroup('left_column')
    ->addText('heading')
    ->addRepeater('item')
    ->addText('heading')
    ->addWysiwyg('synopsis', ['toolbar' => 'basic'])
    ->endRepeater()
    ->endGroup()
    ->addTab('Set archive pages')
    ->addPostObject('posts_archive', ['label' => 'Posts Archive', 'return_format' => 'id', 'instructions' => 'set arhive page for posts'])
    ->setLocation('options_page', '==', 'general-settings')
    ->setFields();
