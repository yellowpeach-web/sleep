<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Single Marketplace post
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
	<main>
		<article class="container">
			<div class="row">
				<div class="col-12 col-md-8">
					<h1 class="col-12"><?php the_title(); ?></h1>
					
					$<?php the_field('product_price'); ?>
					<?php the_field('product_description'); ?>
					<?php 
						$image = get_field('product_image');
						if( !empty($image) ): 
							// vars
							$url = $image['url'];
							$title = $image['title'];
							$alt = $image['alt'];
							$caption = $image['caption'];

							// thumbnail
							$size = 'large';
							$thumb = $image['sizes'][ $size ];
							$width = $image['sizes'][ $size . '-width' ];
							$height = $image['sizes'][ $size . '-height' ];
						?>
							<img class="img-fluid" src="<?php echo $thumb; ?>" alt="<?php echo $alt; ?>" width="<?php echo $width; ?>" height="<?php echo $height; ?>" />
					<?php endif; ?>
						
					<a target="_blank" href="<?php the_field('product_link_url'); ?>"><?php the_field('product_link_text'); ?></a>
					
					<?php if(get_field('product_ID')): ?>
						<?php echo '' , the_field('product_ID'); ?>
					<?php endif; ?>
					<?php if(get_field('product_coupon_code')): ?>
						<?php the_field('product_coupon_code'); ?>
					<?php endif; ?>
					
					<?php get_template_part('inc/social-sharing'); ?>
					<?php get_template_part('inc/nav'); ?>
					
				</div><!-- cols -->
			</div><!-- row -->
		</article><!-- container -->
	</main>
<?php endwhile; endif; ?>

<?php get_footer(); ?>