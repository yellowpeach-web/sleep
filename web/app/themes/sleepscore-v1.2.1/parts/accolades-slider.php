<?php if(get_sub_field('alt_phone')){ ?>

	<?php $detect = new Mobile_Detect; ?>
	<?php if( $detect->isMobile() && !$detect->isTablet() ){ ?>
		<?php if(get_sub_field('display_on_cell_only')) : ?>

		<div class="container-fluid bottom-padding" <?php if(get_sub_field('slider_bg_color')): ?>style="background-color:<?php the_sub_field('slider_bg_color'); ?>" <?php endif; ?>>
			<div id="media-slideshow" class="<?php the_sub_field('slider_classes'); ?>">
				<div class="owl-carousel">
				<?php if(have_rows('media_logo_repeater')): ?>
					<?php while(have_rows('media_logo_repeater')): the_row(); ?>
						<?php if(get_sub_field('link_url')): ?>
						<a href="<?php the_sub_field('link_url'); ?>" <?php if(get_sub_field('target')): ?> target="_blank" rel="nofollow"<?php endif; ?>>
						<?php endif; ?>
						<div class="slide col text-center">
							<div class="bottom-padding-15">
								<?php the_sub_field('quote'); ?>
							</div>
							<?php $image = get_sub_field('media_logo');
							if( !empty($image) ): ?>
								<img class="img-fluid padding-left-right-60" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
							<?php endif; ?>
						</div>
						<?php if(get_sub_field('link_url')): ?>
						</a>
						<?php endif; ?>
					<?php endwhile; ?>
				<?php endif; ?>
				</div><!-- media carousel -->
			</div>
		</div><!-- container-fluid -->

	<?php endif; // end if display on cell only ?>
	<?php } else { // if not mobile ?>

		<div class="container-fluid bottom-padding" <?php if(get_sub_field('slider_bg_color')): ?>style="background-color:<?php the_sub_field('slider_bg_color'); ?>" <?php endif; ?>>
			<div id="media-slideshow" class="<?php the_sub_field('slider_classes'); ?>">
				<div class="owl-carousel">
				<?php if(have_rows('media_logo_repeater')): ?>
					<?php while(have_rows('media_logo_repeater')): the_row(); ?>
						<?php if(get_sub_field('link_url')): ?>
						<a href="<?php the_sub_field('link_url'); ?>" <?php if(get_sub_field('target')): ?> target="_blank" rel="nofollow"<?php endif; ?>>
						<?php endif; ?>
						<div class="slide col text-center">
							<div class="bottom-padding-15">
								<?php the_sub_field('quote'); ?>
							</div>
							<?php $image = get_sub_field('media_logo');
							if( !empty($image) ): ?>
								<img class="img-fluid padding-left-right-60" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
							<?php endif; ?>
						</div>
						<?php if(get_sub_field('link_url')): ?>
						</a>
						<?php endif; ?>
					<?php endwhile; ?>
				<?php endif; ?>
				</div><!-- media carousel -->
			</div>
		</div><!-- container-fluid -->
	<?php }  // end else if not mobile ?>

<?php } else { // if not using alt-phone bypass then display this... ?>
	<div class="container-fluid bottom-padding" <?php if(get_sub_field('slider_bg_color')): ?>style="background-color:<?php the_sub_field('slider_bg_color'); ?>" <?php endif; ?>>
			<div id="media-slideshow" class="<?php the_sub_field('slider_classes'); ?>">
				<div class="owl-carousel">
				<?php if(have_rows('media_logo_repeater')): ?>
					<?php while(have_rows('media_logo_repeater')): the_row(); ?>
						<?php if(get_sub_field('link_url')): ?>
						<a href="<?php the_sub_field('link_url'); ?>" <?php if(get_sub_field('target')): ?> target="_blank" rel="nofollow"<?php endif; ?>>
						<?php endif; ?>
						<div class="slide col text-center">
							<div class="bottom-padding-15">
								<?php the_sub_field('quote'); ?>
							</div>
							<?php $image = get_sub_field('media_logo');
							if( !empty($image) ): ?>
								<img class="img-fluid padding-left-right-60" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
							<?php endif; ?>
						</div>
						<?php if(get_sub_field('link_url')): ?>
						</a>
						<?php endif; ?>
					<?php endwhile; ?>
				<?php endif; ?>
				</div><!-- media carousel -->
			</div>
		</div><!-- container-fluid -->
<?php } ?>
