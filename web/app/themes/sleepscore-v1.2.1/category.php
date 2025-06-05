<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
category php
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
		
<?php if(is_category(array('tired-during-the-day', 'trouble-falling-asleep', 'trouble-staying-asleep', 'i-snore-my-partner-snores', 'general'))) {
		get_template_part('parts/landing-page-loop');
	} else { ?>
		<main class="container padding-top-60">
			<h1 class="text-center"><?php the_field('articles_page_title', 'option'); ?></h1>
			
			<?php if ( have_posts()) : while ( have_posts() ) : the_post();	?>
				<article id="post-<?php the_ID(); ?>" class="index-featured padding-bottom-60 col-12">
					<?php if ( has_post_thumbnail() ) { ?>
						<a href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail( 'full', array(
								'class' => 'img-fluid'
							) ); ?>
						</a>
					<?php } ?>
					<?php the_title( sprintf( '<h2 class="entry-title text-center"><a href="%s">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
					<?php get_template_part( 'inc/meta' ); ?>

					<section class="entry-content">
						<?php the_excerpt(); ?>
					</section><!-- .entry-content -->   

				</article><!-- Newest Post -->

				<?php // End the loop.
				endwhile;
				wp_reset_postdata();

			endif; ?>
			<?php rewind_posts(); ?>
			<?php query_posts( 'offset=1&posts_per_page=12' ); ?>
			<div class="cat-rol-wrap">
			<?php while ( have_posts() ) : the_post(); ?>
				<div class="col-12 col-md-4 padding-bottom-30 cat-roll" id="post-<?php the_ID(); ?>">
					<?php if ( has_post_thumbnail() ) { ?>
						<a class="center-block" href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail( 'blog-roll', array(
								'class' => 'img-fluid'
							) ); ?>
						</a>
					<?php } ?>
					<h3><a href="<?php the_permalink() ?>"><?php the_title(); ?></a></h3>
					<?php get_template_part( 'inc/meta' ); ?>
					<div class="entry">
						<?php the_excerpt(); ?>
					</div><!-- entry-->

				</div><!-- post-->
			<?php endwhile; ?>
			</div>
		<?php get_template_part( 'inc/nav' ); ?>
	</main>
<?php } ?>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
