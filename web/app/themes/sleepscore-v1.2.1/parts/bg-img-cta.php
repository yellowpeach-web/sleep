<div class="bg-img-cta bg-img <?php the_sub_field('bg_img_container_classes'); ?>" style="
	<?php if(get_sub_field('background_image')): ?>
		background-image:url(<?php the_sub_field('background_image'); ?>); 
	<?php endif; ?>
	<?php if(get_sub_field('bg_color')): ?>
		background-color:<?php the_sub_field('bg_color'); ?>; 
	<?php endif; ?>
">
	<div class="container">
		<div class="row <?php the_sub_field('bg_img_row_classes'); ?>">
			<div class="<?php the_sub_field('bg_img_column_classes'); ?>">
				<div class="<?php the_sub_field('headline_classes'); ?>">
					<?php the_sub_field('headline'); ?>
				</div>
				<?php if(get_sub_field('sub_heading_classes')): ?>
					<div class="<?php the_sub_field('sub_heading_classes'); ?>">
						<?php the_sub_field('sub_heading'); ?>
					</div>
				<?php endif; ?>

				<?php if(get_sub_field('body_copy')): ?>
					<div class="<?php the_sub_field('body_copy_classes'); ?>">
						<?php the_sub_field('body_copy'); ?>
					</div>
				<?php endif; ?>

				<?php if(get_sub_field('cta_link_url')) { ?>
					<a class="cta-link <?php the_sub_field('cta_link_classes'); ?>" href="<?php the_sub_field('cta_link_url'); ?>"&nbsp; 
						<?php if( get_sub_field('cta_target')): ?>
							target="_blank"
						<?php endif; ?>>
						<span><?php the_sub_field('cta_button_text'); ?></span>
					</a>
					<?php } elseif(get_sub_field('cta_button_text')) { ?>
						<span class="cta-text"><?php the_sub_field('cta_button_text'); ?></span>
				<?php } ?>
			</div><!-- column -->
		</div><!-- bg-img-container -->
	</div><!-- container -->
</div><!-- bg-img-cta -->