<?php

// Wordpress default category template

$context = Timber::context();
$post = Timber::get_post();

Timber\Timber::render(array( 'templates/category.twig', 'templates/archive.twig' ), $context);
