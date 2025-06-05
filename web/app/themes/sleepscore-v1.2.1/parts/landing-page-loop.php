<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
landing page loop php
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php // var_dump($wp_query->query_vars); ?>

<nav class="container-fluid fixed-sub-nav">
	<div class="row">
		<?php if(in_category(array('tired-during-the-day', 'i-snore-my-partner-snores', 'trouble-falling-asleep', 'trouble-staying-asleep', 'general'))): ?>
			<?php // get_template_part('parts/ss-cat-nav'); ?>

			<div class="col-2 d-inline-block d-sm-none">
				<button id="ss-nav-trigger" class="ss-cat-nav-branding">
					<?php /*?><span class="square-24 fa fa-warehouse"></span><?php */?>
					<span class="square-24 fa fa-chevron-down"></span>
				</button><!-- ss-cat-nav-branding -->
			</div>
			<div id="ss-cat-nav-wrap" class="container">
				<div class="row">
					<?php if(have_rows('landing_page_menu_repeater', 'option')): ?>
						<?php while(have_rows('landing_page_menu_repeater', 'option')): the_row(); ?>
							<a id="<?php the_sub_field('menu_id', 'option'); ?>" class="landing-menu text-center <?php the_sub_field('menu_classes', 'option'); ?>" href="<?php the_sub_field('menu_link', 'option'); ?>">
								<div class="menu-text" ><?php the_sub_field('menu_text', 'option'); ?></div>
							</a>
						<?php endwhile; ?>
					<?php endif; ?>
					<div class="d-none d-sm-inline-block col-sm-4">
						<div class="search-form-wrapper">
							<?php get_search_form(); ?>
						</div>
					</div>
				</div><!-- row -->
			</div><!-- ss-cat-nav-wrap -->
			<div class="d-inline-block d-sm-none col-10">
				<div class="search-form-wrapper">
					<?php get_search_form(); ?>
				</div>
			</div>

		<?php endif; ?>
	</div><!-- row -->
</nav>



