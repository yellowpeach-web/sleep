<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Partners Home
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<main>
	<article class="container">
		<?php /* ?><div class="<?php the_field('hero_image_classes', 'option'); ?>">
			<img class="img-fluid" src="<?php the_field('hero_image', 'option'); ?>" />
		</div><?php */ ?>
		<div class="<?php the_field('hero_content_classes', 'option'); ?>">
			<div class="<?php the_field('hero_headline_classes', 'option'); ?>"><?php the_field('hero_headline', 'option'); ?></div>
			<div class="<?php the_field('hero_body_copy_classes', 'option'); ?>"><?php the_field('hero_body_copy', 'option'); ?></div>
		</div>
	</article>

	<?php
		wp_reset_query();
		$the_query = new WP_Query( array(
			'post_type' => 'partners',
			'tax_query' => array(
				array (
					'taxonomy' => 'validated_status',
					'field' => 'slug',
					'terms' => 'validated_partner',
				)
			),
			'orderby' => 'menu_order',
			'order'   => 'ASC',
			'posts_per_page' => -1,
		));
	?>

	<section class="container">
		<div class="row bottom-padding">
		<div class="col-12 hero-headline">Featured Partners</div>
		<?php while ( $the_query->have_posts() ) : $the_query->the_post(); ?>
			<div class="col-12 col-md-4">
				<a href="<?php the_permalink(); ?>">
					<?php
					$image = get_field('partner_logo');
					if( !empty($image) ): ?>
						<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
					<?php endif; ?>
				</a>
			</div><!-- cols -->
		<?php endwhile; ?>
		</div><!-- row -->
	</section>

	<?php
	wp_reset_query();
	$the_query = new WP_Query( array(
		'post_type' => 'partners',
		'tax_query' => array(
			array (
				'taxonomy' => 'validated_status',
				'field' => 'slug',
				'terms' => 'standard_partner',
			)
		),
		'orderby' => 'menu_order',
			'order'   => 'ASC',
		'posts_per_page' => -1,
	) );
	?>
	<section class="container">
		<?php if (have_posts()) : ?>
			<div class="row bottom-padding">
			<div class="col-12 hero-headline">Partners</div>
			<?php while ( $the_query->have_posts() ) : $the_query->the_post(); ?>
				<div class="col-12 col-md-4">
					<a target="_blank" href="<?php the_permalink(); ?>">
						<?php
						$image = get_field('partner_logo');
						if( !empty($image) ): ?>
							<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
						<?php endif; ?>
					</a>
				</div><!-- cols -->
			<?php endwhile; ?>
			</div><!-- row -->
		<?php endif; ?>
	</section>
</main>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
