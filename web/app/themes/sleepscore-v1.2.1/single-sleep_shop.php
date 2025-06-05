<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Single SleepShop post
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<nav id="ss-cat-nav" class="container-fluid fixed-sub-nav">
	<div class="row">
		<div class="col-3 d-inline-block d-md-none">
			<button id="ss-nav-trigger" class="ss-cat-nav-branding">
				<span class="square-24 fa fa-warehouse"></span>
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav-branding -->
		</div>
		<div id="ss-cat-nav-wrap" class="container"><?php // this is the sub nav ?>
			<?php if(have_rows('category_nav_repeater', 'option')): ?>
			<div class="row">
			<?php while(have_rows('category_nav_repeater', 'option')): the_row(); ?>
				<a class="col-6 col-md ss-cat-nav" href="<?php the_sub_field('link_url', 'option'); ?>">
					<div class="text-center"><?php the_sub_field('title', 'option'); ?></div>
				</a>
			<?php endwhile; ?>
			<div class="d-none d-md-inline-block col-md-4">
				<div class="search-form-wrapper">
					<?php get_search_form(); ?>
				</div>
			</div>

			</div><!-- row -->
		</div><!-- container -->
		<div class="d-inline-block d-md-none col-9"> <?php // this is the search on mobile ?>
			<div class="search-form-wrapper">
				<?php get_search_form(); ?>
			</div>
		</div>

		<?php endif; ?>
	</div><!-- row -->
</nav>

<?php // declaring variable to populate with values for analytics
	$ssProductTitle;
	$ssProductPrice;
	$ssProductCat;
	$ssProductLink;
?>

