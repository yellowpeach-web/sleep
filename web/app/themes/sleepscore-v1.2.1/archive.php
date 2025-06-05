<?php
	wp_enqueue_style( 'light-theme-css', get_template_directory_uri() . '/css/light-theme.min.css?v=2.0.5', array(), '', 'screen');
	get_header(); 
?>
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Archive
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<main class="top-padding-15 bottom-padding light-theme">
	<article class="container bottom-padding-60">
		<?php /* <div class="row bottom-padding">
			<div class="col-12 col-sm-4 offset-sm-8 search-form-wrapper">
				<?php // get_search_form(); ?>
			</div>
		</div> */ ?>
		<div class="row align-items-center">
			<?php if (have_posts()) : ?>

				<?php $post = $posts[0]; // Hack. Set $post so that the_date() works. ?>

				<?php /* If this is a category archive */ if (is_category()) { ?>
					<h2>Archive for the &#8216;<?php single_cat_title(); ?>&#8217; Category</h2>

				<?php /* If this is a tag archive */ } elseif( is_tag() ) { ?>
					<div class="col-12 headline bottom-padding capitalize">Popular Topic(s): <?php single_tag_title(); ?></div>

				<?php /* If this is a daily archive */ } elseif (is_day()) { ?>
					<h2>Archive for <?php the_time('F jS, Y'); ?></h2>

				<?php /* If this is a monthly archive */ } elseif (is_month()) { ?>
					<h2>Archive for <?php the_time('F, Y'); ?></h2>

				<?php /* If this is a yearly archive */ } elseif (is_year()) { ?>
					<h2>Archive for <?php the_time('Y'); ?></h2>

				<?php /* If this is an author archive */ } elseif (is_author()) { ?>
					<h2>Author Archive</h2>

				<?php /* If this is a paged archive */ } elseif (isset($_GET['paged']) && !empty($_GET['paged'])) { ?>
					<h2>Blog Archives</h2>

				<?php } ?>

				
				<?php while (have_posts()) : the_post(); ?>
					<?php if (has_post_thumbnail()): ?>
					<div class="col-8 offset-2 col-md-2 offset-md-1 bottom-padding-15">
						<a href="<?php the_permalink() ?>">
						<?php the_post_thumbnail('large', array('class' => 'img-fluid')); ?>
						</a>
					</div>
					<?php endif; ?>
					<div class="col-12 <?php echo has_post_thumbnail() ? 'col-md-9' : '' ?>">
						<div id="post-<?php the_ID(); ?>" class="bottom-padding-15">
							<a class="headline font-24" href="<?php the_permalink() ?>"><?php the_title(); ?></a>
							<p ><?php the_excerpt(); ?></p>
						</div>
					</div><!-- post-->
				<?php endwhile; ?>
		<?php else : ?>
		<?php endif; ?>
		</div><!-- row -->
	</article>
</main>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
