<hr>
<div class="navigation col">
	<div class="row">
		<p class="col text-center product-headline">
			Read more articles related to sleep
		</p>
	</div>
	<div class="row d-flex align-items-center">
		<?php $prevPost = get_previous_post(true);
		if($prevPost) { ?>
		<?php $prevthumbnail = get_the_post_thumbnail($prevPost->ID, array(300,225) );?>
		<div class="d-none d-flex col-sm-1 nav-box previous text-center">
			<?php previous_post_link('%link',"<p class=\"hero-headline\"> < </p>", TRUE); ?>
		</div>
		<div class="col-12 col-sm-5 nav-box previous text-center align-self-start">
			<?php $prevthumbnail = get_the_post_thumbnail($prevPost->ID, array(300,225) );?>
			<?php previous_post_link('%link', $prevthumbnail, TRUE); ?>
			<?php previous_post_link('%link',"<p>%title</p>", TRUE); ?>
		</div>

		<?php } $nextPost = get_next_post(true);
			if($nextPost) { ?>
			<div class="col-12 col-sm-5 nav-box next text-center align-self-start">
				<?php $nextthumbnail = get_the_post_thumbnail($nextPost->ID, array(300,225) ); ?>
				<?php next_post_link('%link', $nextthumbnail, TRUE); ?>
				<?php next_post_link('%link',"<p>%title</p>", TRUE); ?>
			</div>
			<div class="d-none d-flex col-sm-1 nav-box previous text-center">
				<?php next_post_link('%link',"<p class=\"hero-headline\"> > </p>", TRUE); ?>
			</div>
		<?php } ?>
		<div class="clearfix"></div>
	</div><!-- row -->
</div>
