<div class="<?php the_sub_field('executive_container_classes'); ?>">
	<div class="<?php the_sub_field('executive_row_classes'); ?>">
		<div class="col-md-8 offset-md-2">
		<div class="row">
		<?php if(have_rows('executive_repeater')): ?>
			<?php $i = 1; ?>
			<?php while(have_rows('executive_repeater')): the_row(); ?>
			<!--<div class="animated animate-on-scroll" data-animation="fadeInUp">-->

			<?php /* <a class="lightbox-gallery <?php the_sub_field('bootstrap_column_classes'); ?>" href="#<?php echo $i; ?>" data-flashy-type="inline"> */ ?>
			<div class="<?php the_sub_field('bootstrap_column_classes'); ?>">
				<?php $image = get_sub_field('executive_photo');
					if( !empty($image) ): ?>
					<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
				<?php endif; ?>
				<div class="headline top-padding-15"><?php the_sub_field('executive_name'); ?></div>
				<div class="top-padding-15"><?php the_sub_field('executive_title'); ?></div>
			<?php /* </a> */ ?></div><!-- col -->

			<?php /* <div id="<?php echo $i; $i++ ?>" class="d-none">
				<div class="text-center">
					<div class="w-200 d-inline-block">
					<?php $image = get_sub_field('executive_photo');
						if( !empty($image) ): ?>
						<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
					<?php endif; ?>
					</div>
					<div class="headline top-padding-15"><?php the_sub_field('executive_name'); ?></div>

					<div class="product-headline top-padding-15"><?php the_sub_field('executive_title'); ?></div>
					<div class="row top-padding-15 bottom-padding-15">
						<hr class="col-6 blue-bg"/>
					</div>
				</div>
				<div class="padding-left-right-60 bottom-padding-15">
					<div class="product-headline">Bio: </div>
					<?php the_sub_field('bio_copy'); ?>
				</div>
				<div class="padding-left-right-60 bottom-padding-15">
					<div class="product-headline">Average SleepScore: </div>
					<?php the_sub_field('average_sleepscore'); ?>
				</div>
				<div class="padding-left-right-60 bottom-padding-15">
					<div class="product-headline">Favorite SleepShop item: </div>
					<a href="<?php the_sub_field('favorite_sleepshop_item_link'); ?>"><?php the_sub_field('favorite_sleepshop_item'); ?></a>
				</div>
				<div class="padding-left-right-60 bottom-padding-15">
					<div class="product-headline">Best advice nugget: </div>
					<?php the_sub_field('best_advice_nugget'); ?>
				</div>
				<div class="padding-left-right-60 bottom-padding-15">
					<div class="product-headline">Why did you join SleepScore? </div>
					<?php the_sub_field('join_sleepscore'); ?>
				</div>
			</div> */ ?>

			<?php /* ?><?php if(get_sub_field('executive_linkedin_url')): ?>
				<a href="<?php the_sub_field('executive_linkedin_url'); ?>" target="_blank">
					<span class="fa fa-linkedin"></span>
				</a>
				<br>
			<?php endif; ?>
			<?php if(get_sub_field('executive_bio')): ?>
			<div class="executive-bio">
				<?php the_sub_field('executive_bio'); ?>
			</div>
			<?php endif; ?><?php */?>

			<?php endwhile; ?>
		<?php endif; ?>
		</div>
		</div><!-- nested col -->
	</div><!-- executive row -->
</div><!-- executive repeater -->
