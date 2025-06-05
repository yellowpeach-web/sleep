<?php
	require_once('inc/layout-options.php');
	$layoutOptions = new LayoutOptions();
?>
<?php get_header(); ?>
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Search
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php
	if ($layoutOptions->isVisible('subNav')):
		include('parts/sub-nav.php');
	endif; 
?>
<main class="top-bottom-padding">
	<article class="container">
		<?php if (have_posts()) : ?>
		<div class="row">
			<h2 class="hero-headline col-12">Search Results</h2>
			<?php while (have_posts()) : the_post(); ?>
				<div class="col-8 offset-2 col-md-3 offset-md-0">
					<a href="<?php the_permalink() ?>">
						<?php the_post_thumbnail('large', array('class' => 'img-fluid')); ?>
					</a>
				</div>
				<div <?php post_class('col-12 col-md-9 bottom-padding-15') ?> id="post-<?php the_ID(); ?>">
					<h2 class="product-headline"><?php the_title(); ?></h2>
					<?php get_template_part( 'inc/meta' ); ?>
					<div class="entry">
						<?php the_excerpt(); ?>
					</div>
				</div>
			<?php endwhile; ?>
			
		<?php else : ?>
			<h2>No items found.</h2>
			
		<?php endif; ?>
		</div>
	</article>
</main>
<?php get_footer(); ?>
