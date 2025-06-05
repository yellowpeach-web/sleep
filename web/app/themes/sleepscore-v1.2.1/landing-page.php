<?php
/*
* Template Name: Landingpage
*/
get_header();
?>
<?php
while (have_posts()) : the_post();
endwhile;
wp_reset_query();
?>
<?php get_template_part('template-parts/flexible', 'modules'); ?>
<?php get_footer(); ?>