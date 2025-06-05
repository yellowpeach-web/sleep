<div class="container-fluid">
	<div class="col-12 col-md-6 offset-md-3 top-bottom-padding d-flex align-items-center">
		<div class="<?php the_sub_field('carousel_trigger_class'); ?>">
		<?php $GLOBALS['$numberOfSlides'] = get_sub_field('how_many_slides'); ?>

		<?php if(get_sub_field('quotes')) { ?>

			<?php if(have_rows('carousel_repeater')): ?>
				<?php while(have_rows('carousel_repeater')): the_row(); ?>
					<div class="slide">
						<div class="row">
							<div class="col-2">
								<img class="img-fluid wp-image-3502 alignleft" src="https://sleepscore.com/wp-content/uploads/2018/06/quote.png" alt="" width="100" height="100" />
							</div>
							<div class="col-10">
								<?php the_sub_field('slide_content'); ?>
							</div>
						</div>
					</div>
				<?php endwhile; ?>
			<?php endif; ?>
		<?php } else { ?>
			<?php if(have_rows('carousel_repeater')): ?>
				<?php while(have_rows('carousel_repeater')): the_row(); ?>
					<div class="slide">
						<div class="row">
							<?php if(get_sub_field('slide_content_classes')) { ?>
							<div class="<?php the_sub_field('slide_content_classes'); ?>">
							<?php } else { ?>
							<div class="col-12">
							<?php } ?>
								<?php the_sub_field('slide_content'); ?>
							</div>
						</div>
					</div>
				<?php endwhile; ?>
			<?php endif; ?>
		<?php } ?>

		</div><!-- module carousel -->
	</div><!-- col -->
</div><!-- container -->
<?php function after_jquery() { ?>

	<script>
		$(".owl-carousel").owlCarousel({
			items: 	<?php echo $GLOBALS['$numberOfSlides']; ?>,
			loop:true,
			dots:false,
			nav:false,
			margin:0,
			autoplay:true,
			autoplayTimeout:8000,
			smartSpeed:2000
		});
	</script>
<?php } add_action( 'wp_footer', 'after_jquery', 100 ); ?>
