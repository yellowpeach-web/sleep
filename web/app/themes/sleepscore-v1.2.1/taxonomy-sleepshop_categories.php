<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
taxonomy ss category php
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php $ssCatTitle; ?>

<?php  get_template_part('parts/ss-cat-nav'); ?>

<main class="container top-padding-60">
	<?php if(!is_paged()): ?>
	<div class="row top-padding bottom-padding">
		<?php // ========================================================= conditional hero based on taxonomy ?>	
	<?php if(is_tax('sleepshop_categories','bedding')) { ?>
		<?php $ssCatTitle = "bedding"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'bedding' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','lighting')) { ?>
	<?php $ssCatTitle = "lighting"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'lighting' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','wellness')) { ?>
	<?php $ssCatTitle = "wellness"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'wellness' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','electronics')) { ?>
	<?php $ssCatTitle = "electronics"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'electronics' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','grinding')) { ?>
	<?php $ssCatTitle = "grinding"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'grinding' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','snoring')) { ?>
	<?php $ssCatTitle = "snoring"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'snoring' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','snoring-grinding')) { ?>
	<?php $ssCatTitle = "snoring-grinding"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'snoring' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','travel')) { ?>
	<?php $ssCatTitle = "travel"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'travel' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','fall-asleep')) { ?>
	<?php $ssCatTitle = "fall-asleep"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'fall_asleep_option' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','feel-more-energy')) { ?>
	<?php $ssCatTitle = "feel-more-energy"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'feel_more_energy_option' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','snoring-partner-snoring')) { ?>
	<?php $ssCatTitle = "snoring-partner-snoring"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'snoring_cat_option' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','stay-asleep')) { ?>
	<?php $ssCatTitle = "stay-asleep"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'stay_asleep_option' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>"><?php the_sub_field('hero_body_copy', 'option'); ?></div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
	<?php } elseif(is_tax('sleepshop_categories','validated')) { ?>
	<?php $ssCatTitle = "validated-products"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'validated_products' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>">
						<?php the_sub_field('hero_body_copy', 'option'); ?>
						<button type="button" class="text-btn" data-toggle="modal" data-target=".bd-example-modal-lg">
							<?php the_field('ss_home_validated_modal_text', 'option');?>
						</button>
					</div>
				</div>
				<?php // ===================================================== this is the modal ?>
				<div class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
					<div class="modal-dialog modal-lg">
						<div class="modal-content">
							<div class="modal-header royal-bg white-font headline">
								<span class="modal-title h5"><?php the_field('ss_home_validated_section_modal_title', 'option');?></span>
								<button type="button" class="close white-font" data-dismiss="modal" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							<div class="modal-body">
								<?php the_field('ss_home_validated_section_modal_content', 'option');?>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>
		<?php } elseif(is_tax('sleepshop_categories', 'curated')) { ?>
	<?php $ssCatTitle = "curated-products"; ?>
		<?php if(have_rows('sleep_shop_cat_hero_content', 'option')): ?>
		<?php while(have_rows('sleep_shop_cat_hero_content', 'option')): the_row(); ?>
			<?php if( get_row_layout() == 'curated_products' ): ?>
				<div class="<?php the_sub_field('hero_image_classes', 'option'); ?>">
					<img class="img-fluid" src="<?php the_sub_field('hero_image', 'option'); ?>" />
				</div>
				<div class="<?php the_sub_field('hero_content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('ss_cat_hero_headline_classes', 'option'); ?>"><?php the_sub_field('ss_cat_hero_headline', 'option'); ?></div>
					<div class="<?php the_sub_field('hero_body_copy_classes', 'option'); ?>">
						<?php the_sub_field('hero_body_copy', 'option'); ?>
						<button type="button" class="text-btn" data-toggle="modal" data-target=".bd-example-modal-lg">
							<?php the_field('ss_home_validated_modal_text', 'option');?>
						</button>
					</div>
				</div>
				<?php // ===================================================== this is the modal ?>
				<div class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
					<div class="modal-dialog modal-lg">
						<div class="modal-content">
							<div class="modal-header royal-bg white-font headline">
								<span class="modal-title h5"><?php the_field('ss_home_curated_section_modal_title', 'option');?></span>
								<button type="button" class="close white-font" data-dismiss="modal" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
							</div>
							<div class="modal-body">
								<?php the_field('ss_home_curated_section_modal_content', 'option');?>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>
		<?php endwhile; ?>
		<?php endif; ?>	
	<?php } else { } ?>
	</div><!-- row -->
