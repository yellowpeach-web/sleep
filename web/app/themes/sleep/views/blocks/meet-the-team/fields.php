<?php

use YPTheme\AcfBuilder\ThemeFieldBuilder;

$block = new ThemeFieldBuilder('meet-the-team');
$block
    ->getClone('heading')
    ->addWysiwyg('content')
    ->addTrueFalse('include_filter', [
        'label' => 'Include filters?',
        'ui' => 1,
    ])
    ->addTrueFalse('show_selected', [
        'label' => 'Select specific team members?',
        'instructions' => '*if not selected all members will be displayed',
        'ui' => 1,
    ])
    ->addRelationship('team', [
        'label' => 'Team memebers',
        'post_type' => ['team'],
        'filters' => [0 => 'search'],
        'return_format' => 'id'
    ])
    ->conditional('show_selected', '==', '1')
    ->setLocation('block', '==', 'acf/meet-the-team')
    ->setFields();
