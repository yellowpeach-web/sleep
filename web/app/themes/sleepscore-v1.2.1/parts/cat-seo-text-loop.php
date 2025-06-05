<article class="col-12 col-sm-10 offset-sm-1">
	<h1><?php the_sub_field('landing_page_headline', 'option'); ?></h1>
	<div><?php the_sub_field('landing_page_seo_body_copy', 'option'); ?></div>
	<?php if(get_sub_field('landing_page_cta_button_text', 'option')): ?>
	<div class="col-12 text-center">
		<a class="cta-button" href="<?php the_sub_field('landing_page_cta_button_url', 'option'); ?>">
			<?php the_sub_field('landing_page_cta_button_text', 'option'); ?>
		</a>
	</div>
	<?php endif; ?>
</article>