<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="apple-itunes-app" content="app-id=1364781299, affiliate-data=SSLWebmeta, app-argument=/profile">
	<link rel="manifest" href="/manifest.json">
	<?php require_once('inc/layout-options.php'); ?>
	<?php $layoutOptions = new LayoutOptions(); ?>
	<?php
	$http_referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
	$utm_source = isset($_GET['utm_source']) ? $_GET['utm_source'] : '';
	$utm_medium = isset($_GET['utm_medium']) ? $_GET['utm_medium'] : '';
	$utm_campaign = isset($_GET['utm_campaign']) ? $_GET['utm_campaign'] : '';
	$utm_term = isset($_GET['utm_term']) ? $_GET['utm_term'] : '';

	if(!empty($http_referer)) {	$httpreferer = '&' . $http_referer;}
	if(!empty($utm_source)) {	$utmsource = '&' . $utm_source;}
	if(!empty($utm_medium)) {	$utmmedium = '&' . $utm_medium;}
	if(!empty($utm_campaign)) {	$utmcampaign = '&' . $utm_campaign;}
	if(!empty($utm_term)) {	$utmterm = '&' . $utm_term;}
	?>
	<?php $auRefID = isset($_GET['aurefid']) ? $_GET['aurefid'] : ''; ?>
	<?php $numberOfSlides; // carousel slides global ?>
	<script> var phpAuRefID = "<?php  echo $auRefID; ?>"; </script>
	<meta name="msvalidate.01" content="E7F101F5349779820CD394FA5DDBF39D" /><!-- bing webmaster -->
	<!-- Google Tag Manager -->
	<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
	new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
	j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
	'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
	})(window,document,'script','dataLayer','GTM-WRHGX62');</script>
	<!-- End Google Tag Manager -->

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
<?php if ($layoutOptions->isVisible('header')): ?>
	<?php if (get_field('announcement_banner_text', 'option')): ?>
		<?php if (get_field('announcement_banner_link_url', 'option')): ?>
			<a 
				href="<?php the_field('announcement_banner_link_url', 'option'); ?>"
				<?php if ( get_field('announcement_banner_open_link_in_new_tab', 'option') == 'Yes' ): ?>
				target="blank" rel="noopener"
				<?php endif; ?>
			>
		<?php endif; ?>
		<div class="announcement-banner">
			<div class="container">
				<?php the_field('announcement_banner_text', 'option'); ?>
				<?php if (get_field('announcement_banner_link_text', 'option') && get_field('announcement_banner_link_url', 'option')): ?>
					<u class="text-underline"><?php the_field('announcement_banner_link_text', 'option'); ?></u>
				<?php endif; ?>
			</div>
		</div>
		<?php if (get_field('announcement_banner_link_url', 'option')): ?>
			</a>
		<?php endif; ?>
	<?php endif; ?>
	<header class="header-main">

		<div class="container">
			
			<div class="d-lg-flex align-items-lg-center w-100">

				<div class="d-flex align-items-center">
					<a id="logo" class="" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="SleepScore Labs">
						<svg id="logo_2_lines_negative" data-name="logo 2 lines negative" xmlns="http://www.w3.org/2000/svg" width="100%" viewBox="0 0 270.942 63.248">
							<g id="icon" transform="translate(-6536.349 -1616.955)">
								<path id="Path_678" data-name="Path 678" d="M6570.484,1658.333a20.691,20.691,0,1,1,20.689-20.689A20.713,20.713,0,0,1,6570.484,1658.333Zm0-38.2a17.505,17.505,0,1,0,14.2,7.263A17.529,17.529,0,0,0,6570.484,1620.135Z" fill="#fdf0e4"/>
								<path id="Path_679" data-name="Path 679" d="M6570.761,1658.3c-8.768,0-15-4.749-20.5-8.94-4.344-3.312-8.1-6.171-12.24-6.171h-1.675v-3.18h1.675c5.219,0,9.566,3.313,14.168,6.822,5.346,4.075,10.875,8.289,18.569,8.289,6.392,0,12.063-4.09,17.547-8.046,5.037-3.634,9.795-7.065,14.844-7.065h1.53v3.18h-1.53c-4.022,0-8.375,3.14-12.983,6.464C6584.549,1653.71,6578.18,1658.3,6570.761,1658.3Z" fill="#fdf0e4"/>
								<path id="Path_680" data-name="Path 680" d="M6570.761,1665.325c-8.633,0-15.253-3.222-21.093-6.064-4.323-2.1-8.056-3.92-11.644-3.92h-1.675v-3.18h1.675c4.321,0,8.554,2.06,13.036,4.241,5.531,2.692,11.8,5.742,19.7,5.742,7.514,0,13.041-2.782,18.385-5.473,4.607-2.32,8.959-4.51,14.006-4.51h1.53v3.18h-1.53c-4.292,0-8.13,1.932-12.576,4.17C6585.164,1662.236,6579.03,1665.325,6570.761,1665.325Z" fill="#fdf0e4"/>
								<path id="Path_681" data-name="Path 681" d="M6570.761,1672.436a58.92,58.92,0,0,1-17.919-2.821,43.293,43.293,0,0,0-12.87-2.218h-3.623v-3.18h3.623a45.912,45.912,0,0,1,13.745,2.34,55.448,55.448,0,0,0,17.044,2.7,50.964,50.964,0,0,0,17.5-2.791,37.863,37.863,0,0,1,12.532-2.248h3.885v3.18H6600.8a34.764,34.764,0,0,0-11.583,2.1A54.347,54.347,0,0,1,6570.761,1672.436Z" fill="#fdf0e4"/>
								<rect id="Rectangle_70" data-name="Rectangle 70" width="68.333" height="3.18" transform="translate(6536.349 1676.321)" fill="#fdf0e4"/>
							</g>
							<g id="type" transform="translate(-6536.349 -1616.955)">
								<path id="Path_682" data-name="Path 682" d="M6620.262,1635.468l4.572-.914c.665,3.533,3.076,5.611,6.9,5.611,3.2,0,4.863-1.247,4.863-3.242,0-2.037-1.455-3.076-6.152-3.782-6.9-1.081-9.1-3.865-9.1-7.648,0-4.406,3.242-7.232,9.31-7.232,6.567,0,9.227,3.533,10.183,8.1l-4.655.956c-.789-3.284-2.2-5.029-5.736-5.029-2.784,0-4.322,1.247-4.322,3.117,0,1.663.914,2.868,6.4,3.782,7.024,1.122,8.894,4.032,8.894,7.565,0,4.489-3.117,7.523-9.975,7.523C6623.878,1644.238,6620.844,1640.289,6620.262,1635.468Z" fill="#fdf0e4"/>
								<path id="Path_683" data-name="Path 683" d="M6647.984,1643.739H6643.5v-25.478h4.489Z" fill="#fdf0e4"/>
								<path id="Path_684" data-name="Path 684" d="M6649.936,1634.221c0-6.608,3.907-10.515,9.976-10.515,5.943,0,8.936,3.74,8.936,9.1,0,.79,0,1.58-.083,2.619h-14.34c.416,3.283,2.328,4.9,5.528,4.9,3.118,0,4.281-1.538,4.863-3.533l3.907,1.122c-1,3.741-3.658,6.318-8.811,6.318C6653.927,1644.238,6649.936,1640.664,6649.936,1634.221Zm4.531-2.119h10.225c-.25-3.035-1.829-4.614-4.9-4.614C6656.836,1627.488,6654.966,1628.943,6654.467,1632.1Z" fill="#fdf0e4"/>
								<path id="Path_685" data-name="Path 685" d="M6669.886,1634.221c0-6.608,3.907-10.515,9.975-10.515,5.943,0,8.936,3.74,8.936,9.1,0,.79,0,1.58-.083,2.619h-14.34c.416,3.283,2.328,4.9,5.528,4.9,3.118,0,4.281-1.538,4.863-3.533l3.907,1.122c-1,3.741-3.657,6.318-8.811,6.318C6673.876,1644.238,6669.886,1640.664,6669.886,1634.221Zm4.53-2.119h10.225c-.25-3.035-1.829-4.614-4.905-4.614C6676.785,1627.488,6674.915,1628.943,6674.416,1632.1Z" fill="#fdf0e4"/>
								<path id="Path_686" data-name="Path 686" d="M6702.3,1644.28c-3.907,0-6.069-2.12-6.942-6.11h-.083v11.264h-4.488V1624.2h4.488v5.528h.083c.915-3.949,3.159-6.027,6.983-6.027,5.029,0,7.648,3.865,7.648,10.183C6709.993,1640.373,6707.25,1644.28,6702.3,1644.28Zm3.241-10.391c0-3.907-1.579-6.152-5.029-6.152-3.242,0-5.237,2.162-5.237,6.027v.332c0,3.741,2,6.152,5.237,6.152C6703.883,1640.248,6705.545,1637.879,6705.545,1633.889Z" fill="#fdf0e4"/>
								<path id="Path_687" data-name="Path 687" d="M6712.033,1635.712l4.572-.915c.665,3.533,3.076,5.611,6.9,5.611,3.2,0,4.863-1.247,4.863-3.242,0-2.036-1.455-3.075-6.152-3.782-6.9-1.081-9.1-3.865-9.1-7.648,0-4.4,3.242-7.232,9.31-7.232,6.567,0,9.227,3.533,10.183,8.1l-4.655.956c-.79-3.283-2.2-5.029-5.736-5.029-2.784,0-4.322,1.247-4.322,3.117,0,1.663.914,2.868,6.4,3.782,7.024,1.123,8.894,4.032,8.894,7.565,0,4.489-3.117,7.523-9.975,7.523C6715.649,1644.481,6712.615,1640.533,6712.033,1635.712Z" fill="#fdf0e4"/>
								<path id="Path_688" data-name="Path 688" d="M6744.326,1628.022c-3.2,0-5.444,1.995-5.444,6.193,0,3.99,2.2,6.193,5.528,6.193,3.283,0,4.447-2.078,5.07-4.863l4.281.873c-.789,4.987-3.616,8.1-9.268,8.1-6.152,0-10.183-3.616-10.183-10.266,0-6.484,4.115-10.308,10.058-10.308,5.861,0,8.562,2.91,9.352,7.939l-4.323.706C6748.815,1629.851,6747.361,1628.022,6744.326,1628.022Z" fill="#fdf0e4"/>
								<path id="Path_689" data-name="Path 689" d="M6754.8,1634.174c0-6.442,3.99-10.225,10.017-10.225,5.985,0,9.975,3.783,9.975,10.225,0,6.4-3.782,10.349-9.975,10.349C6758.54,1644.523,6754.8,1640.575,6754.8,1634.174Zm4.489-.042c0,3.783,1.953,6.276,5.486,6.276,3.575,0,5.57-2.493,5.57-6.276,0-3.74-2.078-6.11-5.528-6.11C6761.325,1628.022,6759.288,1630.392,6759.288,1634.132Z" fill="#fdf0e4"/>
								<path id="Path_690" data-name="Path 690" d="M6776.743,1643.983v-19.535h4.489v6.4h.083c.873-5.071,2.618-6.4,5.569-6.4h1.122v5.029H6786.1c-3.492,0-4.863,1.2-4.863,4.614v9.892Z" fill="#fdf0e4"/>
								<path id="Path_691" data-name="Path 691" d="M6788.38,1634.465c0-6.609,3.907-10.516,9.975-10.516,5.943,0,8.936,3.741,8.936,9.1,0,.789,0,1.579-.083,2.618h-14.339c.415,3.283,2.327,4.9,5.527,4.9,3.118,0,4.281-1.538,4.863-3.533l3.907,1.122c-1,3.741-3.657,6.317-8.811,6.317C6792.37,1644.481,6788.38,1640.907,6788.38,1634.465Zm4.53-2.12h10.225c-.25-3.034-1.829-4.613-4.9-4.613C6795.279,1627.732,6793.409,1629.186,6792.91,1632.345Z" fill="#fdf0e4"/>
								<path id="Path_692" data-name="Path 692" d="M6627.435,1675.191h11.225v4.473h-15.947v-24.852h4.722Z" fill="#fdf0e4"/>
								<path id="Path_693" data-name="Path 693" d="M6652.66,1674.777h-.083c-.911,3.479-3.272,5.3-6.627,5.3-3.6,0-6.13-1.823-6.13-5.551,0-3.562,2.154-6.13,7.041-6.13h5.8v-.787c0-2.651-1.077-4.1-3.769-4.1-2.4,0-3.77,1.077-4.225,3.894l-4.266-.58c.58-4.349,3.148-7.041,8.656-7.041,5.633,0,8.036,2.816,8.036,7.787v12.094h-4.432Zm0-2.444v-.621h-5.136c-2.154,0-3.314.787-3.314,2.112,0,1.367.87,2.609,3.4,2.609C6650.672,1676.433,6652.66,1674.694,6652.66,1672.333Z" fill="#fdf0e4"/>
								<path id="Path_694" data-name="Path 694" d="M6664.382,1665.706h.082c.912-3.935,3.148-6.006,6.959-6.006,5.012,0,7.621,3.852,7.621,10.148,0,6.462-2.733,10.355-7.662,10.355-3.894,0-6.048-2.113-6.918-6.089h-.082v5.55h-4.474v-25.39h4.474Zm10.231,4.142c0-3.894-1.575-6.13-5.012-6.13-3.231,0-5.219,2.153-5.219,6.006v.331c0,3.728,1.988,6.13,5.219,6.13C6672.956,1676.185,6674.613,1673.824,6674.613,1669.848Z" fill="#fdf0e4"/>
								<path id="Path_695" data-name="Path 695" d="M6679.832,1673.493l4.183-.829c.621,2.444,1.864,4.1,4.971,4.1,2.36,0,3.52-.911,3.562-2.444,0-1.408-.994-2.154-4.888-2.858-5.26-1.035-7.124-2.858-7.124-5.964,0-3.521,2.568-5.8,7.663-5.8,5.3,0,7.5,2.278,8.325,6.006l-4.266.953c-.58-2.569-1.74-3.645-4.059-3.645-2.113,0-3.231.869-3.231,2.278,0,1.367.787,2.278,4.722,2.941,5.426.994,7.248,2.982,7.248,5.964,0,3.645-2.526,6.006-8.035,6.006C6682.648,1680.2,6680.536,1677.3,6679.832,1673.493Z" fill="#fdf0e4"/>
								<path id="Path_696" data-name="Path 696" d="M6697.74,1660.264h7.149v1.729h-2.57v6.331h-2.009v-6.331h-2.57Zm14.088,8.083h-1.706l-1.027-2.616c-.585-1.472-1.052-2.687-1.449-3.762h-.07c.023,1.028.046,2.313.046,3.435v2.92h-1.728v-8.06h3.154l.817,2.289c.4,1.052.935,2.383,1.285,3.552h.071c.373-1.169.864-2.454,1.261-3.575l.865-2.266h2.9v8.06h-1.986v-2.92c0-1.122.046-2.407.07-3.435h-.07c-.374,1.075-.865,2.43-1.379,3.715Z" fill="#fdf0e4"/>
							</g>
						</svg>
					</a>
					<button class="navbar-toggler d-flex flex-column ml-auto d-block d-lg-none" type="button" data-toggle="collapse" data-target="#primary-nav" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar top-bar"></span>
						<span class="icon-bar mid-bar"></span>
						<span class="icon-bar bottom-bar"></span>
					</button>
				</div>
				<nav id="nav-wrap" class="navbar navbar-expand-lg ml-lg-auto">
					<div id="primary-nav" class="collapse navbar-collapse justify-content-end">
						<?php
							$menu = array(
								'theme_location'  => '',
								'menu'            => 'Primary',
								'container'       => '',
								'container_class' => '',
								'container_id'    => '',
								'menu_class'      => 'navbar-nav',
								'menu_id'         => '',
								'echo'            => true,
								'fallback_cb'     => 'wp_page_menu',
								'before'          => '',
								'after'           => '',
								'link_before'     => '',
								// 'link_after'      => '<div class="underline"></div>',
								'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
								'depth'           => 0,
								'walker'          => new Primary_Walker_Nav_Menu()
							);
							wp_nav_menu( $menu );
						?>
					</div>
				</nav>
			</div>
		</div>
	</header>
<?php endif; ?>
