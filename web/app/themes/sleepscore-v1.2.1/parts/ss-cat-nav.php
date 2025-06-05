<?php // these are in theme settings/ sleep shop/shop by Category after trigger icon
	$beNL = get_field('bedding_nav_link', 'option');
	$beI = get_field('bedding_icon', 'option');
	$beNT = get_field('bedding_nav_text', 'option');
	$liNL = get_field('lighting_nav_link', 'option');
	$liI = get_field('lighting_icon', 'option');
	$liNT = get_field('lighting_nav_text', 'option');
	$elNL = get_field('electronics_nav_link', 'option');
	$elI = get_field('electronics_icon', 'option');
	$elNT = get_field('electronics_nav_text', 'option');
	$loNL = get_field('lotions_nav_link', 'option');
	$loI = get_field('lotion_icon', 'option');
	$loNT = get_field('lotions_nav_text', 'option');
	$snNL = get_field('snoring_nav_link', 'option');
	$snI = get_field('snoring_icon', 'option');
	$snNT = get_field('snoring_nav_text', 'option');
	$grNL = get_field('grinding_nav_link', 'option');
	$grI = get_field('grinding_icon', 'option');
	$grNT = get_field('grinding_nav_text', 'option');
	$toNL = get_field('topics_nav_link', 'option');
	$toI = get_field('topics_icon', 'option');
	$toNT = get_field('topics_nav_text', 'option');
	$alNL = get_field('all_nav_link', 'option');
	$alI = get_field('all_icon', 'option');
	$alNT = get_field('all_nav_text', 'option');
	
	$ssCatNav = '
		<a class="ss-cat-nav scrolling-link col-6 col-lg" href="' . $beNL . '">
			<img class="square-24 d-inline-block d-lg-none" src="' . $beI . '" />
			<span class="">' . $beNT . '</span>
		</a>
		<a class="ss-cat-nav scrolling-link col-6 col-lg" href="' . $liNL . '">
			<img class="square-24 d-inline-block d-lg-none" src="' . $liI . '" />
			<span class="">' . $liNT . '</span>
		</a>
		<a class="ss-cat-nav scrolling-link col-6 col-lg" href="' . $elNL . '">
			<img class="square-24 d-inline-block d-lg-none" src="' . $elI . '" />
			<span class="">' . $elNT . '</span>
		</a>
		<a class="ss-cat-nav scrolling-link col-6 col-lg" href="' . $loNL . '">
			<img class="square-24 d-inline-block d-lg-none" src="' . $loI . '" />
			<span class="">' . $loNT . '</span>
		</a>
		<a class="ss-cat-nav scrolling-link col-6 col-lg" href="' . $snNL . '">
			<img class="square-24 d-inline-block d-lg-none" src="' . $snI . '" />
			<span class="">' . $snNT . '</span>
		</a>
		<a class="ss-cat-nav scrolling-link col-6 col-lg" href="' . $grNL . '">
			<img class="square-24 d-inline-block d-lg-none" src="' . $grI . '" />
			<span class="">' . $grNT . '</span>
		</a>
		<a class="ss-cat-nav scrolling-link col-6 col-lg d-block d-lg-none" href="' . $toNL . '">
			<img class="square-24 d-inline-block d-lg-none" src="' . $toI . '" />
			<span class="">' . $toNT . '</span>
		</a>
		<a class="ss-cat-nav scrolling-link col-6 col-lg d-block d-lg-none" href="' . $alNL . '">
			<img class="square-24 d-inline-block d-lg-none" src="' . $alI . '" />
			<span class="">' . $alNT . '</span>
		</a>
	';
	$ssFallBackNav = '
		<a class="ss-cat-nav scrolling-link col-6 col-lg-2" href="/sleep-shop-categories/validated">
			<span class="">Validated Products</span>
		</a>
		<a class="ss-cat-nav scrolling-link col-6 col-lg-2" href="/sleep-shop-categories/curated">
			<span class="">All Products</span>
		</a>
	';
?>

<nav id="ss-cat-nav scrolling-link" class="container-fluid fixed-sub-nav">
	<div class="row">
	<?php if(is_tax('sleepshop_categories','bedding')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<img class="square-24" src="<?php the_field('bedding_icon', 'option'); ?>" />
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
				
	<?php } elseif(is_tax('sleepshop_categories','lighting')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<img class="square-24" src="<?php the_field('lighting_icon', 'option'); ?>" />
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
			
	<?php } elseif(is_tax('sleepshop_categories','wellness')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<img class="square-24" src="<?php the_field('lotion_icon', 'option'); ?>" />
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>

	<?php } elseif(is_tax('sleepshop_categories','electronics')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<img class="square-24" src="<?php the_field('electronics_icon', 'option'); ?>" />
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>

	<?php } elseif(is_tax('sleepshop_categories','grinding')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<img class="square-24" src="<?php the_field('grinding_icon', 'option'); ?>" />
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
	
	<?php } elseif(is_tax('sleepshop_categories','snoring')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<img class="square-24" src="<?php the_field('snoring_icon', 'option'); ?>" />
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
		
	<?php } elseif(is_tax('sleepshop_categories','snoring-grinding')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<img class="square-24" src="<?php the_field('snoring_icon', 'option'); ?>" />
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
		
	<?php } elseif(is_tax('sleepshop_categories','travel')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<img class="square-24" src="<?php the_field('grinding_icon', 'option'); ?>" />
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
		
	<?php } elseif(is_tax('sleepshop_categories','fall-asleep')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
		
	<?php } elseif(is_tax('sleepshop_categories','feel-more-energy')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
		
	<?php } elseif(is_tax('sleepshop_categories','snoring-partner-snoring')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
		
	<?php } elseif(is_tax('sleepshop_categories','stay-asleep')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
	
	<?php } elseif(is_tax('sleepshop_categories','validated')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
	
	<?php } elseif(is_tax('sleepshop_categories','curated')) { ?>
		<div class="col-3 d-block d-lg-none no-right-padding">
			<button id="ss-nav-trigger" class="ss-cat-nav scrolling-link-branding">
				<span class="square-24 fa fa-chevron-down"></span>
			</button><!-- ss-cat-nav scrolling-link-branding -->
		</div>
		
	<?php } else { } ?>
	
	
	
		<div id="ss-cat-nav-wrap" class="container">
			<div class="row">
				<?php if(is_tax('sleepshop_categories','validated')) { 
					echo $ssFallBackNav;
				} elseif(is_tax('sleepshop_categories','curated')) {
					echo $ssFallBackNav;
				} elseif(is_post_type_archive('sleep_shop')) {
					echo $ssFallBackNav;
				} else { 
					echo $ssCatNav;
				} ?>
				<div class="d-none d-lg-inline-block col-lg-4 ml-auto">
					<div class="search-form-wrapper">
						<?php get_search_form(); ?>
					</div><!-- search form wrapper -->
				</div>	
			</div><!-- row -->
		</div><!-- ss-cat-nav scrolling-link-wrap -->
		<?php // ===================================== this is for mobile ?>
		<div class="d-block d-lg-none col-9 d-flex align-items-center ">
			<div class="search-form-wrapper ml-auto">
				<?php get_search_form(); ?>
			</div>
		</div>
		
		
	</div><!-- row -->
</nav>