<main class="top-padding-60">
	<section class="container">
		<div class="row">
		<?php $do_not_duplicate = array(); ?>

			<?php if(is_category('tired-during-the-day')) {
				if( have_rows('landing_page_content', 'option') ):
					while ( have_rows('landing_page_content', 'option') ) : the_row();
						if( get_row_layout() == 'tired-during-the-day' ): ?>
							<div class="hero-img col-12 col-sm-8 offset-sm-2 top-bottom-padding">
								<div class="">
									<h1 class="text-center hero-headline"><?php the_sub_field('landing_page_headline'); ?></h1>
									<div class="text-center white-underline"></div>
									<p class="text-center"><?php the_sub_field('hero_text'); ?></p>
								</div>
							</div><!-- seo text container -->
						<?php
						endif;
					endwhile;
				endif;
			} elseif(is_category('trouble-falling-asleep')) {
				if( have_rows('landing_page_content', 'option') ):
					while ( have_rows('landing_page_content', 'option') ) : the_row();
						if( get_row_layout() == 'trouble-falling-asleep' ): ?>
							<div class="hero-img col-12 col-sm-8 offset-sm-2 top-bottom-padding">
								<div class="">
									<h1 class="text-center hero-headline"><?php the_sub_field('landing_page_headline'); ?></h1>
									<div class="text-center white-underline"></div>
									<p class="text-center"><?php the_sub_field('hero_text'); ?></p>
								</div>
							</div><!-- seo text container -->
						<?php
						endif;
					endwhile;
				endif;
			} elseif(is_category('trouble-staying-asleep')) {
				if( have_rows('landing_page_content', 'option') ):
					while ( have_rows('landing_page_content', 'option') ) : the_row();
						if( get_row_layout() == 'trouble-staying-asleep' ): ?>
							<div class="hero-img col-12 col-sm-8 offset-sm-2 top-bottom-padding">
								<div class="">
									<h1 class="text-center hero-headline"><?php the_sub_field('landing_page_headline'); ?></h1>
									<div class="text-center white-underline"></div>
									<p class="text-center"><?php the_sub_field('hero_text'); ?></p>
								</div>
							</div><!-- seo text container -->
						<?php
						endif;
					endwhile;
				endif;
			} elseif(is_category('i-snore-my-partner-snores')) {
				if( have_rows('landing_page_content', 'option') ):
					while ( have_rows('landing_page_content', 'option') ) : the_row();
						if( get_row_layout() == 'i-snore-my-partner-snores' ): ?>
							<div class="hero-img col-12 col-sm-8 offset-sm-2 top-bottom-padding">
								<div class="">
									<h1 class="text-center hero-headline"><?php the_sub_field('landing_page_headline'); ?></h1>
									<div class="text-center white-underline"></div>
									<p class="text-center"><?php the_sub_field('hero_text'); ?></p>
								</div>
							</div><!-- seo text container -->
						<?php
						endif;
					endwhile;
				endif;
			} elseif(is_category('general')) {
				if( have_rows('landing_page_content', 'option') ):
					while ( have_rows('landing_page_content', 'option') ) : the_row();
						if( get_row_layout() == 'general' ): ?>
							<div class="hero-img col-12 col-sm-8 offset-sm-2 top-bottom-padding">
								<div class="">
									<h1 class="text-center hero-headline"><?php the_sub_field('landing_page_headline'); ?></h1>
									<div class="text-center white-underline"></div>
									<p class="text-center"><?php the_sub_field('hero_text'); ?></p>
								</div>
							</div><!-- seo text container -->
						<?php
						endif;
					endwhile;
				endif;
			} else {

			} ?>
		</div><!-- row -->
	</section>



	<section class="container">
		<div class="row"> <?php  // ================================================first group of posts ?>
			<div class="col-12 col-sm-10 offset-sm-1">
				<div class="row">
				<?php
					function buildArgs($firstLoop) {
						$args = array(
							'category_name' => $firstLoop,
							'post__not_in' => $do_not_duplicate,
							'posts_per_page' => 4,
						);
					return $args;
					}
				?>
					<?php if(is_category('tired-during-the-day')) {
						$args = buildArgs('tired-during-the-day');
					} elseif(is_category('trouble-falling-asleep')) {
						$args = buildArgs('trouble-falling-asleep');
					} elseif(is_category('trouble-staying-asleep')) {
						$args = buildArgs('trouble-staying-asleep');
					} elseif(is_category('i-snore-my-partner-snores')) {
						$args = buildArgs('i-snore-my-partner-snores');
					} elseif(is_category('general')) {
						$args = buildArgs('general');
					} ?>
				<?php $wp_query = new WP_Query($args); ?>
				<?php if($wp_query->have_posts()): ?>
					<?php while ($wp_query->have_posts()): $wp_query->the_post(); ?>
					<?php $do_not_duplicate[] = $post->ID; ?>
						<div class="col-12 col-sm-6 bottom-padding index-roll" id="post-<?php the_ID(); ?>">
							<?php if ( has_post_thumbnail() ) { ?>
								<a class="center-block" href="<?php the_permalink(); ?>">
									<?php the_post_thumbnail( 'blog-roll', array(
										'class' => 'img-fluid',
										'alt'   => trim(strip_tags( $wp_postmeta->_wp_attachment_image_alt ))
									) ); ?>
								</a>
							<?php } ?>
							<a class="product-headline index-a-tag" href="<?php the_permalink() ?>"><?php the_title(); ?></a>
						</div><!-- post-->
					<?php endwhile; ?>
				<?php endif; ?>
				</div><!-- row -->
			</div><!-- col 10 -->
		</div><!-- row -->

		<div class="row">
			<button class="show-more">Show More</button>
		</div><!-- row -->

		<div id="show-more" class="hidden">
			<div class="row"> <?php  // ======================================== second group of posts ?>
				<div class="col-12 col-sm-10 offset-sm-1">
					<div class="row">
					<?php function buildArgs3($thirdLoop) {
							$args = array(
								'category_name' => $thirdLoop,
								'posts_per_page' => 4,
								'offset' => 4,
								'post__not_in' => array($do_not_duplicate),
							);
						return $args;
						}
					?>
					<?php if(is_category('tired-during-the-day')) {
						$args3 = buildArgs3('tired-during-the-day');
					} elseif(is_category('trouble-falling-asleep')) {
						$args3 = buildArgs3('trouble-falling-asleep');
					} elseif(is_category('trouble-staying-asleep')) {
						$args3 = buildArgs3('trouble-staying-asleep');
					} elseif(is_category('i-snore-my-partner-snores')) {
						$args3 = buildArgs3('i-snore-my-partner-snores');
					} elseif(is_category('general')) {
						$args3 = buildArgs3('general');
					} ?>
					<?php $wp_query = new WP_Query($args3); ?>
					<?php if($wp_query->have_posts()): ?>
						<?php while ($wp_query->have_posts()): $wp_query->the_post(); ?>
						<?php $do_not_duplicate[] = $post->ID; ?>
							<div class="col-12 col-sm-6 bottom-padding index-roll" id="post-<?php the_ID(); ?>">
								<?php if ( has_post_thumbnail() ) { ?>
									<a class="center-block" href="<?php the_permalink(); ?>">
										<?php the_post_thumbnail( 'blog-roll', array(
											'class' => 'img-fluid',
											'alt'   => trim(strip_tags( $wp_postmeta->_wp_attachment_image_alt ))
										) ); ?>
									</a>
								<?php } ?>
								<a class="product-headline index-a-tag" href="<?php the_permalink() ?>"><?php the_title(); ?></a>
							</div><!-- post-->
						<?php endwhile; ?>
					<?php endif; ?>
					</div><!-- row -->
				</div><!-- col 10 -->
			</div><!-- row -->



			<div class="row">
				<button class="show-all">Show All</button>
			</div><!-- row -->
			<div class="row">
				<div id="show-all" class="hidden col">
				<div class="col-12 col-sm-10 offset-sm-1">
					<div class="row">
						<div class="h3"><?php the_field('all_articles_titles', 'option'); ?></div>
						<?php // ================================================ 5th loop, all articles
							function buildArgs5($fifthLoop) {
									$args = array(
										'category_name' => $fifthLoop,
										'posts_per_page' => 60,
										'offset' => 16,
										'post__not_in' => array($do_not_duplicate),
									);
								return $args;
								}
							?>
							<?php if(is_category('tired-during-the-day')) {
								$args5 = buildArgs5('tired-during-the-day');
							} elseif(is_category('trouble-falling-asleep')) {
								$args5 = buildArgs5('trouble-falling-asleep');
							} elseif(is_category('trouble-staying-asleep')) {
								$args5 = buildArgs5('trouble-staying-asleep');
							} elseif(is_category('i-snore-my-partner-snores')) {
								$args5 = buildArgs5('i-snore-my-partner-snores');
							} elseif(is_category('general')) {
								$args5 = buildArgs5('general');
							} ?>
							<?php $wp_query = new WP_Query($args5); ?>
							<?php if($wp_query->have_posts()): ?>

								<?php while ($wp_query->have_posts()): $wp_query->the_post(); ?>
								<?php $do_not_duplicate[] = $post->ID; ?>

									<a class="col-10 offset-1 col-sm-2 offset-sm-0" href="<?php the_permalink(); ?>">
										<?php the_post_thumbnail( 'thumbnail', array(
											'class' => 'img-fluid bottom-padding',
											'alt'   => trim(strip_tags( $wp_postmeta->_wp_attachment_image_alt ))
										) ); ?>
									</a>
									<a class="col-10 offset-1 col-sm-10 offset-sm-0 headline bottom-padding" href="<?php the_permalink(); ?>" >
										<?php the_title(); ?>
									</a>

								<?php endwhile; ?>

							<?php endif; ?>
						</div><!-- show all hidden -->
					</div><!-- row -->
				</div><!-- col 10 -->
			</div><!-- row -->
		</div><!-- show more hidden -->
