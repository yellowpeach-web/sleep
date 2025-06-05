<?php if( have_rows('gallery_repeater', 'option') ): ?>
	<div id="carousel-wrapper-<?php echo $post->ID; ?>" class="carousel slide" data-ride="carousel">
		<div class="carousel-inner vh50">
		<?php while ( have_rows('gallery_repeater', 'option') ) : the_row(); ?>
			<div class="carousel-item">
				<?php if(get_sub_field('image_only')) { ?>
					<?php 
						$image = get_sub_field('image');
						if( !empty($image) ):
						// vars
						$url = $image['url'];
						$title = $image['title'];
						$alt = $image['alt'];
						// thumbnail
						$size = 'large';
						$thumb = $image['sizes'][ $size ];
						$width = $image['sizes'][ $size . '-width' ];
						$height = $image['sizes'][ $size . '-height' ];
					?>			
						<img class="d-block w-100" src="<?php echo $thumb; ?>" alt="<?php echo $alt; ?>" />
					<?php endif; // end image slide ?>
				<?php } elseif(get_sub_field('image_headline')) { ?>
					<div class="col bg-img vh50" style="background-image:url('<?php the_sub_field('bg_image'); ?>')">
						<div class="img-text col text-center">
							<h2><?php the_sub_field('heading'); ?></h2>
							<span><?php the_sub_field('tagline'); ?></span>
						</div><!-- img-text -->
					</div>
				<?php } elseif(get_sub_field('wysiwyg_editor')) { ?>
					<div <?php if(get_sub_field('editor_bg_image')): ?>
						class="bg-img" style="background-image:url("<?php the_sub_field('bg_image'); ?>")"
						<?php endif; ?>
					>
						<?php the_sub_field('editor_slide'); ?>
					</div>
				<?php } else {} ?>				
			</div><!-- item -->
		<?php endwhile;	?>
		</div><!-- carousel-inner -->
		
		<ol class="carousel-indicators">
			<li data-target="#carousel-wrapper-<?php echo $post->ID; ?>" data-slide-to="0" class="active"></li>
			<li data-target="#carousel-wrapper-<?php echo $post->ID; ?>" data-slide-to="1"></li>
			<li data-target="#carousel-wrapper-<?php echo $post->ID; ?>" data-slide-to="2"></li>
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
<?php endif; ?>