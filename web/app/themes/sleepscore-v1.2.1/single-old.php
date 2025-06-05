<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Single post
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
	<nav class="container-fluid fixed-sub-nav">
		<div class="row">

				<div class="col-2 d-inline-block d-sm-none">
					<button id="ss-nav-trigger" class="ss-cat-nav-branding">
						<?php /*?><span class="square-24 fa fa-warehouse"></span><?php */?>
						<span class="square-24 fa fa-chevron-down"></span>
					</button><!-- ss-cat-nav-branding -->
				</div>
				<div id="ss-cat-nav-wrap" class="container">
					<div class="row">
						<?php if(have_rows('landing_page_menu_repeater', 'option')): ?>
							<?php while(have_rows('landing_page_menu_repeater', 'option')): the_row(); ?>
								<a id="<?php the_sub_field('menu_id', 'option'); ?>" class="landing-menu text-center <?php the_sub_field('menu_classes', 'option'); ?>" href="<?php the_sub_field('menu_link', 'option'); ?>">
									<div class="menu-text" ><?php the_sub_field('menu_text', 'option'); ?></div>
								</a>
							<?php endwhile; ?>
						<?php endif; ?>
						<div class="d-none d-sm-inline-block col-sm-4">
							<div class="search-form-wrapper">
								<?php get_search_form(); ?>
							</div>
						</div>
					</div><!-- row -->
				</div><!-- ss-cat-nav-wrap -->
				<div class="d-inline-block d-sm-none col-10">
					<div class="search-form-wrapper">
						<?php get_search_form(); ?>
					</div>
				</div>

		</div><!-- row -->
	</nav>
	<main class="top-padding-90">
		<?php $url = wp_get_attachment_url( get_post_thumbnail_id($post->ID) ); ?>
		<article class="container">
			<div id="post-<?php the_ID(); ?>" class="row bottom-padding">
				<div class="col-12 col-sm-12 text-center bottom-padding-15">
					<h1 class="hero-headline"><?php the_title(); ?></h1>

				</div>
				<div class="col-12 col-sm-12" >
					<?php the_post_thumbnail( 'full', array(
						'class' => 'img-fluid',
						'alt'   => trim(strip_tags( $wp_postmeta->_wp_attachment_image_alt ))
					) ); ?>
				</div>
			</div><!-- row -->
		</article><!-- container -->

		<article class="container">
			<div class="row">
				<div class="col-12 col-lg-8">
					<div class="article-content">
						<?php the_content(); ?>

						<?php if(have_rows('product_repeater')):?>
						<div class="row">
							<?php while(have_rows('product_repeater')): the_row(); ?>
							<?php $id = get_sub_field('product_post_id'); ?>

							<a class="<?php the_sub_field('product_classes'); ?>" href="<?php echo get_permalink( $id ); ?>">
								<div class="h250 bg-img bottom-padding-15" style="background-image:url(<?php the_field('product_homepage_img', $id); ?>);">

								</div>
								<div class="product-title">
									<?php echo get_the_title( $id ); ?>
								</div>
							</a>
							<?php endwhile; ?>
						</div>
						<?php endif; ?>
						<?php get_template_part('parts/shopify'); ?>
						<?php get_template_part( 'inc/meta' ); ?>
					</div><!-- content -->
					<?php // comments_template(); ?>
					<?php get_template_part('inc/nav'); ?>
				</div><!-- cols -->
				<div class="col-12 col-lg-4">
					<?php get_sidebar(); ?>
				</div><!-- cols -->
				<?php get_template_part('inc/social-sharing'); ?>
			</div><!-- row -->

		</article><!-- container -->
	</main>
	<?php if(!get_field('hide_hr_line')) : ?>
		<div class="container">
			<div class="row top-padding">
				<hr class="col-10 offset-1">
			</div>
		</div>
	<?php endif; ?>
	<?php if(get_field('hide_footer_banner')) {
		get_template_part('parts/custom-footer-banner');
	} else {
		get_template_part('parts/pre-footer-banner');
	} ?>
<?php endwhile; endif; ?>
<?php get_footer(); ?>
