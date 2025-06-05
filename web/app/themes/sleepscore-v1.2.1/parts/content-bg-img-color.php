<div class="content-bg-img bg-img animated animate-on-scroll vh70 d-flex align-items-center" data-animation="fadeIn" style="
	<?php if(get_sub_field('background_image')): ?>
		background-image:url(<?php the_sub_field('background_image'); ?>); &nbsp;
	<?php endif; ?>
	<?php if(get_sub_field('bg_color')): ?>
		background-color:<?php the_sub_field('bg_color'); ?>; &nbsp;
	<?php endif; ?>
">
	<div class="container">
		<div class="animated animate-on-scroll <?php the_sub_field('bg_img_column_classes'); ?>" data-animation="fadeInUp" data-animation-delay=".1s">
			<?php if(get_sub_field('headline')): ?>
				<div class="<?php the_sub_field('headline_classes'); ?>"><?php the_sub_field('headline'); ?></div>
			<?php endif; ?>
			<?php if(get_sub_field('sub_heading')): ?>
				<div class="<?php the_sub_field('sub_heading_classes'); ?>"><?php the_sub_field('sub_heading'); ?></div>
			<?php endif; ?>
			<?php if(get_sub_field('body_copy')): ?>
				<div class="<?php the_sub_field('body_copy_classes'); ?>"><?php the_sub_field('body_copy'); ?></div>
			<?php endif; ?>
			
			<?php if(get_sub_field('cta_link_url')) { ?>
				<a class="<?php the_sub_field('cta_button_classes'); ?>" href="<?php the_sub_field('cta_link_url'); ?>" 
					<?php if( get_sub_field('cta_target')): ?> target="_blank"<?php endif; ?>>
					<?php the_sub_field('cta_button_text'); ?>
				</a>
				<?php } elseif(get_sub_field('cta_button_text')) { ?>
					<span class="<?php the_sub_field('cta_button_classes'); ?>"><?php the_sub_field('cta_button_text'); ?></span>
			<?php } ?>
		</div><!-- bg-img-container -->
	</div><!-- container -->
</div><!-- bg-img-cta -->