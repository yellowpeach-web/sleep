<div class="container top-bottom-padding">
<?php if(have_rows('feature_list_repeater')): ?>
	<div class="row">
	<?php while(have_rows('feature_list_repeater')): the_row(); ?>
		<div class="<?php the_sub_field('column_classes'); ?>">
			<div class="wrapper border box-shadow">

				<div class="text-center headline top-bottom-padding border-bottom">
					<?php the_sub_field('title'); ?>
				</div><!-- feature-list-title -->

				<?php if(have_rows('feature_repeater')): ?>
				<div class="row top-padding bottom-padding">
					<div class="col-7 col-md-8 top-padding-15 bottom-padding-15 border-bottom"></div>
					<div class="col-2 top-padding-15 bottom-padding-15 border-bottom small text-right text-md-left">Free</div>
					<div class="col-3 col-md-2 top-padding-15 bottom-padding-15 border-bottom small">Premium</div>
				<?php while(have_rows('feature_repeater')): the_row(); ?>

					<div class="col-8 top-padding-15 bottom-padding-15 border-bottom">
						<?php the_sub_field('feature'); ?>
					</div><!-- col-8 -->

					<div class="col-2 top-padding-15 bottom-padding-15 border-bottom">
						<?php if(get_sub_field('check')):?>
							<span class="fa fa-check fa-lg"></span>
						<?php endif; ?>
					</div><!-- col-2 -->
					<div class="col-2 top-padding-15 bottom-padding-15 border-bottom">
						<?php if(get_sub_field('premium_check')):?>
							<span class="fa fa-check fa-lg"></span>
						<?php endif; ?>
					</div><!-- col-2 -->

				<?php endwhile; ?>
				</div><!-- row -->
				<?php endif; // feature_repeater ?>

				<div class="text-center headline d-flex justify-content-center align-items-center top-padding-15 bottom-padding-15">
					<div class="row ">
						<div class="col-12">
							<?php the_sub_field('bottom_feature_cta'); ?>
						</div>

					</div><!-- row -->
				</div><!-- feature-list-Price -->
			</div><!-- wrapper border -->
		</div><!-- col -->
	<?php endwhile; ?>
	</div><!-- row -->
<?php endif; ?>
</div><!-- container -->
