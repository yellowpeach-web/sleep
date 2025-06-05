<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="apple-itunes-app" content="app-id=1364781299, affiliate-data=SSLWebmeta, app-argument=/profile">
	<?php $auRefID = isset($_GET['aurefid']) ? $_GET['aurefid'] : ''; ?>
	<script> var phpAuRefID = "<?php  echo $auRefID; ?>"; </script>

   <!-- Google Tag Manager -->
	<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
	new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
	j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
	'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
	})(window,document,'script','dataLayer','GTM-WRHGX62');</script>
	<!-- End Google Tag Manager -->
	<meta name="msvalidate.01" content="E7F101F5349779820CD394FA5DDBF39D" /><!-- bing webmaster -->
	<?php if ((is_search()) || ($_SERVER['SERVER_NAME'] !== 'www.sleepscore.com')) { ?>
	   <meta name="robots" content="noindex, nofollow" />
	<?php } ?>

	<title><?php wp_title(); ?></title>

	<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>">

	<?php if ( is_singular() ) wp_enqueue_script('comment-reply'); ?>

	<?php wp_head(); ?>

</head>
<body <?php body_class(); ?>>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-WRHGX62"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<?php require_once 'Mobile_Detect.php'; ?>
<header class="d-flex align-items-center">
	<div class="container">
		<div class="row">
			<a id="logo" class="col-5 col-sm-7 col-md-3 col-lg-2" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php
					$image = get_field('sleepscore_logo', 'option');
					if( !empty($image) ):
					echo '<img class="img-fluid" src="' . $image['url'] . '" alt="' . $image['alt'] . '" />';
					endif;
				?>
			</a>
			<?php
				//Detect special conditions devices
				$iPod    = stripos($_SERVER['HTTP_USER_AGENT'],"iPod");
				$iPhone  = stripos($_SERVER['HTTP_USER_AGENT'],"iPhone");
				$iPad    = stripos($_SERVER['HTTP_USER_AGENT'],"iPad");
				$Android = stripos($_SERVER['HTTP_USER_AGENT'],"Android");
				$webOS   = stripos($_SERVER['HTTP_USER_AGENT'],"webOS");

				$iPhoneButton = '<a class="header-cta-button lp-cta ios pull-right" href="https://itunes.apple.com/app/apple-store/id1364781299?pt=118826298&ct=ssweb-hp-burger&mt=8">Get the App</a>';
				// $androidButton = '<a class="header-cta-button android" onclick=_dcq.push(["showForm",{id:"109316435"}]);>Get the App</a>';
				$androidButton = '<a class="header-cta-button android" href="https://play.google.com/store/apps/details?id=com.sleepscore.drive">Get the App</a>';
				$sleepScorePageBtn = '<a class="header-cta-button lp-cta pull-right" href="/sleepscore-app">Get the Free App</a>';
			?>
			<div class="col-7 col-sm-5 col-md-9 col-lg-10 text-right">
				<?php
				if( $iPhone ){
					echo $iPhoneButton;
				} else if($Android){
					echo $androidButton;
				}else {
					echo $sleepScorePageBtn;
				}
				?>
			</div>

		</div>
	</div><!-- container -->
</header>
