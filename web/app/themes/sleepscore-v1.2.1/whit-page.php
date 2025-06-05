<?php
/*
Template Name: Whit
*/
?>
<?php get_header('empty'); ?><!-- -->
<!-- xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
WHIT - page
xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx -->
<style>

	:root {
		--primary-color: #464C9C;
		--text-color: #333;
	}

	.modal-header {
		background-color: var(--primary-color);
		color: #fff; 
		font-size: 40px;
	}

	.modal-content {
		color: var(--text-color);
	}

	.modal-content .headline {
		color: var(--primary-color);
	}

	.modal-content p {
		color: var(--text-color);
	}

	.modal-content .btn-primary,
	.modal-content .btn-primary:active {
		background-color: var(--primary-color);
		border-color: var(--primary-color);
		color: #fff;
	}

	.whit-content .headline {
		color: var(--primary-color);
	}

	#exampleModal {
		padding-right: 0 !important;
	}

	.whit-button[style="top:%; left:%;"] {
		display: none;
	}

	a.tab-button,
	.tab-button {
		display: inline-block;
		width:200px;
		background: #fff;
		border-radius: 15px 15px 0 0;
		padding: 5px 15px;
		text-align: center;
		border: none;
		color: var(--primary-color);
	}
	a.tab-button:visited,
	a.tab-button:link {
		color: #000;
	}
	.tab-button  + .tab-button  {
		margin-left: 15px;
	}
	#whit-splash {
		position: fixed;
		top: 0%;
		left: 0;
		right: 0;
		bottom:0;
		z-index: 111;
		text-align: center;
		padding: 120px;
		background-size: cover;
		background-position: center center;
	}
	#whit-splash .logo {
		max-width: 760px;
	}

	#whit-main {
		display: none;
	}
	#whit-footer {
		position: fixed;
		bottom: 0;
		right: 0;
		z-index: 101;
		padding: 15px;
	}
	#whit-footer img {
		margin-left: 20px;
		margin-right: 20px;
		height: auto;
		width: 140px;
	}
	#sleep-concerns,
	#sleep-routines {
		display: none;
		position: absolute;
		top:50px;
		left:0px;
		right:0px;
		min-height:600px;
		z-index: 111;
		background: #fff;
		color: var(--text-color);
	}
	#sleep-concerns p,
	#sleep-routines p {
		color: var(--text-color);
	}
	#trigger-concerns.active ,
	#trigger-routines.active {
		color: var(--text-color) !important;
		background: #fff !important;
	}
	.nav .nav-logo a {
		display: block;
		max-width: 160px;
	}
	.nav .tabs {
		display: flex;
		justify-content: flex-end;
		align-items: flex-end;
	}
	.close-tab {
		position: absolute;
		top: 30px;
		right: 30px;
		z-index: 1001;
		color: var(--primary-color) !important;
	}
	.title-banner {
		background-color: var(--primary-color);
	}
	@media (max-width: 767px) {
		.nav .nav-logo {
			display: flex;
			justify-content: center;
		}
		.nav .tabs {
			justify-content: center;
		}
	}
