<?php

use Timber\Timber;
use YPTheme\HelperFunctions;

$context = Timber::context();

$queried_object = get_queried_object();
$context['current_term'] = ($queried_object instanceof WP_Term) ? $queried_object->slug : null;
$template = 'templates/index.twig';

if (is_category()) {
      $context['archive_fields'] = HelperFunctions::get_insight_fields();
      $args = [
            'taxonomy'   => 'category',
      ];
      $terms = Timber::get_terms($args);
      $context['terms'] = $terms;
      $context['archive_page'] = get_post_type_archive_link('post');
      $context['archive_title'] = 'Insights';
      $context['color_profile'] = 'light-theme';
}



Timber::render($template, $context);
