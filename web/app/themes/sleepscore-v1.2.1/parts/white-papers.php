<?php $query = new WP_Query( array (
	'post_type' => 'news_media',
	'tax_query' => array(
		array(
			'taxonomy' => 'news_media_categories',
			'field'    => 'slug',
			'terms'    => 'white-papers',
		),
	),
	'posts_per_page' => '4' )
	);
?>
<?php if( $query->have_posts() ): ?>
<div class="container bottom-padding-60">
	<hr>
	<div class="row top-bottom-padding">
		<div class="col-12 bottom-padding hero-headline text-center">
			Papers & Abstracts
		</div>

	<?php  while ( $query->have_posts() ) : $query->the_post(); ?>
		<div class="col-12 col-md-6 bottom-padding">
			<a class="product-headline" href="<?php the_field('white_paper_link'); ?>" target="_blank">
				<?php the_title(); ?>
			</a>
			<br>
			<?php if(get_field('white_paper_author')): ?>
			<span><?php the_field('white_paper_author'); ?></span>
			<br>
			<?php endif; ?>
			<span class="publish-date"><?php the_field('white_paper_publication_date'); ?></span>
		</div><!-- col -->
	<?php endwhile; ?>
		<a class="product-headline text-right outline-button mx-auto" href="/media/white-papers">
			View all <span class="fa fa-angle-right"></span>
		</a>
	</div><!-- row -->
</div><!-- container -->
<?php endif; ?>
