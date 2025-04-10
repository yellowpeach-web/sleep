<?php
$context = Timber::context();
$context['posts'] = Timber::get_posts();
$templates = array('index.twig');
if (is_home()) {

    array_unshift($templates, 'templates/front-page.twig', 'templates/home.twig', 'templates/index.twig');
}
Timber::render($templates, $context);
