<?php

$context = Timber::context();
$post = Timber::get_post();
$context['post'] = $post;

Timber\Timber::render(array( 'templates/page-' . $post->post_name . '.twig', 'templates/page.twig' ), $context);
