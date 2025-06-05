<?php
    /*
        Template Name: No Title
    */
?>
<?php get_header(); ?>
<main class="top-bottom-padding">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article class="container">
            <div class="post" id="post-<?php the_ID(); ?>">
                <div class="entry">
                    <?php the_content(); ?>
                </div>
            </div>
        </article>
    <?php endwhile; endif; ?>
</main>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
