<?php if(have_rows('social_follow_channels', 'option')): ?>
	<div class="social-follow text-center">
	<?php while(have_rows('social_follow_channels', 'option')): the_row(); ?>
		<a class="" target="_blank" href="<?php the_sub_field('social_channel_button_url', 'option'); ?>">
			<span class="fa <?php the_sub_field('social_channel_button_icon', 'option'); ?>"></span>
			<span class="sr-only"><?php the_sub_field('social_channel_button_text', 'option'); ?></span>
		</a>
	<?php endwhile; ?>
	</div><!-- social follow -->
<?php endif; ?>