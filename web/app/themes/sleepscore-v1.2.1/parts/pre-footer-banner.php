<?php
	$http_referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
	$utm_source = isset($_GET['utm_source']) ? $_GET['utm_source'] : '';
 	$utm_medium = isset($_GET['utm_medium']) ? $_GET['utm_medium'] : '';
 	$utm_campaign = isset($_GET['utm_campaign']) ? $_GET['utm_campaign'] : '';
 	$utm_term = isset($_GET['utm_term']) ? $_GET['utm_term'] : '';

	if(!empty($http_referer)) {	$httpreferer = '_' . $http_referer;}
	if(!empty($utm_source)) {	$utmsource = '_' . $utm_source;}
	if(!empty($utm_medium)) {	$utmmedium = '_' . $utm_medium;}
	if(!empty($utm_campaign)) {	$utmcampaign = '_' . $utm_campaign;}
	if(!empty($utm_term)) {	$utmterm = '_' . $utm_term;}
	
	$layoutOptions = new LayoutOptions();
?>

<?php if(!get_field('hide_footer_banner') && $layoutOptions->isVisible('appBanner')) : ?>
	<?php if(have_rows('pre_footer_banner_repeater', 'option')): ?>
	<section id="download-now" class="container-fluid relative" style="background-color: <?php the_field('background_footer_column_color', 'option');?>;">
		<div class="container">
			<div class="row top-padding align-items-center">
			<?php while(have_rows('pre_footer_banner_repeater', 'option')): the_row(); ?>
				<?php the_sub_field('pre_footer_banner_custom_styling', 'option'); ?>
				<div class="col-6 offset-2 col-sm-3 offset-sm-2">
					<?php $feature_image = get_sub_field('feature_image', 'option');
					if( !empty($feature_image) ): ?>
						<?php $feature_image_link_url = get_sub_field('feature_image_link_url', 'option'); ?>
						<?php if (!empty($feature_image_link_url)): ?>
							<a href="<?= $feature_image_link_url ?>" aria-label="Get the SleepScore mobile app">
						<?php endif; ?>
					<img class="<?php the_sub_field('feature_image_classes', 'option'); ?>" src="<?php echo $feature_image['url']; ?>" alt="<?php echo $feature_image['alt']; ?>" loading="lazy" />
						<?php if (!empty($feature_image_link_url)): ?>
							</a>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<div class="<?php the_sub_field('content_classes', 'option'); ?>">
					<div class="<?php the_sub_field('headline_classes', 'option'); ?>"><?php the_sub_field('headline', 'option'); ?></div>
					<div class="<?php the_sub_field('body_copy_classes', 'option'); ?>"><?php the_sub_field('body_copy', 'option'); ?></div>
					<div>
						<?php $mobile_image = get_sub_field('mobile_image', 'option');
						if( !empty($mobile_image) ): ?>
							<?php $mobile_image_link_url = get_sub_field('mobile_image_link_url', 'option'); ?>
							<?php if (!empty($mobile_image_link_url)): ?>
								<a href="<?= $mobile_image_link_url ?>" aria-label="Get the SleepScore mobile app">
							<?php endif; ?>
						<img class="<?php the_sub_field('mobile_image_classes', 'option'); ?>" src="<?php echo $mobile_image['url']; ?>" alt="<?php echo $mobile_image['alt']; ?>" loading="lazy" />
							<?php if (!empty($mobile_image_link_url)): ?>
								</a>
							<?php endif; ?>
						<?php endif; ?>
					</div>
					<div class="buttons bottom-padding">
						<a id="footer-banner-apple-store" class="app-button" target="_blank" href="<?php the_sub_field('app_store_link_url', 'option'); ?><?php echo $utmsource . $utmmedium; ?>&mt=8&ct=sleepscore-web">
							<?php $app_store_image = get_sub_field('app_store_image', 'option');
							if( !empty($app_store_image) ): ?>
							<img class="img-fluid" src="<?php echo $app_store_image['url']; ?>" alt="<?php echo $app_store_image['alt']; ?>" loading="lazy" />
							<?php endif; ?>
						</a>
						<?php if(get_sub_field('google_play_link_url')): ?>
						<?php /* ?><a class="app-button android pointer" onclick=_dcq.push(["showForm",{id:"109316435"}]);><?php */ ?>
						<a id="footer-banner-google-store" class="app-button" target="_blank" href="<?php the_sub_field('google_play_link_url', 'option'); ?>">
							<?php $google_play_image = get_sub_field('google_play_image', 'option');
							if( !empty($google_play_image) ): ?>
							<img class="img-fluid" src="<?php echo $google_play_image['url']; ?>" alt="<?php echo $google_play_image['alt']; ?>" loading="lazy" />
							<?php endif; ?>
						</a>
						<?php endif; ?>
					</div>
					<div id="post-download-btns">
						<div class="<?php the_sub_field('post_download_buttons_text_classes', 'option'); ?>"><?php the_sub_field('post_download_buttons_text', 'option'); ?></div>
					</div>
				</div>
			<?php endwhile; ?>
			</div><!-- row -->
		</div><!-- container -->
	</section><!-- container - fluid -->
	<?php endif; ?>
<?php  endif; ?>
<?php if(!get_field('hide_hr_line')) : ?>
	<div class="container">
		<div class="row top-padding">
			<hr class="col-10 offset-1">
		</div>
	</div>
<?php endif; ?>

<?php /* // =================== email form ?>
<?php if(!get_field('hide_footer_email_opt_in')): ?>
	<div class="container top-padding">
		<?php the_field('pre_footer_email_sign_up_form', 'option'); ?>
	</div>
<?php endif; */ ?>
<?php if(!get_field('hide_social_follow')): ?>
	<div class="col-12 col-lg-6 offset-lg-3 footer-email-sign-up top-padding-15 bottom-padding">
		<?php get_template_part('inc/social-follow'); ?>
	</div>
<?php endif; ?>

<?php if(get_field('show_exit_intent')): ?>
<?php /*
<script type="text/javascript" src="//downloads.mailchimp.com/js/signup-forms/popup/unique-methods/embed.js" data-dojo-config="usePlainJson: true, isDebug: false"></script><script type="text/javascript">window.dojoRequire(["mojo/signup-forms/Loader"], function(L) { L.start({"baseUrl":"mc.us14.list-manage.com","uuid":"6ea733e3aa723906b12509c5c","lid":"3384099a01","uniqueMethods":true}) })</script>
*/ ?>
<?php endif; ?>
