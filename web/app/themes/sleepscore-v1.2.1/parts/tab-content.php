
<?php if(have_rows('tab_content_repeater')): ?>
<div class="container top-bottom-padding">
	<div class="row">
		<ul class="no-bullets tab-menu col-12 text-center">
		<?php while(have_rows('tab_content_repeater')): the_row(); ?>
			<li class="product-headline">
				<a href="<?php the_sub_field('tab_menu_item_link'); ?>">
					<?php the_sub_field('tab_menu_item_title'); ?>
				</a>
			</li>
		<?php endwhile; ?>	
		</ul>
	</div><!-- row --> 
	
	<?php while(have_rows('tab_content_repeater')): the_row(); ?>
		<div id="<?php the_sub_field('tab_item_id'); ?>" class="tab-content <?php the_sub_field('tab_item_classes'); ?>">
		<?php if(have_rows('tab_content_options')): ?>
			<?php while(have_rows('tab_content_options')): the_row(); ?>
				
				<?php if(get_row_layout() == "content_wysiwyg"): ?>
						<?php the_sub_field('basic_editor'); ?>
						
				<?php elseif(get_row_layout() == 'bootstrap_repeater'): ?>
					<?php if(have_rows('bootstrap_column_repeater')): ?>
						<?php while(have_rows('bootstrap_column_repeater')): the_row(); ?>
							<?php if(get_sub_field('start_row')): ?><div class="<?php the_sub_field('row_classes'); ?>"><?php endif; ?>
								<div class="<?php the_sub_field('bootstrap_classes'); ?>">
									<?php the_sub_field('boostrap_content'); ?>
								</div>
							<?php if(get_sub_field('end_row')): ?></div><?php endif; ?>
						<?php endwhile; ?>
					<?php endif; ?>
					
				<?php elseif(get_row_layout() == 'headlines'): ?>
					<?php if(get_sub_field('start_row')): ?><div class="<?php the_sub_field('row_classes'); ?>"><?php endif; ?>
						<div class="<?php the_sub_field('bootstrap_classes'); ?>">
						<?php if(have_rows('headline_repeater')): ?>
							<?php while(have_rows('headline_repeater')): the_row(); ?>
								<div class="<?php the_sub_field('headline_classes'); ?>">
									<?php the_sub_field('headline'); ?>
								</div>
							<?php endwhile; ?>
						<?php endif; ?>
						</div>
						<?php if(get_sub_field('end_row')): ?></div><?php endif; ?>
				<?php endif; ?>
				
			<?php endwhile; ?>
		<?php endif; ?>
		</div><!-- content -->		
	<?php endwhile; ?>
	
</div><!-- container -->
<?php endif; ?>