</section>
<section class="container"><?php  // ======================================== Sleep Shop Products ?>
	<div class="row">
		<?php function buildArgs4($ssLoop) {
				$args = array(
					'post_type' => 'sleep_shop',
					'tax_query' => array(
						array(
							'taxonomy' => 'sleepshop_categories',
							'field'    => 'slug',
							'terms'    => $ssLoop,
						),
					),
					'posts_per_page' => 3,
				);
			return $args;
			}
		?>
		<?php if(is_category('tired-during-the-day')) {
			$args4 = buildArgs4('feel-more-energy');
			$prodCat = 'feel-more-energy';
		} elseif(is_category('trouble-falling-asleep')) {
			$args4 = buildArgs4('fall-asleep');
			$prodCat = 'fall-asleep';
		} elseif(is_category('trouble-staying-asleep')) {
			$args4 = buildArgs4('stay-asleep');
			$prodCat = 'stay-asleep';
		} elseif(is_category('i-snore-my-partner-snores')) {
			$args4 = buildArgs4('snoring-partner-snoring');
			$prodCat = 'snoring-partner-snoring';
		} elseif(is_category('generalsssss')) {
			$args4 = buildArgs4('general');
		} ?>
		<?php $wp_query = new WP_Query($args4); ?>
		<?php if($wp_query->have_posts()): ?>
			<div class="col-8 bottom-padding semi-bold">Related Products</div>
			<a class="product-headline text-right col-4" href="/sleep-shop-categories/<?php echo $prodCat; ?>">
				View all <span class="fa fa-angle-right"></span>
			</a>
			<?php while ($wp_query->have_posts()): $wp_query->the_post(); ?>
				<div class="col-12 col-md-4 sleep-shop-product">
					<div class="product-img">
						<img class="img-fluid" src="<?php the_field('product_homepage_img'); ?>" />
					</div><!-- product-img -->
					<div class="">
						<div class="product-title product-headline"><?php the_title(); ?></div>
						<div class="product-excerpt bottom-padding-15">
							<?php the_field('product_excerpt'); ?>
						</div>
						<a class="product-cta-button cta-button" href="<?php the_permalink(); ?>">View Product</a>
					</div><!-- col -->
				</div>
			<?php endwhile; ?>
			<?php wp_reset_query(); ?>
		<?php endif; ?>

	</div><!-- row -->
