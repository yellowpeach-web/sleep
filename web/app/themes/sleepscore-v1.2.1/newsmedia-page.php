<?php
/*
Template Name: News Media
*/
?>
<?php wp_enqueue_style( 'light-theme-css', get_template_directory_uri() . '/css/light-theme.min.css?v=2.0.5', array(), '', 'screen'); ?>
<?php get_header(); ?>
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
News and Media - page
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php /* <style>html { scroll-padding-top: 150px; }</style> */ ?>
<nav id="ss-cat-nav" class="container-fluid fixed-sub-nav">
	<div class="row">
		<div class="col-3 d-block d-lg-none">
			<button id="ss-nav-trigger" class="ss-cat-nav-branding">
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav-branding -->
		</div>
		<div id="ss-cat-nav-wrap" class="container">
			<div class="row">
				<?php if(have_rows('press_nav_repeater')): ?>
					<?php while(have_rows('press_nav_repeater')): the_row(); ?>
						<a class="ss-cat-nav col-6 col-lg text-center scrolling-link" href="<?php the_sub_field('link'); ?>">
							<span class=""><?php the_sub_field('title'); ?></span>
						</a>
					<?php endwhile; ?>
				<?php endif; ?>
				<?php /* ?><div class="d-none d-lg-inline-block col-lg-4">
					<div class="search-form-wrapper">
						<?php get_search_form(); ?>
					</div>
				</div>	<?php */ ?>
			</div><!-- row -->
		</div><!-- ss-cat-nav-wrap -->
		<?php /* ?><div class="d-block d-lg-none col-9">
			<div class="search-form-wrapper">
				<?php get_search_form(); ?>
			</div>
		</div><?php */ ?>
	</div><!-- row -->
</nav>

<div class="light-theme">
	<div class="container">
		<?php
			$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
			$args = array(
				'post_type' => 'news_media',
				'tax_query' => array(
					array(
						'taxonomy' => 'news_media_categories',
						'field'    => 'slug',
						'terms'    => 'press-releases',
					),
				),
				'paged' => $paged,
				'posts_per_page' => 6
			);
		?>
		<div class="row top-padding-60">
			<h2 class="col-12 hero-headline text-center">Press Releases</h2>
		</div>
		<?php $wp_query = new WP_Query($args); ?>
		<?php if($wp_query->have_posts()): ?>
			<style type="text/css">
				#releases a.border-box {
					background-color: rgba(23, 27, 69, 0.6);
					border: 2px solid #fff;
					margin-bottom: 20px;
					padding: 10px;
				}
				#releases a.border-box:first-child {
					border-width: 2px;
				}
				#releases a.border-box:hover {
					background-color: rgba(23, 27, 69, 0.8);
				}
			</style>
			<div id="releases" class="row bottom-padding"><?php $which_release=1; ?>
			<?php while ($wp_query->have_posts()): $wp_query->the_post(); ?>

					<a  class="col-12 bordered-box" href="<?php the_permalink(); ?>"<?php print ' id="release_' . $which_release++ . '"'; ?>><span class="pull-right fa fa-arrow-right"></span>
						<span class="font-20"><?php the_title(); ?></span> - <span class="font-12"><?php the_time('F jS, Y') ?></span>
					</a><!-- col -->

			<?php endwhile;  wp_reset_postdata();  ?>
			<a class="text-right col-12" href="/media/press-releases">View all Press Releases</a>
			</div><!-- row -->
		<?php endif; ?>

	</div><!-- container-->

	<div class="container light-theme"><!-- was "main" -->
		<?php
			$args = array(
				'post_type' => 'news_media',
				'tax_query' => array(
					array(
						'relation' => 'AND',
						array(
							'taxonomy' => 'news_media_categories',
							'field'    => 'slug',
							'terms'    => array( 'news-articles' ),
						),
						array(
							'taxonomy' => 'news_media_categories',
							'field'    => 'slug',
							'terms'    => array( 'sticky-news-article' ),
						),
					),
				),
				'posts_per_page' => 12
			);
		?>
		<div class="row">
			<div class="col-12 hero-headline text-center">In the News</div>
		</div>
		<?php $wp_query = new WP_Query($args); ?>
		<?php if($wp_query->have_posts()): ?>
			<div id="news" class="row top-padding">
			<?php while ($wp_query->have_posts()): $wp_query->the_post(); ?>
				<div class="col-12 col-md-4 bottom-padding-60 text-center">
					<div class="row">
						<div class="col-12 col-md-6 offset-md-3 bottom-padding-15">
							<a class="" href="<?php the_field('news_articles_link'); ?>" target="_blank">
							<?php the_post_thumbnail( 'medium', array(
								'class' => 'img-fluid',
								'alt'   => trim(strip_tags( $wp_postmeta->_wp_attachment_image_alt ))
							) ); ?>
							</a>
						</div><!-- col -->
					</div><!-- inner row -->

					<a class="font-18 bottom-padding-15 text-center" href="<?php the_field('news_articles_link'); ?>" target="_blank">"<?php the_title(); ?>"</a>
				</div><!-- col wrap -->
			<?php endwhile;  wp_reset_postdata();  ?>
			</div><!-- row -->
			<div class="row">
				<a class="text-right col-12 bottom-padding" href="/media/news-articles">View more coverage <span class="fa fa-chevron-right"></span></a>
			</div>
		<?php endif; ?>
	</div><!-- was "main" -->

	<div class="container-fluid pb-5">
		<div class="container">
			<div class="row top-padding">
				<h2 class="col-12 hero-headline text-center">Press Kit</h2>
				<div id="assets" class="row mx-auto">
				<div class="col-12 text-center">
					<p>Download SleepScore Labs logos here.</p>
					<a class="cta-button bottom-padding" target="_blank" href="https://www.sleepscore.com/wp-content/uploads/2022/04/SleepScore_logo.zip">Download All</a>
				</div>
				</div><!-- row -->
			</div>
		</div><!-- container-->
	</div><!-- container-fluid -->

	<div class="container">
		<div class="row top-padding">
			<h2 class="col-12 hero-headline text-center">Press inquiries:</h2>
		</div>
		<div id="contact" class="row bottom-padding">
			<div class="col-12 text-center">
				<?php // echo do_shortcode('[contact-form-7 id="3620" title="Press Contact Form"]'); ?>
				<div class="product-headline"><a href="mailto:press@sleepscorelabs.com">press@sleepscorelabs.com</a></div>
			</div>
		</div><!-- row -->
	</div><!-- container-->

</div>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