</style>
<?php $logoImg = get_field('sleepscore_logo_white_2_lines', 'option'); ?>
<main class="container-fluid vh100 no-top-bottom-margin whit-content">
	<?php // ======================================================================== top nav and header ?>
	<div class="row nav top-padding-15">
		<div class="col-sm-12 col-md-2 nav-logo">
			<a href="/"><?php echo $logoImg; ?></a>
		</div>
		<div class="col-sm-12 col-md-10 text-right tabs">
			<button type="button" id="whit-bedroom" class="tab-button"  data-toggle="modal" data-target="#whitBedroom">WHIT Bedroom</button>
			<a id="trigger-concerns" class="tab-button" href="#">Sleep Concerns</a>
			<a id="trigger-routines" class="tab-button" href="#">Sleep Routines</a>
		</div> 
	</div>
	<div class="menu-buttons" style="display: none;"></div>
	<div class="row relative">
		<?php
			$image = get_field('whit_homepage_bg_img');
			$size = 'full'; // (thumbnail, medium, large, full or custom size)
			if( $image ) {
				echo wp_get_attachment_image( $image, $size, '', array('class' => 'img-fluid'));
			}
		?>
		<?php // ======================================================================= this is the White Bedroom modal ?>
		<div class="modal fade" id="whitBedroom" tabindex="-1" role="dialog" aria-labelledby="whitBedroomLabel" aria-hidden="true" style="border:none;">
			<div class="modal-dialog-centered modal-dialog modal-lg" role="document">
				<div class="modal-content">
					<div class="modal-header white-font">
						<div class="modal-title" id="exampleModalLongTitle">WHIT Bedroom</div>
						<button type="button" class="close white-font" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body" style="background-color:#fff; color:#123962; padding:30px 90px">
						<div class="headline">Good days start with good nights</div>
						<p>Welcome to the world's first perfect bedroom! A partnership between SleepScore Labs and the WHIT Home, the SleepScore Sleep Sanctuary features products exclusively curated and validated by the sleep science team at SleepScore Labs. The products and solutions selected for the bedroom have been shown to help people sleep better to become their best selves each and every day. Enjoy your stay!</p>
						<p>We're the sleep company changing the world by changing the way we sleep.</p>
						<p>Start your sleep improvement journey with us.</p>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>
		<?php // =================================== this is the splash screen	?>
		<div id="whit-splash" class="text-center white-font top-bottom-90 bottom-padding" style="background-image: url(<?php the_field('splash_bg_img'); ?>)">
			<div class="container logo top-padding">
				<?php if( !empty($logoImg) ): ?>
					<?php echo $logoImg ?>
					<!-- <img src="<?php echo $logoImg['url']; ?>" alt="<?php echo $logoImg['alt']; ?>" class="img-fluid top-padding padding-left-right-60" /> -->
				<?php endif; ?>
			</div>

			<div class="hero-headline bottom-padding-15">
				Sleep Sanctuary
			</div>
			<button class="enter blue-button" style="border:none">Enter</button>
		</div>
		<?php // =================================== this is the main content ?>
		<div id="whit-main">
			<div>
				<?php if(have_rows('whit_product_repeater')): ?>
				<?php $counter = 1; ?>
				<?php while(have_rows('whit_product_repeater')): the_row(); ?>
					<?php $PRODUCT_ID =	get_sub_field('product_id'); ?>

					<?php
						$API_KEY = 'a41924c346e6dddac72f5a873635b6f8';
						$SECRET = '15bae8db15192b7d41bc1452e18c72f1';
						$TOKEN = 'f8588c0d534e69b9741084b6c56ffab0';
						$STORE_URL = 'sleepscore-test.myshopify.com';

						// Call one for title and image
						$productUrl = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/products/' . $PRODUCT_ID . '.json?fields=title,image,handle,status';

						$session = curl_init();
						curl_setopt($session, CURLOPT_URL, $productUrl);
						curl_setopt($session, CURLOPT_HEADER, false);
						curl_setopt($session, CURLOPT_HTTPHEADER, array('accept: application/json', 'cache-control: no-cache', 'content-type: application/json'));
						curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
						$productResponse = curl_exec($session);
						curl_close($session);
						$productJson = json_decode($productResponse, true);
						// call 2 for description
						//https://a41924c346e6dddac72f5a873635b6f8:15bae8db15192b7d41bc1452e18c72f1@sleepscore-test.myshopify.com/admin/products/1531277836368/metafields.json
						//https://a41924c346e6dddac72f5a873635b6f8:15bae8db15192b7d41bc1452e18c72f1@sleepscore-test.myshopify.com/admin/products/1511585677392/metafields.json
						$metafieldUrl = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/products/' . $PRODUCT_ID . '/metafields.json';

						$session2 = curl_init();
						curl_setopt($session2, CURLOPT_URL, $metafieldUrl);
						curl_setopt($session2, CURLOPT_HEADER, false);
						curl_setopt($session2, CURLOPT_HTTPHEADER, array('accept: application/json', 'cache-control: no-cache', 'content-type: application/json'));
						curl_setopt($session2, CURLOPT_RETURNTRANSFER, true);
						$metafieldResponse = curl_exec($session2);
						curl_close($session2);
						$metaJson = json_decode($metafieldResponse, true);

						// Variables
						$myTitle = $productJson['product']['title'];
						$myImg = $productJson['product']['image']['src'];
						$myDesc =$metaJson['metafields'][0]['value'];
						$prodLink = 'https://shop.sleepscore.com';

						// if product is active, link directly to the product in the shop
						if ($productJson['product']['status'] === 'active') {
							$prodLink .= '/products/'.$productJson['product']['handle'];
						}
					?>
					<div id="div-<?php echo $counter++; ?>" class="d-none">
						<div class="col-12 text-center">
							<div class="product-title headline"><?php echo $myTitle ?></div>
							<div class="product-description bottom-padding-15"><?php echo $myDesc ?></div>
							<div class="product-image" style="display:block; margin:0 auto; max-width: 400px;">
								<img class="img-fluid" src=<?php echo $myImg; ?> onerror="this.remove();" />
							</div>
							<?php if ($prodLink): ?>
							<div class="product-link mt-3">
								<a href="<?php echo $prodLink; ?>" class="btn btn-primary" target="_blank">
									Learn More 
								</a>
							</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endwhile; ?>
				<?php endif; ?>
				<?php if(have_rows('product_repeater')): ?>
				<?php $counter2 = 1; ?>
				<?php while(have_rows('product_repeater')): the_row(); ?>
					<div id="div-<?php echo $counter2++; ?>ns" class="d-none">
						<div class="col-12 text-center">
							<div class="product-title headline"><?php the_sub_field('product_name'); ?></div>
							<div class="product-description bottom-padding-15"><?php the_sub_field('product_desc'); ?></div>
							<div class="product-image" style="display:block; margin:0 auto; max-width: 400px;">
								<?php $image = get_sub_field('product_image'); ?>
								<?php $size = 'large'; ?>
								<?php if( $image ) { echo wp_get_attachment_image( $image, $size, "", ["class" => "img-fluid"]);} ?>
							</div>
							<div class="product-link d-none"><?php the_sub_field('product_link'); ?></div>
						</div>
					</div>
				<?php endwhile; ?>
				<?php endif; ?>
			</div>

			<?php // ================================================== these are the button dots ?>
			<?php $buttonCounter = 1; ?>
			<div class="buttons">
				<?php if(have_rows('whit_product_repeater')): ?>
				<?php while(have_rows('whit_product_repeater')): the_row(); ?>
					<button type="button" class="whit-button" data-toggle="modal" data-target="#exampleModal" data-whatever="#div-<?php echo $buttonCounter++; ?>" style="top:<?php the_sub_field('top'); ?>%; left:<?php the_sub_field('left'); ?>%;" data-product="<?php the_sub_field('product_name'); ?>" data-id="<?= the_sub_field('product_id') ?>"></button>				
				<?php endwhile; ?>
				<?php endif; ?>
				<?php $buttonCounter2 = 1; ?>
				<?php if(have_rows('product_repeater')): ?>
				<?php while(have_rows('product_repeater')): the_row(); ?>
					<button type="button" class="whit-button" data-toggle="modal" data-target="#exampleModal" data-whatever="#div-<?php echo $buttonCounter2++; ?>ns" style="top:<?php the_sub_field('top'); ?>%; left:<?php the_sub_field('left'); ?>%;" data-product="<?php the_sub_field('product_name'); ?>"></button>
				<?php endwhile; ?>
				<?php endif; ?>
			</div>
		</div><!-- whit-main -->

		<?php // ====================================================================================== this is the modal ?>
		<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
		<div class="modal-dialog-centered modal-dialog modal-lg" role="document">
			<div class="modal-content">
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
			</div>
			</div>
		</div>
		</div>

	</div><!-- row -->

	<?php // ==================================================================================================== footer ?>
	<div id="whit-footer" class="row top-padding-15 bottom-padding-15">
		<div class="col-12 d-flex justify-content-end">
			<?php /*if( !empty($logoImg) ): ?>
			<img src="<?php echo $logoImg['url']; ?>" alt="<?php echo $logoImg['alt']; ?>" />
			<?php endif; */ ?>
		</div>
	</div>

	<?php // ==================================================================================================== Sleep Concerns ?>
	<div id="sleep-concerns">
		<?php if(have_rows('sleep_concerns_repeater')): ?>
			<?php while(have_rows('sleep_concerns_repeater')): the_row(); ?>
				<a class="close-tab font-20"><i class="fa fa-times"></i></a>
				<?php if(get_sub_field('start_row')): ?>
				<div class="<?php the_sub_field('row_classes'); ?>">
				<?php endif; ?>
					<div class="<?php the_sub_field('bootstrap_classes'); ?>" style="background-image: url(<?php the_sub_field('column_bg_image'); ?>);">
						<?php the_sub_field('bootstrap_content'); ?>
					</div><!-- Bootstrap Content -->
				<?php if(get_sub_field('end_row')): ?>
				</div><!-- row -->
				<?php endif; ?>
			<?php endwhile; ?>
		<?php endif; ?>
	</div>

	<?php // ==================================================================================================== Sleep Routines ?>
	<div id="sleep-routines">
		<?php if(have_rows('routines')): ?>
			<?php while(have_rows('routines')): the_row(); ?>
				<?php if(get_row_layout() == "routines_section"): ?>
					<div class="<?php the_sub_field('section_bg_image_classes'); ?>" style="background-image: url(<?php the_sub_field('section_bg_image'); ?>);">
						<a class="close-tab font-20 white-font"><i class="fa fa-times"></i></a>
						<?php if(have_rows('sleep_routines_repeater')): ?>
							<?php while(have_rows('sleep_routines_repeater')): the_row(); ?>
								<?php if(get_sub_field('start_row')): ?>
								<div class="<?php the_sub_field('row_classes'); ?>" >
								<?php endif; ?>
									<div class="<?php the_sub_field('bootstrap_classes'); ?>" style="background-image: url(<?php the_sub_field('column_bg_image'); ?>);">
										<?php the_sub_field('bootstrap_content'); ?>
									</div>
								<?php if(get_sub_field('end_row')): ?>
								</div>
								<?php endif; ?>
							<?php endwhile; ?>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endwhile; ?>
		<?php endif; ?>
	</div>