</section>
	<?php // ========================================================== SEO Text imported from Theme Settings ?>
	<article class="footer-banner">
		<div class="container">
			<div class="row">
				<?php if(is_category('tired-during-the-day')) {
					if( have_rows('landing_page_content', 'option') ):
						while ( have_rows('landing_page_content', 'option') ) : the_row();
							if( get_row_layout() == 'tired-during-the-day' ):
								get_template_part('parts/cat-seo-text-loop');
							endif;
						endwhile;
					endif;
				} elseif(is_category('trouble-falling-asleep')) {
					if( have_rows('landing_page_content', 'option') ):
						while ( have_rows('landing_page_content', 'option') ) : the_row();
							if( get_row_layout() == 'trouble-falling-asleep' ):
								get_template_part('parts/cat-seo-text-loop');
							endif;
						endwhile;
					endif;
				} elseif(is_category('trouble-staying-asleep')) {
					if( have_rows('landing_page_content', 'option') ):
						while ( have_rows('landing_page_content', 'option') ) : the_row();
							if( get_row_layout() == 'trouble-staying-asleep' ):
								get_template_part('parts/cat-seo-text-loop');
							endif;
						endwhile;
					endif;
				} elseif(is_category('i-snore-my-partner-snores')) {
					if( have_rows('landing_page_content', 'option') ):
						while ( have_rows('landing_page_content', 'option') ) : the_row();
							if( get_row_layout() == 'i-snore-my-partner-snores' ):
								get_template_part('parts/cat-seo-text-loop');
							endif;
						endwhile;
					endif;
				} elseif(is_category('general')) {
					if( have_rows('landing_page_content', 'option') ):
						while ( have_rows('landing_page_content', 'option') ) : the_row();
							if( get_row_layout() == 'general' ):
								get_template_part('parts/cat-seo-text-loop');
							endif;
						endwhile;
					endif;
				} else {

				} ?>
			</div><!-- row -->
		</div><!-- container -->
	</article>
</main>
