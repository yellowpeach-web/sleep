<?php

use Timber\Timber;
use YPTheme\HelperFunctions;

$context = Timber::context();
$context['insights_fields'] = HelperFunctions::get_insight_fields();
$template = 'templates/index.twig';
$args = [
      'taxonomy'   => 'category',
];
$terms = Timber::get_terms($args);
$context['terms'] = $terms;
$context['archive_page'] = get_post_type_archive_link('post');
$context['current_term'] = get_queried_object()->slug ?? null;


Timber::render($template, $context);
