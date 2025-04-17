<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('large-text');
$block
    ->addRepeater('statements')
    ->addWysiwyg('reference_text', [
        'label' => 'Reference Text',
        'toolbar' => 'basic',
        'media_upload' => 0,
        'tabs' => 'visual',
    ])
    ->addRepeater('item')
    ->addSelect('type', [
        'label' => 'Text or Image?',
        'choices' => [
            'text' => 'Text',
            'image' => 'Image',
        ],
        'default_value' => 'text',
        'ui' => 1
    ])
    ->addText('text', [
        'conditional_logic' => [
            [
                [
                    'field' => 'type',
                    'operator' => '==',
                    'value' => 'text',
                ],
            ],
        ],
    ])
    ->addImage('image', [
        'conditional_logic' => [
            [
                [
                    'field' => 'type',
                    'operator' => '==',
                    'value' => 'image',
                ],
            ],
        ],
    ])
    ->endRepeater()
    ->setLocation('block', '==', 'acf/large-text')
    ->setFields();
