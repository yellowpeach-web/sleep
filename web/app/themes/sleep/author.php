<?php

global $wp_query;

$context = Timber\Timber::context();
$context['posts'] = Timber::get_posts();
if (isset($wp_query->query_vars['author'])) {
    $author = Timber::get_user($wp_query->query_vars['author']);
    $context['author'] = $author;
    $context['title']  = 'Author Archives: ' . $author->name();
}
Timber\Timber::render(array( 'templates/author.twig', 'templates/archive.twig' ), $context);