</main>

<?php
function last_in_footer() { ?>
    <script>
	$( document ).ready(function() {
		"use strict";
		$('#whit-splash .enter').click(function(e){
			e.preventDefault();
			$('#whit-splash').fadeOut(function() { 
				$('#whit-main').fadeIn(); 
			});
			$('#whitBedroom').modal('show'); 
		});
		$('#trigger-concerns').click(function(e) {
			e.preventDefault();
			$('#sleep-concerns').slideToggle('slow');
			$('#trigger-concerns').addClass('active');
			$('#sleep-routines').hide();
			$('#trigger-routines').removeClass('active');
		});
		$('#trigger-routines').click(function(e) {
			e.preventDefault();
			$('#sleep-routines').slideToggle('slow');
			$('#trigger-routines').addClass('active');
			$('#sleep-concerns').hide();
			$('#trigger-concerns').removeClass('active');
		});
		$('.close-tab').click(function(e) {
			e.preventDefault();
			$('#sleep-routines, #sleep-concerns').slideUp()
			$('#trigger-routines, #trigger-concerns').removeClass('active');

		});

		$('#exampleModal').on('show.bs.modal', function(e) {
			var $button = $(e.relatedTarget); // Button that triggered the modal
			var callDiv = $button.data('whatever'); // Extract info from data-* attributes
			var $displayProd = $(callDiv).clone();
			var $modal = $(this);
			$modal.find('.modal-body').html($displayProd);
			var newTitle = $modal.find('.modal-body .headline').clone();
			$modal.find('.modal-title').html(newTitle);
			$modal.find('.modal-body .headline').addClass('d-none');
			$modal.find('.modal-body div').removeClass('d-none');
		});

	});
</script>
<?php }
add_action( 'wp_footer', 'last_in_footer', 100 );
?>

<?php get_footer('empty'); ?>
