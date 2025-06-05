<?php
/* Template Name: Custom Search */
get_header(); ?>
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
archive-Search
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<main class="top-bottom-padding">
	<article class="container">
		<div class="row">
			<h3 class="col-12 headline">Search Result for : <?php echo "$s"; ?> </h3>
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<div class="col-8 offset-2 col-md-3 offset-md-0">
					<a href="<?php the_permalink() ?>">
						<?php the_post_thumbnail(large, array('class' => 'img-fluid')); ?>
					</a>
				</div>
				<div id="post-<?php the_ID(); ?>" class="col-12 col-md-9 bottom-padding-15">
					<a class="product-headline" href="<?php the_permalink(); ?>" title="<?php the_title(); ?>"><?php the_title(); ?></a>
					<p><?php the_field('product_excerpt'); ?></p>
					<p ><a class="outline-button" href="<?php the_permalink(); ?>">Read More</a></p>
				</div>
				<?php endwhile; ?>
				<?php else : ?>
				<h2>Sorry, no items found.</h2>
			<?php endif; ?>
		</div><!-- row -->
	</article>
</main>
<div class="container">
	<div class="row">
		<?php get_sidebar(); ?>
	</div>
</div>
<?php get_footer(); ?>
