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
    ->addTextarea('address', [
        'label' => 'Address',
        'rows' => '3'
    ])
    ->addUrl('facebook_url', [
        'label' => 'FaceBook URL'
    ])
    ->addUrl('instagram_url', [
        'label' => 'Instagram URL'
    ])
    ->addUrl('twitter_url', [
        'label' => 'Twitter URL'
    ])
    ->addUrl('linkedin_url', [
        'label' => 'LinkedIn URL'
    ])
    ->addTab('Set archive pages')
    ->addPostObject('posts_archive', ['label' => 'Posts Archive', 'return_format' => 'id', 'instructions' => 'set arhive page for posts'])
    ->setLocation('options_page', '==', 'general-settings')
    ->setFields();
