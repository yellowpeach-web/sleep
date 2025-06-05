<?php

use Timber\Timber;
use YPTheme\HelperFunctions;

$context = Timber::context();
$template = 'templates/index.twig';

if (is_post_type_archive('media-entries')) {
    //media archive
    $context['archive_fields'] = HelperFunctions::get_media_fields();
    $args = [
        'taxonomy'   => 'news_media_categories',
    ];
    $terms = Timber::get_terms($args);
    $context['terms'] = $terms;
    $context['archive_title'] = 'Media Entries';
    $context['is_all'] = true;
    $context['archive_page'] = get_post_type_archive_link('media-entries');
    $context['color_profile'] = 'dark-theme';
} elseif (is_post_type_archive('papers')) {
    //papers archive
    $context['archive_fields'] = HelperFunctions::get_paper_fields();
    $args = [
        'taxonomy'   => 'paper_type',
    ];
    $terms = Timber::get_terms($args);
    $context['terms'] = $terms;
    $context['archive_title'] = 'Papers';
    $context['is_all'] = true;
    $context['archive_page'] = get_post_type_archive_link('papers');
    $context['color_profile'] = 'light-theme';
} {
}

Timber::render($template, $context);
