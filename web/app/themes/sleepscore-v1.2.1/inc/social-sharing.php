<div class="social-sharing">
	<?php if(is_singular('sleep_shop')) {  ?>
		<div class="d-inline product-headline bottom-padding-15">
			<?php the_field('social_sharing_sleep_shop', 'option'); ?>
			<br class="d-block d-lg-none">
		</div>
	<?php } elseif(is_single()) { ?>

	<?php } else { ?>
		<div class="d-inline product-headline bottom-padding-15">Share This</div>
	<?php } ?>


	<button class="sharer button ml-0" data-sharer="twitter" data-title="<?php the_title();?>" data-hashtags="" data-url="<?php the_permalink(); ?>">
		<span  class="sr-only">Share on Twitter</span>
		<span class="fa fa-twitter"></span>
	</button>
	<button class="sharer button" data-sharer="facebook" data-url="<?php the_permalink(); ?>">
		<span  class="sr-only">Share on Facebook</span>
		<span class="fa fa-facebook-f"></span>
	</button>
	<button class="sharer button" data-sharer="linkedin" data-url="<?php the_permalink(); ?>">
		<span  class="sr-only">Share on Linkedin</span>
		<span class="fa fa-linkedin"></span>
	</button>
	<button class="sharer button" data-sharer="email" data-title="Check out this article" data-url="<?php the_permalink(); ?>" data-subject="I wanted to share this with you.">
		<span  class="sr-only">Share via Email</span>
		<span class="fa fa-envelope"></span>
	</button>
</div><!-- social sharing -->
