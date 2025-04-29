<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('api-tabs');
$block
    ->addRepeater('api_service', [
        'layout' => 'block',
    ])
    ->addText('name')
    ->addSelect('media_type', [
        'label' => 'Media Type',
        'choices' => [
            'image' => 'Image',
            'video' => 'Video',
        ],
        'default_value' => 'image',
        'ui' => 1,
        'return_format' => 'value',
    ])
    ->addImage('image', [
        'label' => 'Upload Image',
        'conditional_logic' => [
            [
                [
                    'field' => 'media_type',
                    'operator' => '==',
                    'value' => 'image',
                ],
            ],
        ],
    ])
    ->addFile('video', [
        'label' => 'Upload Video',
        'mime_types' => 'mp4,webm,ogg',
        'conditional_logic' => [
            [
                [
                    'field' => 'media_type',
                    'operator' => '==',
                    'value' => 'video',
                ],
            ],
        ],
    ])
    ->addText('heading')
    ->addWysiwyg('content')
    ->addRepeater('statistics', ['max' => 10])
    ->addText('prefix', [
        'label' => 'Prefix',
        'instructions' => '* eg +, -  if required',
    ])
    ->addNumber('number')
    ->addText('postfix', [
        'label' => 'Postfix',
        'instructions' => '* eg %, - if required',
    ])
    ->addText('copy')
    ->endRepeater()
    ->addLink('button')
    ->endRepeater()
    ->setLocation('block', '==', 'acf/api-tabs')
    ->setFields();
