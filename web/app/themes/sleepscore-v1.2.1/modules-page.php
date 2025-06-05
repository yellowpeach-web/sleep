<?php
/*
Template Name: Modules
*/
?>
<?php 
	
	$is_squeeze_page = get_field('squeeze_page');
	$page_theme = get_field('page_theme'); 

	$main_class_names = [];

	if ($is_squeeze_page) {
		$main_class_names[] = 'squeeze-header';
	}

	if ($page_theme === 'Light') {
		wp_enqueue_style( 'light-theme-css', get_template_directory_uri() . '/css/light-theme.min.css?v=2.0.5', array(), '', 'screen');
		$main_class_names[] = 'light-theme';
	}
	
?>
<?php if($is_squeeze_page) {
	get_header('landing');
 } else {
	get_header();
} ?>

<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Modules page template
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->


<main class="<?php echo implode(' ', $main_class_names); ?>">

		<article class="modules-page <?php the_field('module_page_container_classes');?>">
		<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
			<div  id="post-<?php the_ID(); ?>" class="post <?php the_field('module_page_row_classes');?>">

				<?php
				if(have_rows('page_modules')):
					while(have_rows('page_modules')): the_row();
						if(get_row_layout() == "video_cta"):
							get_template_part('video_cta');
						elseif(get_row_layout() == 'bg_image_with_content_and_cta'):
							get_template_part('parts/bg-img-cta');
						elseif(get_row_layout() == 'bootstap_column_flexible_layout'):
							get_template_part('parts/bootstrap-column');
						elseif(get_row_layout() == 'content_bg_img_color'):
							get_template_part('parts/content-bg-img-color');
						elseif(get_row_layout() == 'executive_team'):
							get_template_part('parts/executive-team');
						elseif(get_row_layout() == 'scroller_carousel'):
							get_template_part('parts/scroller-carousel');
						elseif(get_row_layout() == 'tab_content'):
							get_template_part('parts/tab-content');
						elseif(get_row_layout() == 'code_block'):
							the_sub_field('html_code_block');
						elseif(get_row_layout() == 'carousel'):
							get_template_part('parts/carousel-module');
						elseif(get_row_layout() == 'bootstrap_carousel'):
							get_template_part('parts/bootstrap_carousel');
						elseif(get_row_layout() == 'display_sleepshop_products'):
							get_template_part('parts/product-repeater');
						elseif(get_row_layout() == 'display_shopify_products'):
							get_template_part('parts/shopify');
						elseif(get_row_layout() == 'display_related_articles'):
							get_template_part('parts/related-articles');

						elseif(get_row_layout() == 'editable_url_parameters'):
							get_template_part('parts/url-parameters');

						elseif(get_row_layout() == 'add_sidebar'):
							get_template_part('parts/add-sidebar');
						elseif(get_row_layout() == 'end_sidebar'):
							get_template_part('parts/end-sidebar');

						elseif(get_row_layout() == 'hero_slideshow'):
							get_template_part('parts/hero-slideshow');
						elseif(get_row_layout() == 'media_accolades_slider'):
							get_template_part('parts/accolades-slider');

						elseif(get_row_layout() == 'custom_footer_banner_ad'):
							get_template_part('parts/custom-footer-banner');
						endif;
					endwhile;
				endif;
				?>
				<?php if(is_page('sleepscore-app')){
					get_template_part('parts/feature-list-repeater');
				} elseif(is_page('the-science-2')) {
					get_template_part('parts/white-papers');
				}// if is_page ?>


			</div><!--post-->
		<?php endwhile; endif; ?>
	</article>

</main>
<?php if(get_field('show_sharing')) : ?>
	<div class="text-center">
		<?php get_template_part('inc/social-sharing'); ?>
	</div>
<?php endif; ?>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
