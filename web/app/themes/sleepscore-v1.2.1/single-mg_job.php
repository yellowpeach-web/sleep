<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Single job post
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
	<main class="top-padding-60">
		<div class="container">
			<div class="row">
				<?php
				if ( function_exists('yoast_breadcrumb') ) {
				yoast_breadcrumb('
				<p id="breadcrumbs" class="col">','</p>
				');
				}
				?>
			</div>
		</div>

		<article class="container">
			<div id="post-<?php the_ID(); ?>" class="row" >
				<div class="col-12">
					<h1 class="hero-headline"><?php the_title(); ?></h1>
					<?php get_template_part( 'inc/meta' ); ?>
				</div>
			</div><!-- row -->
		</article><!-- container -->

		<section class="container">
			<div class="row">
				<div class="col-12">
					<div class="article-content">
						<?php the_content(); ?>
					</div><!-- content -->
					<?php // comments_template(); ?>
				</div><!-- cols -->

			</div><!-- row -->
		</section><!-- container -->
	</main>
<?php endwhile; endif; ?>
<?php get_footer(); ?>
