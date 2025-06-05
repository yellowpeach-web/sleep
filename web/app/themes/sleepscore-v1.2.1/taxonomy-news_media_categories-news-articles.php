<?php
	wp_enqueue_style( 'light-theme-css', get_template_directory_uri() . '/css/light-theme.min.css?v=2.0.5', array(), '', 'screen');
?>
<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
News Articles php
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php
wp_reset_query();
$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
$the_query = new WP_Query( array(
    'post_type' => 'news_media',
    'tax_query' => array(
        array (
            'taxonomy' => 'news_media_categories',
            'field' => 'slug',
            'terms' => 'news-articles',
        )
    ),
	'paged' => $paged,
	'posts_per_page' => 12,
) );
?>
<main class="top-bottom-padding light-theme">
	<article class="container bottom-padding-60">
		<div class="row">
			<h1 class="col-12 hero-headline bottom-padding">News Articles</h1>
		</div><!-- row -->
		<div class="row">
		<?php while ( $the_query->have_posts() ) : $the_query->the_post(); ?>
			<a target="_blank" class="col-12 bordered-box" href="<?php the_field('news_articles_link') ?>"><span class="pull-right fa fa-arrow-right"></span>
				<span class="font-20"><?php the_title(); ?></span> - <span class="font-12"><?php // the_time('F jS, Y') ?><?php the_field('publish_date'); ?></span>
			</a><!-- col -->
		<?php endwhile; ?>
		</div><!-- row-->
		<div class="row justify-content-between top-bottom-padding">
			<div class="nav-next"><?php previous_posts_link( 'Back' ); ?></div>
			<div class="nav-previous"><?php next_posts_link( 'More' ); ?></div>
		</div><!-- row -->
		<?php wp_reset_postdata();?>
	</article>
</main>

<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
