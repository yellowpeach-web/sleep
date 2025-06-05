<?php
/*
Template Name: Homepage
*/
?>
<?php get_header(); ?>
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Home page V2
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<main>
<?php $detect = new Mobile_Detect; ?>

<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
	<?php // ======================================================================= hero section ?>
		<div class="container relative d-block">
			<a class="lightbox-video fancybox-iframe fancybox-vimeo" href="<?php the_field('home_video_popup_video'); ?>">
				<span class="headline font-24"><span class="fa fa-play-circle"></span>&nbsp;<?php the_field('home_video_popup_text'); ?></span>
			</a>
		</div>
	<?php if(get_field('use_carousel_instead')) {
		get_template_part('parts/carousel-home');
	} else { ?>

		<?php if( $detect->isMobile() && !$detect->isTablet() ){ ?>
			<div class="home-hero <?php the_field('hero_bs_classes'); ?>" style="background-image:url('<?php the_field('home_hero_background_image_mobile'); ?>');" >
		<?php } else { ?>
			<div class="home-hero <?php the_field('hero_bs_classes'); ?>" style="background-image:url('<?php the_field('home_hero_background_image'); ?>');" >
		<?php } ?>
			<div class="<?php the_field('hero_bs_wrapper_classes'); ?>">
				<div class="<?php the_field('home_hero_headline_classes'); ?>">
					<?php the_field('home_hero_headline'); ?>
				</div>
				<div class="<?php the_field('home_hero_subheading_classes'); ?>">
					<?php the_field('home_hero_subheading'); ?>
				</div>
				<div class="<?php the_field('home_hero_body_copy_classes'); ?>">
					<?php the_field('home_hero_body_copy'); ?>
				</div>
				<a id="home-hero-app-store" class="app-button" target="_blank" href="<?php the_field('app_store_link_url'); ?>">
					<?php
					$image = get_field('app_store_image');
					if( !empty($image) ): ?>
					<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
					<?php endif; ?>
				</a>
				<?php if(get_field('google_play_link_url')): ?>
				<a class="app-button" target="_blank" href="<?php the_field('google_play_link_url'); ?>">
				<?php /* ?><a class="app-button android pointer" onclick=_dcq.push(["showForm",{id:"109316435"}]);><?php */ ?>
					<?php
					$image = get_field('google_play_image');
					if( !empty($image) ): ?>
					<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
					<?php endif; ?>
				</a>
				<?php endif; ?>
			</div><!-- home-hero-content-wrapper -->
		</div><!-- Static home hero -->

	<?php } // end use carousel instead ?>

	<div id="media-slideshow" class="col-12 col-md-8 offset-md-2 top-padding d-flex align-items-center"><!-- xxxxxxxxxxxxxxxxxxxxxx sponsors -->
			<div class="owl-carousel">
			<?php if(have_rows('media_slide_repeater')): ?>
				<?php while(have_rows('media_slide_repeater')): the_row(); ?>
					<div class="slide col">
						<?php $image = get_sub_field('media_logo');
						if( !empty($image) ): ?>
							<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
						<?php endif; ?>
					</div>
				<?php endwhile; ?>
			<?php endif; ?>
            </div><!-- media carousel -->
	</div>


	<div class="container-fluid">
		<div class="container home-section-2 bottom-padding relative text-center">
			<?php if(have_rows('home_bootstrap_columns_s2')): ?>
			<?php while(have_rows('home_bootstrap_columns_s2')): the_row(); ?>
				<?php if(get_sub_field('start_row')): ?>
					<div class="<?php the_sub_field('row_classes'); ?>">
				<?php endif; ?>
					<div class="<?php the_sub_field('bootstrap_classes'); ?>" <?php if(get_sub_field('background_color')):?>style="background-color:<?php the_sub_field('background_color');?>;"<?php endif; ?>>
						<?php the_sub_field('content'); ?>
					</div>
				<?php if(get_sub_field('end_row')): ?>
					</div><!-- row -->
				<?php endif; ?>
			<?php endwhile; ?>
			<?php endif; ?>
		</div><!-- section 2 -->
	</div><!-- section fluid -->


	<div class="container-fluid home-section-4 top-bottom-padding relative" <?php if(get_field('s4_container_background_color')):?>style="background-color:<?php the_field('s4_container_background_color');?>;"<?php endif; ?>>
		<?php if(have_rows('home_bootstrap_columns_s4')): ?>
		<div class="container">
			<?php while(have_rows('home_bootstrap_columns_s4')): the_row(); ?>
				<?php if(get_sub_field('start_row')): ?>
					<div class="row">
				<?php endif; ?>
					<div class="<?php the_sub_field('bootstrap_classes'); ?>" <?php if(get_sub_field('background_color')):?>style="background-color:<?php the_sub_field('background_color');?>;"<?php endif; ?>>
						<?php the_sub_field('content'); ?>
					</div>
				<?php if(get_sub_field('end_row')): ?>
					</div><!-- row -->
				<?php endif; ?>
			<?php endwhile; ?>
		</div><!-- container -->
		<?php endif; ?>
	</div><!-- section 4 -->

	<div class="container-fluid">
		<div class="container home-section-2 bottom-padding relative text-center">
			<?php if(have_rows('home_bootstrap_columns_s5')): ?>
			<?php while(have_rows('home_bootstrap_columns_s5')): the_row(); ?>
				<?php if(get_sub_field('start_row')): ?>
					<div class="<?php the_sub_field('row_classes'); ?>">
				<?php endif; ?>
					<div class="<?php the_sub_field('bootstrap_classes'); ?>" <?php if(get_sub_field('background_color')):?>style="background-color:<?php the_sub_field('background_color');?>;"<?php endif; ?>>
						<?php the_sub_field('content'); ?>
					</div>
				<?php if(get_sub_field('end_row')): ?>
					</div><!-- row -->
				<?php endif; ?>
			<?php endwhile; ?>
			<?php endif; ?>
		</div><!-- section 2 -->
	</div><!-- section fluid -->

<?php endwhile; endif; // main loop ?>
</main>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
