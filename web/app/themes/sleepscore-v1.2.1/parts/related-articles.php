<div class="container">
	<div class="row top-padding bottom-padding">
		<div class="<?php the_sub_field('related_articles_title_classes'); ?>">
			<?php the_sub_field('related_articles_title'); ?>
		</div><!-- col -->
	</div><!-- row -->
</div><!-- container -->
<?php if(have_rows('related_articles_repeater')): // ====== this is the old repeater used on pages?>
<div class="container">
	<div class="row">
		<?php while(have_rows('related_articles_repeater')): the_row(); ?>
		<?php $id = get_sub_field('article_post_id'); ?>
		<a class="<?php the_sub_field('related_article_classes'); ?>" href="<?php echo get_permalink( $id ); ?>">

			<div class="text-center bottom-padding-15" >
				<?php echo get_the_post_thumbnail( $id, 'blog-roll', array('class' => 'img-fluid') ); ?>
			</div>

			<div class="product-title">
				<?php echo get_the_title( $id ); ?>
			</div>
		</a>
		<?php endwhile; ?>
	</div><!-- row -->
</div><!-- container -->
<?php endif; ?>

<?php // ===================== this is the newer blog post related articles ?>
<?php $post_objects = get_sub_field('related_article'); ?>
<?php if( $post_objects ): ?>
 <div class="container<?php if(get_sub_field('fluid')): echo '-fluid'; endif; ?>" <?php if(get_sub_field('container_background_color')): ?>style="background-color:<?php the_sub_field('container_background_color'); ?>" <?php endif; ?>>
	<div class="row top-bottom-padding">
		<?php if(get_sub_field('nested_container')): ?>
			<div class="container">
				<div class="row">
		<?php endif; ?>
    	<?php foreach( $post_objects as $post): // variable must be called $post (IMPORTANT) ?>
        <?php setup_postdata($post); ?>
        <div class="<?php the_sub_field('fallback_articles_bs_classes'); ?>">
			<div class="text-center">
			<?php echo get_the_post_thumbnail( $post_id, 'blog-roll', array('class' => 'img-fluid') ); ?>
			</div>
			<div class="bg-white vh-md-20 padding-15">
				<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				<div class="meta">
					<?php // display last update if post has been updated.
						$u_time = get_the_time('U');
						$u_modified_time = get_the_modified_time('U');
						if ($u_modified_time >= $u_time + 86400) {
						echo '<p>';
						the_modified_time('F jS, Y');
						echo "</p> "; }
					?>
				</div>
			</div><!-- bg-white wrapper -->
        </div>
    <?php endforeach; ?>

    <?php wp_reset_postdata(); // IMPORTANT - reset the $post object so the rest of the page works correctly ?>
	<?php if(get_sub_field('nested_container')): ?>
			</div><!-- row -->
		</div> <!-- container -->
	<?php endif; ?>
	</div><!-- row -->
</div><!-- container -->
<?php endif; ?>
