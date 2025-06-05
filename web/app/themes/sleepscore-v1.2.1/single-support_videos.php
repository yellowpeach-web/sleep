<?php get_header('empty'); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Single Support Videos post
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<main class="">
	<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
	<article class="container bottom-padding">
		<div class="row">
			<div class="col-12 text-center">
				<?php the_content(); ?>
			</div><!-- col -->
		</div><!-- row -->
	</article>	
	<?php endwhile; endif; ?>	
</main>
<?php get_footer('empty'); ?>