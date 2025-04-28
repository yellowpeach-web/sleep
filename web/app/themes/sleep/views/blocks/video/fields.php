<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('video');

$block
    ->addSelect('video_type', [
        'label' => 'Video Type',
        'instructions' => 'Select whether to embed a video or upload a file.',
        'choices' => [
            'embed' => 'Embed URL',
            'upload' => 'Upload File',
        ],
        'default_value' => 'embed',
        'ui' => 1,
    ])
    ->addOembed('video_embed', [
        'label' => 'Video Embed URL',
        'conditional_logic' => [
            [
                [
                    'field' => 'video_type',
                    'operator' => '==',
                    'value' => 'embed',
                ],
            ],
        ],
    ])
    ->addFile('video_upload', [
        'label' => 'Video File Upload',
        'mime_types' => 'mp4,webm,ogg',
        'conditional_logic' => [
            [
                [
                    'field' => 'video_type',
                    'operator' => '==',
                    'value' => 'upload',
                ],
            ],
        ],
    ])
    ->setLocation('block', '==', 'acf/video')
    ->setFields();