<main class="top-padding-60">
	<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
	<article class="container top-padding bottom-padding">
		<div class="row">
			<div class="col-12 col-md-6 relative">
				<?php if(get_field('validated_product')): ?>
					<div class="validated-icon">
						<?php
						$image = get_field('validated_icon', 'option');
						if( !empty($image) ): ?>
						<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
						<?php endif; ?>
					</div><!-- validated Icon -->
				<?php endif; ?>

				<?php get_template_part('parts/acf-carousel'); ?>

				<?php if(get_field('product_coupon_code_text')): ?>
					<div class="coupon-message col-12 text-center"><?php the_field('product_coupon_code_text'); ?></div>
				<?php endif; ?>

				<?php if(get_field('product_coupon_code')): ?>
					<div class="coupon-code col-12"><?php the_field('product_coupon_code'); ?></div>
				<?php endif; ?>
			</div><!-- Gallery -->
			<div class="col-12 col-md-6">
				<div class="col-12 headline bottom-padding-15"><?php the_title(); ?></div>

				<div class="product-description col-12">
					<?php the_field('product_description'); ?>
				</div><!-- product description -->

				<div class="price-wrap">
					<span id="<?php the_ID(); ?>-price" class="col-12 amount"><?php the_field('product_price'); ?></span>
					<a id="p=<?php the_ID(); ?>"
					<?php $urlTarget = get_field('product_link_url'); ?>
					<?php if(strpos($urlTarget, 'sleepscore.com') == false) { ?>
					target="_blank" <?php } ?> class="product-cta-button cta-button hh" href="<?php the_field('product_link_url'); ?>">
						<?php the_field('product_link_text'); ?>
					</a>
				</div><!-- price wrap -->

				<?php get_template_part('inc/social-sharing'); ?>
			</div><!-- cols -->
		</div><!-- row -->
		<?php // ============================================================== Validated Section  ?>

		<?php if(get_field('validated_product')): ?>
			<div class="row top-bottom-padding d-flex align-items-center">
				<?php if(have_rows('validation_repeater')): ?>
				<div class="col-12 col-lg-10 offset-lg-1 verified_report bottom-padding">
					<div class="row">
						<div class="col-12 product-headline top-padding-15 bottom-padding-15 text-center">
							<?php the_field('validation_benefits_title', 'option'); ?>
						</div>
						<div class="col-4 offset-4 border-bottom"></div>
					</div><!-- row -->

						<div class="row">
						<?php while(have_rows('validation_repeater')): the_row(); ?>
							<div class="col-12 col-md-4 hover-bg text-center">
								<?php
								$image = get_sub_field('icon');
								if( !empty($image) ): ?>
									<img class="validated-benefit img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
								<?php endif; ?>
								<div class="produt-headline bottom-padding"><?php the_sub_field('benefit'); ?></div>
							</div><!-- col md 4 -->
						<?php endwhile; ?>
						</div><!-- row -->

				</div><!-- verified report -->
				<?php endif; ?>

				<div class="col-4 col-sm-2">
					<?php $image = get_field('validated_icon', 'option');
						if( !empty($image)): ?>
						<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
					<?php endif; ?>
				</div><!-- col -->
				<div class="validated-results col-10">
					<span class="product-headline"><?php the_field('validated_results', 'option'); ?></span>
					<button type="button" class="text-btn" data-toggle="modal" data-target=".validated-area">
						<?php the_field('validated_product_learn_more_text', 'option'); ?>
					</button>

					<?php // ==================================================================== this is the modal ?>
					<div class="modal fade validated-area" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
						<div class="modal-dialog modal-lg">
							<div class="modal-content">
								<div class="modal-header headline royal-bg white-font">
									<span class="modal-title h5"><?php the_field('ss_home_detail_page_modal_title', 'option');?></span>
									<button type="button" class="close white-font" data-dismiss="modal" aria-label="Close">
										<span aria-hidden="true">&times;</span>
									</button>
								</div>
								<div class="modal-body">
									<?php the_field('ss_home_detail_page_modal_content', 'option');?>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
								</div>
							</div>
						</div>
					</div>

				</div><!-- validated results -->
			</div><!-- row -->
		<?php endif; ?>

		<hr class="grey-bg">
		<div class="row">
			<div class="col-12 product-excerpt product-disclosure">
				<?php the_field('product_disclosure', 'option'); ?>
			</div>
		</div>
	</article><!-- container -->
	<?php
		$ssProductTitle = get_the_title();
		$ssInitialPrice = get_field('product_price');
		$ssReplacedPrice = str_replace("$","",$ssInitialPrice );
		$ssProductPrice = trim($ssReplacedPrice);
		$ssProductLink = get_field('product_link_url');
	?>
	<?php endwhile; endif; ?>
	<?php wp_reset_postdata(); ?>

	<?php // ========================================================================== this is the related products section  ?>
	<section class="container-fluid" style="background-color:#F4F4F4;">
		<div class="container">
			<div class="row top-padding"><?php // related products ?>
				<span class="col-7 col-md-6 semi-bold">Related Products</span>
				<?php
					$cat = new WPSEO_Primary_Term('sleepshop_categories', get_the_ID());
					$cat = $cat->get_primary_term();
					$cat = get_term($cat);
					$catSlug = $cat->slug;
					$catName = $cat->name;
					$catLink = get_category_link($cat);
				?>
				<a id="<?php echo $catName; ?>" class="product-headline text-right col-5 col-md-6" href="<?php echo $catLink; ?>">View all <span class="fa fa-angle-right"></span>
				</a>
				<?php $query = new WP_Query( array (
					'post_type' => 'sleep_shop',
					'tax_query' => array(
						array(
							'taxonomy' => 'sleepshop_categories',
							'field'    => 'slug',
							'terms'    => $catSlug,
						),
					),
					'posts_per_page' => '3' )
					);
				?>
				<script>
					var ssProdTitle = "<?php echo $ssProductTitle; ?>";
					var ssProdPrice = "<?php echo $ssProductPrice; ?>";
					var ssProdcat = "<?php echo $catName; ?>";
					var ssProdUrl = "<?php echo $ssProductLink; ?>";
				</script>
			</div><!-- row -->
			<?php if( $query->have_posts() ): ?>
				<div class="row top-padding">
				<?php  while ( $query->have_posts() ) : $query->the_post(); ?>
					<div class="col-12 col-md-4 sleep-shop-product relative">
						<?php if(get_field('validated_product')): ?>
							<div class="validated-icon">
								<?php
								$image = get_field('validated_icon', 'option');
								if( !empty($image) ): ?>
								<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
								<?php endif; ?>
							</div><!-- validated Icon -->
						<?php endif; ?>
						<a class="product-img" href="<?php the_permalink(); ?>">
							<img class="img-fluid" src="<?php the_field('product_homepage_img'); ?>" />
						</a><!-- product-img -->
						<div class="">
							<div class="product-title product-headline">
								<?php the_title(); ?>
							</div><!-- product title -->
							<div class="product-excerpt bottom-padding-15">
								<?php the_field('product_excerpt'); ?>
							</div>
							<a class="product-cta-button cta-button" href="<?php the_permalink(); ?>">View Product</a>
						</div><!-- col -->
					</div>
				<?php endwhile; ?>
				</div><!-- row -->
			<?php endif; ?>

		</div><!-- container -->
	</section>
</main>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
