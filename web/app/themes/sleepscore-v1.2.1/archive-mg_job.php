<?php get_header(); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Archive MG Jobs
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<main class="container top-padding-60">
	<article class="job-listings">
		<div class="row">
			<?php
			if ( function_exists('yoast_breadcrumb') ) {
			yoast_breadcrumb('
			<p id="breadcrumbs" class="col">','</p>
			');
			}
			?>
		</div>
		<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
			<div class="row">
				<h3 class="col-12 col-sm-10 headline">
					<a href="<?php the_permalink();?>"><?php the_title(); ?>  <span class="fa fa-angle-right"></span></a>
				</h3>
				<div class="entry col-12 col-sm-10">
					Job Type: <?php the_field('job_type'); ?>
					Location: <?php the_field('location'); ?>
				</div><!--entry -->
			</div><!--post-->
		<?php endwhile; endif; ?>	
	</article>
</main>
<?php get_footer(); ?>