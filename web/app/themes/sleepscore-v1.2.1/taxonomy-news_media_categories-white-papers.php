<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
taxonomy white papers php
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php
wp_reset_query();
$the_query = new WP_Query( array(
    'post_type' => 'news_media',
    'tax_query' => array(
        array (
            'taxonomy' => 'news_media_categories',
            'field' => 'slug',
            'terms' => 'white-papers',
        )
    ),
	'posts_per_page' => -1,
) );
?>
<main class="top-bottom-padding">
	<article class="container bottom-padding-60">
		<div class="row">
			<h1 class="col-12 hero-headline bottom-padding">Papers & Abstracts</h1>
		<?php while ( $the_query->have_posts() ) : $the_query->the_post(); ?>
			<div class="col-12 bottom-padding">
				<a class="product-headline" href="<?php the_field('white_paper_link') ?>"><?php the_title(); ?></a><br>
				<?php if(get_field('white_paper_author')): ?>
				<span><?php the_field('white_paper_author'); ?></span>
				<br>
				<?php endif; ?>
				<span class="publish-date"><?php the_field('white_paper_publication_date'); ?></span>
			</div><!-- post-->

		<?php endwhile; ?>
		<?php wp_reset_postdata();?>
		</div><!-- row -->
	</article>
</main>

<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
