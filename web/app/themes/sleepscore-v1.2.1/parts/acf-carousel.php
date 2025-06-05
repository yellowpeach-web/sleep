<?php $images = get_field('acf_carousel_gallery'); ?>
<?php if( $images ): ?>
	<div id="acf-gallery-<?php echo $post->ID; ?>" class="carousel slide bottom-padding" data-ride="carousel" data-interval="7000">
		<div class="carousel-inner">
			<?php foreach( $images as $image ): ?>
				<div class="carousel-item">
					<img class="img-fluid" src="<?php echo $image['url']; ?>" alt="<?php echo $image['alt']; ?>" />
				</div>
			<?php endforeach; ?>
		</div><!-- carousel-inner -->
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
					<?php /*?><img class="img-fluid" src="<?php echo $thumb; ?>" alt="<?php echo $alt; ?>" /><?php */?>
				</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
		
		<a class="carousel-control-prev" href="#acf-gallery-<?php echo $post->ID; ?>" role="button" data-slide="prev">
			<span class="carousel-control-prev-icon" aria-hidden="true"></span>
			<span class="sr-only">Previous</span>
		</a>
		<a class="carousel-control-next" href="#acf-gallery-<?php echo $post->ID; ?>" role="button" data-slide="next">
			<span class="carousel-control-next-icon" aria-hidden="true"></span>
			<span class="sr-only">Next</span>
		</a>
		
	</div><!-- carousel wrapper -->
<?php endif; // end if($images) ?>	