<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Single Partner Post
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<main>

	<article class="container padding-bottom-60">
		<?php if (have_posts()) : ?>
			<div class="row top-bottom-padding">
			<?php while (have_posts()) : the_post(); ?>

				<div class="col-12 col-sm-3">
					<?php
					$image = get_field('partner_logo');
					if( !empty($image) ): ?>
						<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
					<?php endif; ?>
				</div><!-- cols -->
				<div class="col-12 col-sm-9">
					<div class="headline"><?php the_title(); ?></div>
					<div class="product-headline"><?php the_field('partner_tagline'); ?></div>
					<div class=""><?php the_field('partner_description'); ?></div>
					<a href="<?php the_field('partner_link_url'); ?>"><?php the_field('partner_link_text'); ?></a>
				</div><!-- cols -->
			<?php endwhile; ?>
			</div><!-- row -->
		<?php endif; ?>
	</article>

	<section class="category-page col-xs-12">  <!-- posts assigned to this page -->

	 	<?php
			$term_list = wp_get_post_terms($post->ID, 'product_brand', array("fields" => "all"));
			$theSlug = $term_list[0]->slug ;
		?>

		<?php $wp_query = new WP_Query( array (
			'post_type' => 'sleep_shop',
			'tax_query' => array(
				array(
					'taxonomy' => 'product_brand',
					'field'    => 'slug',
					'terms'    => $theSlug,
				),
			),
			'posts_per_page' => '-1' )
			);
		?>
		<?php if ( $wp_query->have_posts()) : ?>
			<div class="container bottom-padding">
				<div class="row">
					<div class="hero-headline"><?php echo $partnerTitle; ?> Products</div>
				</div><!-- row -->
			</div><!-- container -->
			<div class="container bottom-padding">
				<div class="row">
				<?php while ( $wp_query->have_posts() ) : $wp_query->the_post(); ?>
					<div class="col-12 col-sm-4 bottom-padding">
						<?php if(get_field('validated_product')): ?>
							<div class="validated-icon">
								<?php
								$image = get_field('validated_icon', 'option');
								if( !empty($image) ): ?>
								<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" title="SleepScore Validated Product Study" />
								<?php endif; ?>
							</div><!-- validated Icon -->
						<?php endif; ?>
						<div class="product-img">
							<img class="img-fluid" src="<?php the_field('product_homepage_img'); ?>" />
						</div><!-- product-img -->
						<div class="product-details">
							<div class="product-headline"><?php the_title(); ?></div>
							<div class="product-excerpt bottom-padding-15">
								<?php the_field('product_excerpt'); ?>
							</div>
							<a class="cta-button" href="<?php the_permalink(); ?>">View Product</a>
						</div><!-- product-details -->
					</div><!-- col -->
				<?php endwhile; ?>
			</div><!-- row -->
		</div><!-- container -->
		<?php wp_reset_postdata(); ?>
		<?php endif; ?>
		<div class="clearfix"></div>
	</section>
</main>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
