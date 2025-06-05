<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Archive SleepShop Homepage
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php  get_template_part('parts/ss-cat-nav'); ?>
<main class="top-padding-60">
	<div class="container wrap top-padding-15"><?php // fix padding on homepage mobile ?>
	<div class="container">
		<?php get_template_part('parts/carousel'); ?>
	</div>
	<article class="container">
		<div class="row bottom-padding-15"><?php //==================================== hero section ?>
			<div class="col bg-img no-gutters vh30 vh-md-50 d-sm-flex align-items-start align-items-md-center top-padding-15" style="background-image:url('<?php the_field('sleep_shop_home_image', 'option'); ?>');">
				<div class="<?php the_field('ss_home_hero_wrapper_classes', 'option'); ?>">
					<div class="<?php the_field('sleep_shop_home_headline_classes', 'option'); ?>"><?php the_field('sleep_shop_home_headline', 'option'); ?></div>
					<div class="<?php the_field('sleep_shop_home_sub_heading_classes', 'option'); ?>"><?php the_field('sleep_shop_home_sub_heading', 'option'); ?></div>
					<div class="<?php the_field('sleep_shop_home_body_copy_classes', 'option'); ?>">
						<?php the_field('sleep_shop_home_body_copy', 'option'); ?><br>
						<button type="button" class="text-btn no-padding" data-toggle="modal" data-target=".bd-example-modal-lg">
							<?php the_field('ss_home_validated_modal_text', 'option');?>
						</button>
					</div><!-- sleep_shop_home_body_copy -->
					<?php if(get_field('sleep_shop_home_cta_link_text', 'option')): ?>
						<a class="btn" href="<?php the_field('sleep_shop_home_cta_url', 'option'); ?>">
							<?php the_field('sleep_shop_home_cta_link_text', 'option'); ?>
						</a>
					<?php endif; ?>
				</div>
			</div><!-- hero section -->
		</div><!--row-->
	</article>
	<?php // ===================================================== this is the modal ?>
	<div class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header royal-bg white-font headline">
					<span class="modal-title h5"><?php the_field('ss_home_validated_modal_title', 'option');?></span>
					<button type="button" class="close white-font" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body container">
					<?php the_field('ss_home_validated_modal', 'option');?>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>

	<section class="container bottom-padding no-padding">
		<?php if(have_rows('shop_by_topics_repeater', 'option')): // =========== shop by topics ?>
			<div class="row">
			<?php while(have_rows('shop_by_topics_repeater', 'option')): the_row(); ?>
				<a class="col-6 col-sm-3 bg-img no-gutters vh30 white-borders text-center" href="<?php the_sub_field('shop_by_topics_link', 'option'); ?>" style="background-image:url('<?php the_sub_field('shop_by_topic_image', 'option'); ?>')">
					<div class="
					headline top-padding-15"><?php the_sub_field('shop_by_topics_title', 'option'); ?></div>
				</a>
			<?php endwhile; ?>
			</div><!-- row -->
		<?php endif; ?>
	</section>
	</div><!-- container wrap -->
	<section class="container-fluid top-padding bottom-padding ">
		<?php // ================================================================= category Icon Nav ?>
		<div class="container">
		<?php if(have_rows('category_nav_repeater', 'option')): ?>
			<div class="row">
				<div class="col-12 semi-bold no-padding bottom-padding-15"><?php the_field('shop_by_category_text', 'option'); ?></div>
			<?php while(have_rows('category_nav_repeater', 'option')): the_row(); ?>
				<a class="cat-icon-nav col-4 col-sm" href="<?php the_sub_field('link_url', 'option'); ?>">
					<?php $image = get_sub_field('icon', 'option');
					if( !empty($image) ): ?>
					<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
					<?php endif; ?>
					<br>
					<div class="text-center"><?php the_sub_field('title', 'option'); ?></div>
				</a>
			<?php endwhile; ?>
			</div><!-- row -->
		<?php endif; ?>
		</div>
	</section>


	<section class="container">
		<?php // =============================================================== Validated Products ?>
		<?php $query = new WP_Query( array (
			'post_type' => 'sleep_shop',
			'tax_query' => array(
				array(
					'taxonomy' => 'sleepshop_categories',
					'field'    => 'slug',
					'terms'    => 'validated',
				),
			),
			'posts_per_page' => '6' )
			);
		?>
		<?php if( $query->have_posts() ): ?>
			<div class="row top-padding-60"><?php // ======================== Validated Title ?>
				<div id="validated-section" class="col bottom-padding hero-headline">Validated Products</div>
				<a class="text-right col pull-right top-padding-15" href="/sleep-shop-categories/validated">
					View more <span class="fa fa-angle-right"></span>
				</a>
			</div><!-- row -->
			<?php // ======================================================== Validated Body Copy ?>
			<div class="row bottom-padding-60 d-flex align-items-center">
				<div class="col-4 col-md-2 bottom-padding-15">
					<?php $image = get_field('validated_icon', 'option');
						if( !empty($image)): ?>
						<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
					<?php endif; ?>
				</div><!-- col -->
				<div class="col-12 col-md-10">
					<span class="product-headline"><?php the_field('validated_section_copy', 'option'); ?></span>
					<button type="button" class="text-btn" data-toggle="modal" data-target=".validated-area">
						<?php the_field('ss_home_validated_section_modal_text', 'option');?>
					</button>
				</div><!-- col -->
			</div><!-- row -->

			<div class="modal fade validated-area" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
				<div class="modal-dialog modal-lg">
					<div class="modal-content">
						<div class="modal-header royal-bg white-font headline">
							<span class="modal-title h5"><?php the_field('ss_home_validated_section_modal_title', 'option');?></span>
							<button type="button" class="close white-font" data-dismiss="modal" aria-label="Close">
								<span aria-hidden="true">&times;</span>
							</button>
						</div>
						<div class="modal-body">
							<?php the_field('ss_home_validated_section_modal_title_content', 'option');?>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
						</div>
					</div>
				</div>
			</div>

			<div class="row"><?php // ============================================== Validated Products ?>
			<?php  while ( $query->have_posts() ) : $query->the_post(); ?>
				<a class="col-12 col-sm-4 bottom-padding-60" href="<?php the_permalink(); ?>">
					<div class="product-img">
						<img class="img-fluid" src="<?php the_field('product_homepage_img'); ?>" />
					</div><!-- product-img -->
					<div class="product-title product-headline"><?php the_title(); ?>&nbsp<span class="fa fa-chevron-right link-chevron"></span></div>
				</a><!-- col -->
			<?php endwhile; ?>
			</div><!-- row -->
			<div class="row">
				<a class="headline text-center col" href="/sleep-shop-categories/validated">
					View more Validated Products <span class="fa fa-angle-right"></span>
				</a>
			</div>
			<hr>
			<?php get_template_part('inc/social-sharing'); ?>

			<?php else : ?>
		<?php endif; ?>
	</section>
</main>
<?php get_template_part('parts/pre-footer-banner'); ?>

<?php get_footer(); ?>
