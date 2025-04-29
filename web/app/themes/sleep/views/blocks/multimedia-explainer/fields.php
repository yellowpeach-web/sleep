<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('multimedia-explainer');
$block
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addRepeater('filter-headings', [
        'layout' => 'block',
    ])
    ->addText('label')
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
    ->addRepeater('content', [
        'layout' => 'block',
    ])
    ->addText('subheading')
    ->addText('heading')
    ->addWysiwyg('content')
    ->addLink('button')
    ->endRepeater()
    ->endRepeater()
    ->setLocation('block', '==', 'acf/multimedia-explainer')
    ->setFields();
