<?php

use Timber\Timber;
use YPTheme\HelperFunctions;

$context = Timber::context();
$template = 'templates/index.twig';

$queried_object = get_queried_object();
$context['current_term'] = ($queried_object instanceof WP_Term) ? $queried_object->slug : null;
$context['term'] = false;

if (is_tax('news_media_categories')) {
      $context['archive_fields'] = HelperFunctions::get_media_fields();
      $args = [
            'taxonomy'   => 'news_media_categories',
      ];
      $terms = Timber::get_terms($args);
      $context['terms'] = $terms;
      $context['archive_title'] = 'Media Entries';
      $context['archive_page'] = get_post_type_archive_link('media-entries');
      $context['current_term'] = get_queried_object()->slug ?? null;
      $context['color_profile'] = 'dark-theme';
} elseif (is_tax('paper_type')) {
      $context['archive_fields'] = HelperFunctions::get_paper_fields();
      $args = [
            'taxonomy'   => 'paper_type',
      ];
      $terms = Timber::get_terms($args);
      $context['terms'] = $terms;
      $context['archive_title'] = 'Papers';
      $context['archive_page'] = get_post_type_archive_link('papers');
      $context['color_profile'] = 'light-theme';
}
Timber::render($template, $context);
