<div class="col-12 col-lg-3 text-center">
	<a class="fancybox-iframe fancybox-vimeo" href="<?php the_field('link_url_to_video'); ?>">
		<?php $image = get_field('thumbnail_image');
		if( !empty($image) ): ?>
		<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
		<?php endif; ?>
		<span class="poduct-headline"><?php the_title(); ?></span>
	</a>
</div><!-- col -->
