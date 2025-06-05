<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Marketplace Homepage
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<main>
	<article class="container">
		<?php if (have_posts()) : ?>
			<div class="row">
			<?php while (have_posts()) : the_post(); ?>
				<div class="col-12 col-md-4">
					<h2 class=""><?php the_title(); ?></h2>

					$<?php the_field('product_price'); ?>
					<?php the_field('product_excerpt'); ?>
					<?php 
						$image = get_field('product_image');
						if( !empty($image) ): 
							// vars
							$url = $image['url'];
							$title = $image['title'];
							$alt = $image['alt'];
							$caption = $image['caption'];

							// thumbnail
							$size = 'medium';
							$thumb = $image['sizes'][ $size ];
							$width = $image['sizes'][ $size . '-width' ];
							$height = $image['sizes'][ $size . '-height' ];
						?>
							<img class="img-fluid" src="<?php echo $thumb; ?>" alt="<?php echo $alt; ?>" width="<?php echo $width; ?>" height="<?php echo $height; ?>" />
					<?php endif; ?>

					<a target="_blank" href="<?php the_field('product_link_url'); ?>"><?php the_field('product_link_text'); ?></a>

					
				</div><!-- cols -->
								
			<?php endwhile; ?>
			</div><!-- row -->
			<?php get_template_part('inc/social-sharing'); ?>
			<?php get_template_part('inc/nav'); ?>
			<?php else : ?>
		<?php endif; ?>
	</article>
</main>

<?php get_footer(); ?>