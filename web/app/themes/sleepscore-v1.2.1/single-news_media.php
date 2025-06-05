<?php
	wp_enqueue_style( 'light-theme-css', get_template_directory_uri() . '/css/light-theme.min.css?v=2.0.5', array(), '', 'screen');
	get_header(); 
?>
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Single News & Media post
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
	<main class="top-padding-60 light-theme">
		<?php $url = wp_get_attachment_url( get_post_thumbnail_id($post->ID) ); ?>
		<article class="container">
			<div id="post-<?php the_ID(); ?>" class="row bottom-padding">
				<div class="col-12 col-lg-10 offset-sm-1 text-center">
					<h1 class="hero-headline"><?php the_title(); ?></h1>
					<?php get_template_part( 'inc/meta' ); ?>
				</div>
			</div><!-- row -->
		</article><!-- container -->

		<article class="container">
			<div class="row bottom-padding">
				<div class="col-12">
					<div class="article-content">
						<?php the_content(); ?>
					</div><!-- content -->
					<?php // comments_template(); ?>
					<?php //get_template_part('inc/nav'); ?>
				</div><!-- cols -->
				
			</div><!-- row -->
		</article><!-- container -->
	</main>
	<?php get_template_part('parts/pre-footer-banner'); ?>
<?php endwhile; endif; ?>
<?php get_footer(); ?>
