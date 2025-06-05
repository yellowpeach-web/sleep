<div class="container<?php if(get_sub_field('container_fluid')): echo '-fluid'; endif; ?>">
	<div class="row hh <?php the_sub_field('row_classes'); ?>">
		<?php if(have_rows('two_column_editors')): ?>
		<?php while(have_rows('two_column_editors')): the_row(); ?>
			<?php if(get_sub_field('narrow_column')) { ?>
				<div class="narrow-column col-12 col-md-4">
			<?php } elseif(get_sub_field('wide_column')) { ?>
				<div class="wide-column col-12 col-md-8">
			<?php } else { ?>
				<div class="col-12 col-md">
			<?php } ?>
					<div class="row">
						<?php if(get_sub_field('show_quotes')): ?>
							<div class="col-12">
								<img class="quote-icon quote-icon-start" src="<?php echo get_template_directory_uri(); ?>/images/start-quote.jpg" alt="quote" />
							</div>
						<?php endif; ?>
						<?php if(get_sub_field('show_quotes')){ ?>
							<div class="col-12 semi-bold font-40 line-height-1 top-padding-15">
						<?php } else { ?>
						<div class="col-12">
						<?php } ?>
							<?php the_sub_field('column_editor'); ?>
						</div>
						<?php if(get_sub_field('show_quotes')): ?>
							<div class="col-12 text-right bottom-padding-15">
								<img class="quote-icon quote-icon-end" src="<?php echo get_template_directory_uri(); ?>/images/end-quote.jpg" alt="quote" />
							</div>
						<?php endif; ?>
					</div><!-- content editor -->
			</div><!-- col -->
		<?php endwhile; ?>
	<?php endif; ?>
	</div><!-- row -->
</div><!-- container -->
