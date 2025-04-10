<?php

$templates = array('templates/search.twig', 'templates/archive.twig', 'templates/index.twig');

$context = Timber::context();
$context['searched_for'] = 'Search results for ' . get_search_query();
$context['posts'] = Timber::get_posts();

Timber\Timber::render($templates, $context);
