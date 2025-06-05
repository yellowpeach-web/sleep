<?php
/*
Template Name: No Sidebar
*/
?>
<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
No Sidebar page
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<main>
	<article id="no-sidebar" class="container">
	<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
		<div class="post" id="post-<?php the_ID(); ?>">
			<h1><?php the_title(); ?></h1>
			<?php get_template_part( 'inc/meta' ); ?>
			<div class="entry">
				<?php the_content(); ?>
			</div><!-- entry->
		</div><!--post-->
		<?php endwhile; endif; ?>
	</article>
</main>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>