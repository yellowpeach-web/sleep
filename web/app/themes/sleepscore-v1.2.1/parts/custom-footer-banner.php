<?php if(have_rows('custom_pre_footer_banner_repeater')): ?>
	<section id="download-now" class="container-fluid relative" style="background-color: <?php the_field('background_footer_column_color');?>;">
		<div class="container">
			<div class="row top-bottom-padding align-items-center">
			<?php while(have_rows('custom_pre_footer_banner_repeater')): the_row(); ?>
				<div class="<?php the_sub_field('feature_image_classes'); ?>">
					<?php $image = get_sub_field('feature_image');
					if( !empty($image) ): ?>
					<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
					<?php endif; ?>
				</div>
				<div class="<?php the_sub_field('content_classes'); ?>">
					<div class="<?php the_sub_field('headline_classes'); ?>"><?php the_sub_field('headline'); ?></div>
					<div class="<?php the_sub_field('body_copy_classes'); ?>"><?php the_sub_field('body_copy'); ?></div>
					<div class="buttons bottom-padding">
						<a id="footer-banner-app-store" class="app-button" target="_blank" href="<?php the_sub_field('app_store_link_url'); ?><?php echo $utmsource . $utmmedium  ; ?>&mt=8&ct=sleepscore-web">
							<?php
							$image = get_sub_field('app_store_image');
							if( !empty($image) ): ?>
							<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
							<?php endif; ?>
						</a>
						<?php if(get_sub_field('google_play_link_url')): ?>
						<?php /* ?><a class="app-button android pointer" onclick=_dcq.push(["showForm",{id:"109316435"}]);><?php */ ?>
						<a class="app-button" target="_blank" href="<?php the_sub_field('google_play_link_url'); ?>">
							<?php
							$image = get_sub_field('google_play_image');
							if( !empty($image) ): ?>
							<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
							<?php endif; ?>
						</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endwhile; ?>
			</div><!-- row -->
		</div><!-- container -->
	</section><!-- container - fluid -->
<?php endif; ?>
