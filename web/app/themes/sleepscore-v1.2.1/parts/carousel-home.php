
	<div id="carousel-wrapper-<?php echo $post->ID; ?>" class="carousel slide <?php the_field('hero_slideshow_classes'); ?>" data-ride="carousel">
		<div class="carousel-inner">
		<?php while ( have_rows('home_hero_repeater') ) : the_row(); ?>
		
		
			<div class="carousel-item bg-img <?php the_sub_field('hero_slideshow_classes'); ?>" style="background-image:url('<?php the_sub_field('background_image'); ?>');">
			
				<div class="<?php the_sub_field('container_classes'); ?>">
					<div class="row">
						
					<?php if(get_sub_field('use_headlines_instead')) { ?>
						<?php if(have_rows('headline_repeater')): ?>
							<?php while(have_rows('headline_repeater')): the_row(); ?>
								<div class="<?php the_sub_field('headline_classes'); ?>">
									<div class="row">
										<div class="<?php the_sub_field('headline_classes'); ?>">
											<?php the_sub_field('headline'); ?>
										</div>
									</div><!-- row -->
								</div><!-- container -->
							<?php endwhile; ?>
						<?php endif; ?>
					<?php } else { ?>
						<div class="<?php the_sub_field('editor_classes'); ?>">
							<?php the_sub_field('wysiwyg_editor'); ?>
						</div>
					<?php } ?>
					</div><!-- row -->
				</div>
				
			</div><!-- item -->
		<?php endwhile;	?>
		</div><!-- carousel-inner -->
		
		<ol class="carousel-indicators">
	    <?php $i = '-1'; ?>
		<?php while ( have_rows('home_hero_repeater') ) : the_row(); ?>
			<li data-target="#<?php the_sub_field('acf_carousel_id'); ?>" data-slide-to="<?php echo ++$i; ?>" class="active"></li>
		<?php endwhile;	?>
		</ol>
  
		<a class="carousel-control-prev" href="#carousel-wrapper-<?php echo $post->ID; ?>" role="button" data-slide="prev">
			<span class="carousel-control-prev-icon" aria-hidden="true"></span>
			<span class="sr-only">Previous</span>
		</a>
		<a class="carousel-control-next" href="#carousel-wrapper-<?php echo $post->ID; ?>" role="button" data-slide="next">
			<span class="carousel-control-next-icon" aria-hidden="true"></span>
			<span class="sr-only">Next</span>
		</a>

		<script>
			$(document).ready(function(){
				$(".carousel-inner div:first-child").addClass("item active");
			});
		</script>
					
	</div><!-- carousel wrapper -->
