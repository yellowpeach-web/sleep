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
    ->addLink('announcement_bar')
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
    ->addTab('Insights Page')
    ->addText('insights_heading')
    ->addWysiwyg('insights_content')
    ->addLink('insights_cta', ['label' => 'Gravity CTA values'])
    ->addTab('Insight Single')
    ->addText('single_subheading', ['label' => 'Single Page subheading'])
    ->addGroup('single_cta', ['label' => 'Single Call To Action'])
    ->addText('subheading')
    ->addText('heading')
    ->addWysiwyg('content')
    ->addLink('button')
    ->endGroup()
    ->addGroup('insights_feed')
    ->addText('heading')
    ->addWysiwyg('content')
    ->endGroup()
    ->addLink('insight_single_cta', ['label' => 'Gravity CTA values'])
    ->addTab('Media Entries Page')
    ->addPostObject('media_archive_page', ['label' => 'Media Entries Archive Page', 'return_format' => 'id', 'instructions' => 'set page to display all media entries'])
    ->addText('media_entries_heading')
    ->addWysiwyg('media_entries_content')
    ->addLink('media_entries_cta', ['label' => 'Gravity CTA values'])
    ->addTab('Media Single')
    ->addText('media_single_subheading', ['label' => 'Single Page subheading'])
    ->addGroup('media_single_cta', ['label' => 'Single Call To Action'])
    ->addText('subheading')
    ->addText('heading')
    ->addWysiwyg('content')
    ->addLink('button')
    ->endGroup()
    ->addGroup('media_feed')
    ->addText('heading')
    ->addWysiwyg('content')
    ->endGroup()
    ->addLink('media_single_cta_gravity', ['label' => 'Gravity CTA values'])
    ->addTab('Papers Page')
    ->addPostObject('papers_archive_page', ['label' => 'Media Entries Archive Page', 'return_format' => 'id', 'instructions' => 'set page to display all media entries'])
    ->addText('papers_heading')
    ->addWysiwyg('papers_content')
    ->addLink('papers_cta', ['label' => 'Gravity CTA values'])
    ->addTab('Partner logos')
    ->addRepeater('logos')
    ->addImage(
        'blue_company_logo',
        ['instructions' => 'For use on white background']
    )
    ->addImage(
        'white_company_logo',
        ['instructions' => 'For use on blue background']
    )
    ->endRepeater()
    ->setLocation('options_page', '==', 'general-settings')
    ->setFields();
