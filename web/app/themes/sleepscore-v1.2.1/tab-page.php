<?php
/*
Template Name: Tab
*/
?>
<?php get_header(); ?>
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Tab page template
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<main class="">
	<article>
		<?php // this is the tab navigation ==============================================  ?>
		<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
		<div class="container">
			<div class="row top-padding bottom-padding-15">
				<?php if(have_rows('tab_title_repeater')): ?>
				<ul class="no-bullets tab-menu col-12 text-center no-padding">
					<?php while(have_rows('tab_title_repeater')): the_row(); ?>
						<li class="product-headline">
							<a href="<?php the_sub_field('tab_menu_item_link'); ?>">
								<?php the_sub_field('tab_menu_item_title'); ?>
							</a>
						</li>
					<?php endwhile; ?>
				</ul>
				<?php endif; ?>
			</div><!-- row -->
		</div><!-- container -->
		<?php endwhile; endif; ?>


		<?php // this is the tab content ==============================================  ?>

		<div id="<?php the_field('tab_1_item_id'); ?>" class="tab-content <?php the_field('tab_1_item_classes'); ?>">
			<?php if(have_rows('tab_1_bootstrap_column_repeater')): ?>
				<?php while(have_rows('tab_1_bootstrap_column_repeater')): the_row(); ?>
				<?php if(get_sub_field('container_classes')): ?>
				<div class="<?php the_sub_field('container_classes');?>">
				<?php endif; ?>
					<?php if(get_sub_field('start_row')): ?><div class="<?php the_sub_field('row_classes'); ?>"><?php endif; ?>
						<div class="<?php the_sub_field('bootstrap_classes'); ?>">
							<?php the_sub_field('bootstrap_content'); ?>
						</div>
					<?php if(get_sub_field('end_row')): ?></div><?php endif; ?>
				<?php if(get_sub_field('end_container')): ?>
				</div><!-- container -->
				<?php endif; ?>
				<?php endwhile; ?>
			<?php endif; ?>
			<?php if(have_rows('lightbox_content_repeater')): ?>
				<?php while(have_rows('lightbox_content_repeater')): the_row(); ?>
					<div id="<?php the_sub_field('lightbox_id'); ?>" class="<?php the_sub_field('lightbox_classes'); ?>">
						<?php the_sub_field('lightbox_content'); ?>
					</div>
				<?php endwhile; ?>
			<?php endif; ?>
		</div><!-- tab 1 -->

		<div id="<?php the_field('tab_2_item_id'); ?>" class="tab-content <?php the_field('tab_2_item_classes'); ?>">
			<?php if(have_rows('tab_2_bootstrap_column_repeater')): ?>
				<?php while(have_rows('tab_2_bootstrap_column_repeater')): the_row(); ?>
					<?php if(get_sub_field('container_classes')): ?>
					<div class="<?php the_sub_field('container_classes');?>">
					<?php endif; ?>
						<?php if(get_sub_field('start_row')): ?><div class="<?php the_sub_field('row_classes'); ?>"><?php endif; ?>
							<div class="<?php the_sub_field('bootstrap_classes'); ?>">
								<?php the_sub_field('bootstrap_content'); ?>
							</div>
						<?php if(get_sub_field('end_row')): ?></div><?php endif; ?>
					<?php if(get_sub_field('end_container')): ?>
					</div><!-- container -->
					<?php endif; ?>
				<?php endwhile; ?>
			<?php endif; ?>
		</div><!-- tab 2-->

		<div id="<?php the_field('tab_3_item_id'); ?>" class="tab-content <?php the_field('tab_3_item_classes'); ?>">
			<?php if(have_rows('tab_3_bootstrap_column_repeater')): ?>
				<?php while(have_rows('tab_3_bootstrap_column_repeater')): the_row(); ?>
					<?php if(get_sub_field('container_classes')): ?>
					<div class="<?php the_sub_field('container_classes');?>">
					<?php endif; ?>
						<?php if(get_sub_field('start_row')): ?><div class="<?php the_sub_field('row_classes'); ?>"><?php endif; ?>
							<div class="<?php the_sub_field('bootstrap_classes'); ?>">
								<?php the_sub_field('bootstrap_content'); ?>
							</div>
						<?php if(get_sub_field('end_row')): ?></div><?php endif; ?>
					<?php if(get_sub_field('end_container')): ?>
					</div><!-- container -->
					<?php endif; ?>
				<?php endwhile; ?>
			<?php endif; ?>


			<?php if(have_rows('executive_repeater')): ?>
				<?php $i = 1; ?>
				<div class="container">
					<div class="row bottom-padding">
					<?php while(have_rows('executive_repeater')): the_row(); ?>
						<a class="lightbox-gallery <?php the_sub_field('column_classes'); ?>" href="#<?php echo $i; ?>" data-flashy-type="inline">
							<?php $image = get_sub_field('executive_photo');
								if( !empty($image) ): ?>
								<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
							<?php endif; ?>
							<div class="headline top-padding-15"><?php the_sub_field('executive_name'); ?></div>
							<div class="top-padding-15"><?php the_sub_field('executive_title'); ?></div>
						</a><!-- col -->

						<div id="<?php echo $i; $i++ ?>" class="d-none">
							<div class="text-center">
								<div class="w-200 d-inline-block">
								<?php $image = get_sub_field('executive_photo');
									if( !empty($image) ): ?>
									<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
								<?php endif; ?>
								</div>
								<div class="headline top-padding-15"><?php the_sub_field('executive_name'); ?></div>
								<a class="" target="_blank" rel="nofollow" href="<?php the_sub_field('executive_linkedin_url'); ?>">
									<span class="<?php the_sub_field('fa-span'); ?>"></span>
								</a>
								<div class="product-headline top-padding-15"><?php the_sub_field('executive_title'); ?></div>
								<div class="row top-padding-15 bottom-padding-15">
									<hr class="col-6 blue-bg"/>
								</div>
							</div>
							<div class="padding-left-right-60 bottom-padding"><?php the_sub_field('executive_bio'); ?></div>
						</div>
					<?php endwhile; ?>
					</div><!-- row -->
				</div><!-- container -->
			<?php endif; ?>

			<?php //get_template_part('parts/white-papers'); ?>

		</div><!-- tab 3 -->

		<div id="<?php the_field('tab_4_item_id'); ?>" class="tab-content <?php the_field('tab_4_item_classes'); ?>">
			<?php if(have_rows('tab_4_bootstrap_column_repeater')): ?>
				<?php while(have_rows('tab_4_bootstrap_column_repeater')): the_row(); ?>
					<?php if(get_sub_field('container_classes')): ?>
					<div class="<?php the_sub_field('container_classes');?>">
					<?php endif; ?>
						<?php if(get_sub_field('start_row')): ?><div class="<?php the_sub_field('row_classes'); ?>"><?php endif; ?>
							<div class="<?php the_sub_field('bootstrap_classes'); ?>">
								<?php the_sub_field('bootstrap_content'); ?>
							</div>
						<?php if(get_sub_field('end_row')): ?></div><?php endif; ?>
					<?php if(get_sub_field('end_container')): ?>
					</div><!-- container -->
					<?php endif; ?>
				<?php endwhile; ?>
			<?php endif; ?>
		</div><!-- tab 4 -->
	</article>
</main>
<?php get_template_part('parts/pre-footer-banner'); ?>
<?php get_footer(); ?>
