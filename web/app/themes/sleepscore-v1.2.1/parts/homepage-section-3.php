<div class="container home-section-3 top-bottom-padding relative">
	<?php if(have_rows('home_bootstrap_columns_s3', 2642)): ?>
	<?php while(have_rows('home_bootstrap_columns_s3', 2642)): the_row(); ?>
		<?php if(get_sub_field('start_row', 2642)): ?>
			<div class="row">
		<?php endif; ?>
			<div class="<?php the_sub_field('bootstrap_classes', 2642); ?>" <?php if(get_sub_field('background_color', 2642)):?>style="background-color:<?php the_sub_field('background_color', 2642);?>;"<?php endif; ?>>
				<?php the_sub_field('content', 2642); ?>
			</div>
		<?php if(get_sub_field('end_row', 2642)): ?>
			</div><!-- row -->
		<?php endif; ?>
	<?php endwhile; ?>
	<?php endif; ?>
</div><!-- section 3 -->