<div class="container-fluid vertical-align" style="background-color:<?php the_sub_field('scroller_static_column_bg_color'); ?>;">
	<div class="col-12 col-md-3">
		<?php the_sub_field('scroller_static_column'); ?>
		<button class="scroller-trigger scroll-left"><span class="<?php the_sub_field('scroller_trigger_icon'); ?>"></span></button>
	</div>
	<div class="scroller-window col-12 col-md-9 no-padding">
		<div class="scroller-wrapper" style="width:<?php the_sub_field('scroller_wrapper_width'); ?>;">
		<?php if(have_rows('scroller_carousel_repeater')): ?>
			<?php while(have_rows('scroller_carousel_repeater')): the_row(); ?>
				<div class="slides">
				<?php if(have_rows('slide_column_repeater')): ?>
					<?php while(have_rows('slide_column_repeater')): the_row(); ?>
						<div class="<?php the_sub_field('column_classes'); ?>" style="background-image:url('<?php the_sub_field('background_image'); ?>');">
							<div class="headline"><?php the_sub_field('headline'); ?></div>
						</div><!-- column Classes -->
					<?php endwhile; ?>
				<?php endif; ?>
				</div><!-- slide -->
			<?php endwhile; ?>
		<?php endif; ?>
		</div><!-- scroller wrapper -->
	</div>
</div><!-- container-fluid -->
	