<div class="container-fluid ">
	<div class="row align-items-center">
		<div class="col-12 col-md-6 no-padding">
			<?php $images = get_sub_field('bootstrap_gallery_carousel'); ?>
			<?php if( $images ): ?>
				<div class="carousel slide carousel-fade" data-ride="carousel" data-interval="5000">
					<div class="carousel-inner">
						<?php foreach( $images as $image ): ?>
							<div class="carousel-item">
								<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
							</div>
						<?php endforeach; ?>
					</div><!-- carousel-inner -->
					<?php if(get_sub_field('show_indicators')): ?>
						<ul class="carousel-indicators">
							<?php $i = '-1'; ?>
							<?php foreach( $images as $image ): ?>
								<?php
									if( !empty($image) ):
										// vars
										$url = $image['url'];
										$title = $image['title'];
										$alt = $image['alt'];

										// thumbnail
										$size = 'thumbnail';
										$thumb = $image['sizes'][ $size ];
										$width = $image['sizes'][ $size . '-width' ];
										$height = $image['sizes'][ $size . '-height' ];
								?>
								<li data-target="#acf-gallery-<?php echo $post->ID; ?>" data-slide-to="<?php echo ++$i; ?>" class="active">
									<img class="img-fluid" src="<?php echo $thumb; ?>" alt="<?php echo $alt; ?>" />
								</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if(get_sub_field('show_indicators')): ?>
						<a class="carousel-control-prev" href="#acf-gallery-<?php echo $post->ID; ?>" role="button" data-slide="prev">
							<span class="carousel-control-prev-icon" aria-hidden="true"></span>
							<span class="sr-only">Previous</span>
						</a>
						<a class="carousel-control-next" href="#acf-gallery-<?php echo $post->ID; ?>" role="button" data-slide="next">
							<span class="carousel-control-next-icon" aria-hidden="true"></span>
							<span class="sr-only">Next</span>
						</a>
					<?php endif; ?>
				</div><!-- carousel wrapper -->
			<?php endif; // end if($images) ?>
		</div><!-- col classes -->
		<div class="col-12 col-md-5 offset-md-1">
			<div class="hero-headline">Get to know the</div>
			<div class="white-font hero-headline">SleepScore Ambassadors</div>
		</div>
	</div><!-- row -->
</div><!-- container-fluid -->
