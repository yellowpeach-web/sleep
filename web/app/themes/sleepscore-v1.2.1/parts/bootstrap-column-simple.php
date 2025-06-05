<?php if(get_sub_field('full_screen_background_image')) { ?>
	<div id="<?php the_sub_field('container_id'); ?>" class="container-fluid bg-img <?php the_sub_field('bg_image_classes'); ?>" 
		<?php $image = get_sub_field('full_screen_background_image'); ?>
		style="background-image:url('<?php the_sub_field('full_screen_background_image'); ?>'); <?php if(get_sub_field('bg_color')) : ?>background-color:<?php the_sub_field('bg_color'); ?>;<?php endif; ?> "><!-- container-fluid -->
<?php } elseif(get_sub_field('bg_color')) { ?>
	<div id="<?php the_sub_field('container_id'); ?>" class="container<?php if(get_sub_field('fluid')):?>-fluid<?php endif; ?> no-padding <?php the_sub_field('bg_image_classes'); ?>" style="background-color:<?php the_sub_field('bg_color'); ?>;"><!-- container-fluid -->
<?php } elseif(get_sub_field('fluid')) { ?>
	<div class="container-fluid <?php the_sub_field('bg_image_classes'); ?>">
<?php } else { ?>
	<div class="container <?php the_sub_field('bg_image_classes'); ?>">
<?php } ?>
	<?php if(get_sub_field('nested_container')): ?>
		<div class="container">
	<?php endif; ?>
	<?php if(have_rows('bootstrap_column')): ?>
		<?php while(have_rows('bootstrap_column')): the_row(); ?>

				<?php if(get_sub_field('start_row')): ?>
				<div class="<?php the_sub_field('row_classes'); ?>">
				<?php endif; ?>
					<div id="<?php the_sub_field('bootstrap_id'); ?>" class="<?php the_sub_field('bootstrap_classes'); ?>">
						<?php the_sub_field('boostrap_content'); ?>
					</div><!-- Bootstrap Content -->
				<?php if(get_sub_field('end_row')): ?>
				</div><!-- row -->
				<?php endif; ?>

		<?php endwhile; ?>
	<?php endif; ?>
	<?php if(get_sub_field('nested_container')): ?>
		</div><!-- nested container -->
	<?php endif; ?>
</div><!-- container -->