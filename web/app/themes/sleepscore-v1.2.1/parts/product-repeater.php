<?php if(have_rows('product_repeater')):?>
<div class="container">
	<div class="row">
		<?php while(have_rows('product_repeater')): the_row(); ?>
		<?php $id = get_sub_field('product_post_id'); ?>
		<a class="<?php the_sub_field('product_classes'); ?>" href="<?php echo get_permalink( $id ); ?>">
			<div class="h250 bg-img bottom-padding-15" style="background-image:url(<?php the_field('product_homepage_img', $id); ?>);">
				
			</div>
			<div class="product-title">
				<?php echo get_the_title( $id ); ?>
			</div>
		</a>
		<?php endwhile; ?>
	</div><!-- row -->
</div><!-- container -->
<?php endif; ?>