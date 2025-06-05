<?php
	require_once('inc/layout-options.php');
	$layoutOptions = new LayoutOptions();
?>
<?php if ($layoutOptions->isVisible('footer')): ?>
<footer class="top-padding">
	<?php /* <div class="footer-top">
		<div class="container top-padding">
			<?php the_field('pre_footer_email_sign_up_form', 'option'); ?>
		</div>
	</div>
	*/ ?>
	<div class="footer-bottom">
		<div class="container">
			<?php
			$app_store_image = get_field( 'app_store_image', 'option' );
			$app_store_link = get_field( 'app_store_link', 'option' );
			$play_store_image = get_field( 'play_store_image', 'option' );
			$play_store_link = get_field( 'play_store_link', 'option' );
			if( (!empty( $app_store_image ) || !empty( $play_store_image )) && $layoutOptions->isVisible('appButtons')){ ?>
				<div class="get-the-app">
					<h4>Get The App</h4>
					<?php if( !empty( $app_store_image ) &&!empty( $app_store_link ) ){ ?>
						<a id="app-page-app-store" class="app-button" href="<?php echo $app_store_link; ?>" target="_blank">
							<img src="<?php echo $app_store_image; ?>" alt="SleepScore app at App Store" width="150" height="auto">
						</a>
					<?php } ?>
					<?php if( !empty( $play_store_image ) && !empty( $play_store_link ) ){ ?>
						<a id="app-page-google-store" class="app-button" href="<?php echo $play_store_link; ?>" target="_blank">
							<img src="<?php echo $play_store_image; ?>" alt="SleepScore app at Google Play Store" width="150" height="auto">
						</a>
					<?php } ?>
				</div>
			<?php } ?>

			<nav class="row">
					<?php $footerMenu = array(
							'theme_location'  => '',
							'menu'            => 'Footer About Us',
							'container'       => 'div',
							'container_class' => 'col-6 col-sm',
							'container_id'    => '',
							'menu_class'      => 'footer-nav no-padding',
							'menu_id'         => '',
							'echo'            => true,
							'fallback_cb'     => '',
							'before'          => '',
							'after'           => '',
							'link_before'     => '',
							'link_after'      => '',
							'items_wrap'      => '<div class="footer-title">About Us</div><ul id="%1$s" class="%2$s">%3$s</ul>',
							'depth'           => 0,
							'walker'          => ''
						);
						wp_nav_menu( $footerMenu );
					?>
					<?php $footerMenu = array(
							'theme_location'  => '',
							'menu'            => 'Footer Our Family of Apps',
							'container'       => 'div',
							'container_class' => 'col-6 col-sm',
							'container_id'    => '',
							'menu_class'      => 'footer-nav no-padding',
							'menu_id'         => '',
							'echo'            => true,
							'fallback_cb'     => '',
							'before'          => '',
							'after'           => '',
							'link_before'     => '',
							'link_after'      => '',
							'items_wrap'      => '<div class="footer-title">Our Family of Apps</div><ul id="%1$s" class="%2$s">%3$s</ul>',
							'depth'           => 0,
							'walker'          => ''
						);
						wp_nav_menu( $footerMenu );
					?>
					<?php $footerMenu = array(
							'theme_location'  => '',
							'menu'            => 'Footer Join the Community',
							'container'       => 'div',
							'container_class' => 'col-6 col-sm',
							'container_id'    => '',
							'menu_class'      => 'footer-nav no-padding',
							'menu_id'         => '',
							'echo'            => true,
							'fallback_cb'     => '',
							'before'          => '',
							'after'           => '',
							'link_before'     => '',
							'link_after'      => '',
							'items_wrap'      => '<div class="footer-title">Join the Community</div><ul id="%1$s" class="%2$s">%3$s</ul>',
							'depth'           => 0,
							'walker'          => ''
						);
						wp_nav_menu( $footerMenu );
					?>
					<?php $footerMenu = array(
							'theme_location'  => '',
							'menu'            => 'Footer Customer Service & Support',
							'container'       => 'div',
							'container_class' => 'col-6 col-sm',
							'container_id'    => '',
							'menu_class'      => 'footer-nav no-padding',
							'menu_id'         => '',
							'echo'            => true,
							'fallback_cb'     => '',
							'before'          => '',
							'after'           => '',
							'link_before'     => '',
							'link_after'      => '',
							'items_wrap'      => '<div class="footer-title">Customer Service & Support</div><ul id="%1$s" class="%2$s">%3$s</ul>',
							'depth'           => 0,
							'walker'          => ''
						);
						wp_nav_menu( $footerMenu );
					?>
			</nav>

			<div class="copyright col-12">
				&copy; <?php echo date('Y');?>&nbsp;<?php the_field('text_to_display', 'option'); ?>
			</div>
			<div class="disclaimers">
				<?php the_field('page_disclaimer'); ?>
			</div>
		</div>
	</div>
	<div class="clearfix"></div>
</footer>
<?php endif; ?>
<?php // require_once('enewsletter-modal.php'); ?>
<?php wp_footer(); ?>
<?php if(is_front_page() || is_page('holidays') || is_page('home-0319')) : ?>
	<?php $detect = new Mobile_Detect; ?>
	<?php if( $detect->isMobile() && !$detect->isTablet() ){ ?>
		<script>
			$(document).ready(function() {
            	if (typeof $owl === 'undefined') {
					$(".owl-carousel").owlCarousel({
						items: 1,
						loop:true,
						dots:false,
						nav:false,
						margin:0,
						autoplay:true,
						autoplayTimeout:8000,
						smartSpeed:2000
					});
				}
			});
		</script>
	<?php } else { // display these only on desktop and tablet ?>
		<script>
			$(document).ready(function() {
            	if (typeof $owl === 'undefined') {
					$(".owl-carousel").owlCarousel({
						items: 3,
						loop:true,
						dots:false,
						nav:false,
						margin:0,
						autoplay:true,
						autoplayTimeout:8000,
						smartSpeed:2000
					});
				}
			});
		</script>
	<?php } ?>
<?php endif; ?>
<script>
$(document).ready(function() {
	// trigger lightbox
	$('.lightbox-gallery').flashy({
		galleryCounter: false,
		themeClass:"ss-lightbox",
	});
});
</script>
</body>
</html>
