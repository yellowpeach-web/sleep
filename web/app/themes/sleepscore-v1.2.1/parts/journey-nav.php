<?php if(have_rows('landing_page_menu_repeater', 'option')): ?>
	<div class="row">
		<div class="landing-nav col text-center">
		<?php while(have_rows('landing_page_menu_repeater', 'option')): the_row(); ?>
			<a id="<?php the_sub_field('menu_id', 'option'); ?>" class="landing-menu text-center <?php the_sub_field('menu_classes', 'option'); ?>" href="<?php the_sub_field('menu_link', 'option'); ?>">
				<div class="menu-text" ><?php the_sub_field('menu_text', 'option'); ?></div>
			</a>
		<?php endwhile; ?>
		</div>
	</div><!-- row -->
<?php endif; ?>