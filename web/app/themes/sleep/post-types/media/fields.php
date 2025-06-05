<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$posts = new ThemeFieldBuilder('media_entry_sidebar_call_to_action', [
      'position' => 'acf_after_title',
]);

$posts
      ->addTrueFalse('overide_sidebard_cta', [
            'label' => 'Overide default sidebar CTA with specific message?',
            'ui' => 1,
      ])
      ->addGroup('single_cta', ['label' => 'Single Call To Action'])
      ->conditional('overide_sidebard_cta', '==', '1')
      ->addText('subheading')
      ->addText('heading')
      ->addWysiwyg('content')
      ->addLink('button')
      ->endGroup()
      ->setLocation('post_type', '==', 'news_media')
      ->setFields();
