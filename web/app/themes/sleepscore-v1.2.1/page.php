<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
page
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php
	$page_theme = get_field('page_theme'); 

	$main_class_name = '';

	if ($page_theme === 'Light') {
		wp_enqueue_style( 'light-theme-css', get_template_directory_uri() . '/css/light-theme.min.css?v=2.0.5', array(), '', 'screen');
		$main_class_name = ' light-theme';
	}
?>
<main class="top-bottom-padding<?php echo $main_class_name ?>">
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
	<article class="container">
		<div class="post" id="post-<?php the_ID(); ?>">
			<h1 class="hero-headline bottom-padding"><?php the_title(); ?></h1>
			<div class="entry">
				<?php the_content(); ?>
			</div><!--entry -->
		</div><!--post-->
	</article>

<?php endwhile; endif; ?>
<?php if(is_page('faq')) {
	wp_reset_query();
	$args = array(
		'post_type'      => 'page',
		'posts_per_page' => -1,
		'post_parent'    => $post->ID,
		'order'          => 'ASC',
		'orderby'        => 'menu_order'
	 );
	$query = new WP_Query( $args  );
	if ( $query->have_posts()) {
		while ( $query->have_posts() ) : $query->the_post(); ?>
			<?php get_template_part('parts/faq-accordion'); ?>
		<?php endwhile; ?>
	<?php } // end query ?>
<?php if(is_page(2408)) : ?>
	<div class="container bottom-padding">
		<div class="row">
			<div id="support-videos" class="col-12 hero-headline bottom-padding-15">Support Videos</div>
			<div class="col-12 col-md-8">
				<iframe src="https://player.vimeo.com/video/274948540?byline=0" width="640" height="360" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
			</div><!-- col-12 -->
			<div class="col-12 col-md-10">
				<span class="poduct-headline">How to use the SleepScore App</span>
			</div><!-- col-12 -->
		</div><!-- row -->
	</div><!-- container -->


	<?php
	wp_reset_query();
	$args = array(
		'post_type'      => 'support_videos',
		'tax_query' => array(
			array(
				'taxonomy' => 'video_cats',
				'field'    => 'slug',
				'terms'    => 'hide',
				'operator' => 'NOT IN',
			),
		),
		'posts_per_page' => -1,
		'order'          => 'ASC',
		'orderby'        => 'menu_order'
	 );
	$query = new WP_Query( $args  );
	if ( $query->have_posts()) { ?>
		<div class="container support-videos bottom-padding">
			<div class="row bottom-padding">
			<?php while ( $query->have_posts() ) : $query->the_post(); ?>
				<?php get_template_part('parts/support-videos'); ?>
			<?php endwhile; ?>
			</div><!-- row -->
			<div class="row">
				<?php get_template_part('inc/social-sharing'); ?>
			</div><!-- row -->
		</div><!-- container -->
	<?php } // end query ?>
<?php endif; // end if is_page(2048) ?>
<?php } // end if is page ?>

</main>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
