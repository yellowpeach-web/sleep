<?php
	require_once('inc/layout-options.php');
	$layoutOptions = new LayoutOptions();
?>
<?php get_header(); ?>

<?php /* xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Single post
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx */ ?>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
	<?php
		if ($layoutOptions->isVisible('subNav')):
			include('parts/sub-nav.php');
		endif; 
		wp_enqueue_style( 'light-theme-css', get_template_directory_uri() . '/css/light-theme.min.css?v=2.0.5', array(), '', 'screen');
	?>
	<main class="light-theme <?= !$layoutOptions->isVisible('subNav') && !$layoutOptions->isVisible('header') ? 'no-bread-crumb': '' ?>">
		<?php $url = wp_get_attachment_url( get_post_thumbnail_id($post->ID) ); ?>
		<?php
		if(have_rows('single_modules')) {
			$fallbackArticle = TRUE;
			while(have_rows('single_modules')): the_row();
				if(get_row_layout() == "default_editors"):
					get_template_part('parts/default-editor');
				elseif(get_row_layout() == 'bootstap_column_flexible_layout'):
					get_template_part('parts/bootstrap-column-simple');
				elseif(get_row_layout() == 'two_columns'):
					get_template_part('parts/two-columns');
				elseif(get_row_layout() == 'code_block'):
					the_sub_field('html_code_block');
				elseif(get_row_layout() == 'bootstrap_carousel'):
					get_template_part('parts/bootstrap_carousel');
				elseif(get_row_layout() == 'display_shopify_products'):
					get_template_part('parts/shopify');
					$fallbackArticle = FALSE;
				elseif(get_row_layout() == 'display_related_articles'):
					get_template_part('parts/related-articles');
					$fallbackArticle = FALSE;
				elseif(get_row_layout() == 'add_sidebar'):
					get_template_part('parts/add-sidebar');
				elseif(get_row_layout() == 'end_sidebar'):
					get_template_part('parts/end-sidebar');
				elseif(get_row_layout() == 'custom_footer_banner_ad'):
					get_template_part('parts/custom-footer-banner');
				endif;
			endwhile; ?>

	<?php // Popular Posts related articles Fallback ========== ?>
	<?php if(!get_field('hide_related_articles')): ?>
			<?php if($fallbackArticle === TRUE): ?>
			<?php $hideNav === TRUE; ?>
			<div class="container<?php if(get_field('fluid_container_fallback_articles', 'option')): echo '-fluid'; endif; ?>" <?php if(get_field('container_bg_color_fallback_articles', 'option')): ?>style="background-color:<?php the_field('container_bg_color_fallback_articles', 'option'); ?>" <?php endif; ?>>
				<div class="row top-bottom-padding">
					<?php if(get_field('nested_container_fallback_articles', 'option')): ?>
						<div class="container">
							<div class="row">
					<?php endif; ?>
				<div class="col-12 headline bottom-padding-15 text-center">
					<?php the_field('title_for_fallback_articles', 'option'); ?>
				</div>
				<?php
					$cat = new WPSEO_Primary_Term( 'category',get_the_ID());
					$cat = $cat->get_primary_term();
					$cat = get_term($cat);
					$catSlug = $cat->slug;

				//$category = get_the_category();
				$popularpost = new WP_Query( array(
					'posts_per_page' => 3,
					'category_name' => $catSlug,
					'meta_key' => 'wpb_post_views_count',
					'orderby' => 'meta_value_num',
					'order' => 'DESC'  )
				);
				while ( $popularpost->have_posts() ) : $popularpost->the_post(); ?>
				<div class="col-12 col-md-4">
					<div class="text-center">
						<?php echo get_the_post_thumbnail( $post_id, 'blog-roll', array('class' => 'img-fluid')); ?>
					</div>
					<div class="bg-white vh20 padding-15">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						<div class="meta">
							<?php // display last update if post has been updated.
								$u_time = get_the_time('U');
								$u_modified_time = get_the_modified_time('U');
								if ($u_modified_time >= $u_time + 86400) {
								echo '<p>';
								the_modified_time('F jS, Y');
								echo "</p> "; }
							?>
						</div>
					</div>
				</div><!-- col -->
				<?php endwhile; ?>
				<?php if(get_field('nested_container_fallback_articles', 'option')): ?>
					</div><!-- row nested -->
				</div><!-- nested container -->
				<?php endif; ?>
			</div><!-- row primary -->
			</div><!-- container -->
			<?php endif; // end if fallback is true ?>
		<?php endif; // end if hide related articles is checked ?>
		<?php } else { // =========== use the original single.php content ?>
		<article id="container-sslarticle" class="container-lg pb-5">
			<?php if ($layoutOptions->isVisible('breadcrumbNav')): ?>
				<div class="row">
					<div id="breadcrumbs" class="col-12">
						<?php if ( function_exists( 'yoast_breadcrumb' ) ) { yoast_breadcrumb(); } ?>
					</div>
				</div>
			<?php endif; ?>
			<div class="row flex-column-reverse flex-md-row">
				<?php if ($layoutOptions->isVisible('sidebar')): ?>
				<div id="col-sslarticle-left" class="col-12 col-lg-3 col-md-3 <?= !$layoutOptions->isVisible('breadcrumbNav') ? 'col-no-breadcrumb' : '' ?>">
					<div class="sidebar-single-sleepscore">
						<?php get_sidebar(); ?>
					</div>
				</div><!-- cols -->
				<?php endif; ?>
				<div id="col-sslarticle-right" class="content col-12 <?= $layoutOptions->isVisible('sidebar') ? 'col-lg-9 col-md-9' : 'mt-2' ?>">
					<article>
						<div id="post-<?php the_ID(); ?>" class="row bottom-padding">
							<div class="col-12 col-sm-12 text-center bottom-padding-15">
								<h1 class="hero-headline"><?php the_title(); ?></h1>
							</div>
							<div class="col-12 col-sm-12" >
								<?php the_post_thumbnail( 'full', array(
									'class' => 'img-fluid',
									'loading' => 'eager'
								) ); ?>
								<div id="card-sslarticle" class="card border-0 top-padding-15">
									<div class="row">
										<div class="col-12">
											<?php get_template_part( 'inc/meta' ); ?>
										</div>
										<div class="col-12 mt-3">
											<?php get_template_part('inc/social-sharing'); ?>
										</div>
									</div>
								</div>
							</div>
						</div><!-- row -->
					</article><!-- container -->
					<?php the_content(); ?>
					<?php // get_template_part( 'inc/meta' ); ?>
					<?php // comments_template(); ?>
				</div><!-- content -->
			</div><!-- row -->
		</article><!-- article container -->
			<?php }	?>
		<?php // =========== END DEfault original single.php content ?>

		<?php if(get_field('show_products')): ?>
			<?php get_template_part('parts/default-products'); ?>
		<?php endif; ?>

		<?php if(($fallbackArticle === FALSE) && ($hideNav === TRUE)): ?>
			<?php get_template_part('inc/nav'); ?>
		<?php endif; ?>

	</main>
	<?php wp_reset_query(); ?>
	<?php if(!get_field('hide_hr_line')) : ?>
		<!-- <div class="container">
			<div class="row top-padding">
				<hr class="col-10 offset-1">
			</div>
		</div> -->
	<?php endif; ?>
	<?php if ($layoutOptions->isVisible('appBanner')): ?>
		<?php if(get_field('hide_footer_banner')) {
			get_template_part('parts/custom-footer-banner');
		} else {
			get_template_part('parts/pre-footer-banner');
		} ?>
	<?php endif; ?>
<?php endwhile; endif; ?>
<?php get_footer(); 
