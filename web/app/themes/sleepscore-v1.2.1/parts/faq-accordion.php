<div class="container faq bottom-padding">
	<div class="col-10 col-md-8">
		<h2 class="headline question"><?php the_title(); ?><span class="fa fa-plus pull-right"></span></h2>
		<div class="answer">
			<?php if(have_rows('faq_accordion_repeater')):?>
				<?php while(have_rows('faq_accordion_repeater')): the_row(); ?>
					<div class="question">
						<?php the_sub_field('question'); ?><span class="fa fa-plus pull-right"></span>
					</div>
					<div class="answer">
						<?php the_sub_field('answer'); ?>
					</div>
				<?php endwhile; ?>
			<?php endif; ?>
		</div><!-- title answer -->
	</div><!-- cols -->
</div><!-- container -->