<?php endif; // is_paged() ?>
	
	<script>
		var ssCatTitle = "<?php echo $ssCatTitle; ?>";
	</script>
	
<?php if(!is_paged()): ?>
	<?php if(is_tax('sleepshop_categories','curated')): ?>
	<section class="container bottom-padding no-padding">
		<?php if(have_rows('shop_by_topics_repeater', 'option')): // ===================== shop by topics ?>
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
	<?php endif; ?>
<?php endif; // is_paged() ?>



<?php if(is_paged()){ ?>
	<?php $term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) ); ?>
	<?php $currentTax = $term->taxonomy; ?>
	<?php $currentTerm = $term->name; ?>
	<?php
		$args = array(
			'post_type' => 'sleep_shop',
			'tax_query' => array(
				array(
					'taxonomy' => $currentTax,
					'field'    => 'slug',
					'terms'    => $currentTerm,
				),
			),
			'posts_per_page' => -1
		);
	?>
	<?php $wp_query = new WP_Query($args); ?>
	<?php if($wp_query->have_posts()): ?>
		<div class="row top-bottom-padding">
		<?php while ($wp_query->have_posts()): $wp_query->the_post(); ?>
			<a class="col-12 col-sm-4 bottom-padding-60 relative" href="<?php the_permalink(); ?>">
				<?php if(get_field('validated_product')): ?>
					<div class="validated-icon">
						<?php 
						$image = get_field('validated_icon', 'option');
						if( !empty($image) ): ?>
						<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
						<?php endif; ?>
					</div><!-- validated Icon -->
				<?php endif; ?>
				<div class="product-img">
					<img class="img-fluid" src="<?php the_field('product_homepage_img'); ?>" />	
				</div><!-- product-img -->
				<div class="">
					<div class="product-title product-headline">
						<?php the_title(); ?>&nbsp;<span class="fa fa-chevron-right link-chevron"></span>
					</div><!-- product title -->
				</div>
		</a><!-- col -->
		<?php endwhile;  wp_reset_postdata();  ?>
		</div><!-- row -->
		<div class="row navigation bottom-padding text-center justify-content-end">
			<?php previous_posts_link('Back') ?>&nbsp;
			<?php next_posts_link('View all') ?>&nbsp;
		</div><!-- row -->
	<?php endif; ?>
<?php } else { ?>
	<?php if ( have_posts()) : // ==================================================== Products ?>
		<div class="row top-bottom-padding">
		<?php while ( have_posts() ) : the_post();	?>
		<a class="col-12 col-sm-4 bottom-padding-60 relative" href="<?php the_permalink(); ?>">
			<?php if(get_field('validated_product')): ?>
				<div class="validated-icon">
					<?php 
					$image = get_field('validated_icon', 'option');
					if( !empty($image) ): ?>
					<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
					<?php endif; ?>
				</div><!-- validated Icon -->
			<?php endif; ?>
			<div class="product-img">
				<img class="img-fluid" src="<?php the_field('product_homepage_img'); ?>" />	
			</div><!-- product-img -->
			<div class="">
				<div class="product-title product-headline">
					<?php the_title(); ?>&nbsp;<span class="fa fa-chevron-right link-chevron"></span>
				</div><!-- product title -->		
			</div>
		</a><!-- col -->
		<?php endwhile; ?>
		</div><!-- row -->
		<div class="row navigation bottom-padding text-center justify-content-end">
			<?php previous_posts_link('Back') ?>&nbsp;
			<?php next_posts_link('View all') ?>&nbsp;
		</div><!-- row -->
	<?php endif; ?>
<?php } // end else if is_paged() ?>
</main>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>