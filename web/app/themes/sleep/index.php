<?php

use Timber\Timber;
use YPTheme\HelperFunctions;


$context = Timber::context();
$context['archive_fields'] = HelperFunctions::get_insight_fields();
$template = 'templates/index.twig';
$args = [
    'taxonomy'   => 'category',
];
$terms = Timber::get_terms($args);
$context['terms'] = $terms;
$context['archive_page'] = get_post_type_archive_link('post');
$context['is_all'] = true;
$context['archive_title'] = 'Insights';
$context['color_profile'] = 'light-theme';

Timber::render($template, $context);
