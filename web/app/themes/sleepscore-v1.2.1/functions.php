<?php

	// filters to allow html tags for ACF 
	add_filter( 'acf/the_field/allow_unsafe_html', function( $allowed, $selector ) {
		
		$allowed_selectors = [
			'answer',
			'boostrap_content',
			'html_code_block',
			'landing_page_headline', 
			'link_url', 
			'menu_text',
			'news_articles_link',
			'product_link_url', 
			'pre_footer_banner_custom_styling',
			'shop_by_topics_title', 
			'ss_cat_hero_headline', 
			'text_to_display',
			'title'
		];

		if ( in_array($selector, $allowed_selectors)) {
			return true;
		}

		return $allowed;
	
	}, 10, 2);

// disable Gutenberg
	if ( version_compare($GLOBALS['wp_version'], '5.0-beta', '>') ) {
    // WP > 5 beta
    add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
} else {
    // WP < 5 beta
    add_filter( 'gutenberg_can_edit_post_type', '__return_false' );
}
// Add RSS links to <head> section
	add_theme_support( 'automatic-feed-links' );

	if ( ! isset( $content_width ) ) $content_width = 900;

	// remove Yoast comments from Html
	add_filter( 'wpseo_debug_markers', '__return_false' );

// Clean up the <head>
	function removeHeadLinks() {
    	remove_action('wp_head', 'rsd_link');
    	remove_action('wp_head', 'wlwmanifest_link');
    }
    add_action('init', 'removeHeadLinks');
    remove_action('wp_head', 'wp_generator');

// remove query strings from static resources
	function _remove_query_strings_1( $src ){
	$rqs = explode( '?ver', $src );
        return $rqs[0];
	}
	if ( is_admin() ) { // Remove query strings from static resources disabled in admin
	} else {
		add_filter( 'script_loader_src', '_remove_query_strings_1', 15, 1 );
		add_filter( 'style_loader_src', '_remove_query_strings_1', 15, 1 );
	}
	function _remove_query_strings_2( $src ){
		$rqs = explode( '&ver', $src );
			return $rqs[0];
	}
	if ( is_admin() ) { // Remove query strings from static resources disabled in admin
	} else {
		add_filter( 'script_loader_src', '_remove_query_strings_2', 15, 1 );
		add_filter( 'style_loader_src', '_remove_query_strings_2', 15, 1 );
	}
// REMOVE EMOJI ICONS
	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('wp_print_styles', 'print_emoji_styles');


// tiny MCE buttons
	function my_mce_buttons_2( $buttons ) {
		$buttons[] = 'fontselect';
		$buttons[] = 'fontsizeselect';
		$buttons[] = 'styleselect';

		return $buttons;
	}
	add_filter( 'mce_buttons_2', 'my_mce_buttons_2' );

// Callback function to filter the MCE settings
	function my_mce_before_init_insert_formats( $init_array ) {
	$style_formats = array(  // Define the style_formats array
		array(
			'title' => 'Make Button',
			'block' => 'button',
			'classes' => 'cta-button',
			'wrapper' => true,
		),
		array(
			'title' => 'Responsive IFrame',
			'selector' => 'iframe',
			'classes' => 'responsive',
			'wrapper' => false,
		),
		array(
			'title' => 'Hero Headline',
			'classes' => 'hero-headline bottom-padding-15',
			'block' => 'div',
			'wrapper' => true,
		),
		array(
			'title' => 'Headline',
			'classes' => 'headline bottom-padding-15',
			'block' => 'div',
			'wrapper' => true,
		),
		array(
			'title' => 'Product Headline',
			'classes' => 'product-headline',
			'block' => 'div',
			'wrapper' => true,
		),
		array(
			'title' => '24pt Font',
			'classes' => 'font-24 bottom-padding-15',
			'block' => 'div',
			'wrapper' => true,
		),
		array(
			'title' => '20pt Font',
			'classes' => 'font-20 bottom-padding-15',
			'block' => 'div',
			'wrapper' => true,
		),
		array(
			'title' => '18pt Font',
			'classes' => 'font-18 bottom-padding-15',
			'block' => 'div',
			'wrapper' => true,
		),
		array(
			'title' => 'Grey Font',
			'classes' => 'grey-font',
			'block' => 'div',
			'wrapper' => true,
		),
		array(
			'title' => 'Light Grey Font',
			'classes' => 'light-grey-font',
			'block' => 'div',
			'wrapper' => true,
		),
		array(
			'title' => 'Responsive Image',
			'selector' => 'img',
			'classes' => 'img-fluid',
			'wrapper' => false,
		),
		array(
			'title' => 'Line Break',
			'block' => 'div',
			'classes' => 'clearfix center-block',
			'wrapper' => true,
		),
	);
	// Insert the array, JSON ENCODED, into 'style_formats'
	$init_array['style_formats'] = json_encode( $style_formats );
	return $init_array;
	}
	// Attach callback to 'tiny_mce_before_init'
	add_filter( 'tiny_mce_before_init', 'my_mce_before_init_insert_formats' );

// load scripts
	function sdwpd_load_scripts() {
		wp_deregister_script('jquery');
	    wp_register_script('jquery', get_template_directory_uri() . '/js/jquery-3.6.0.min.js' );
	    wp_enqueue_script('jquery');
		wp_enqueue_style( 'bootstrap-css', get_template_directory_uri() . '/css/bootstrap.min.css' );
		wp_enqueue_style( 'slick-slider', get_template_directory_uri() . '/css/slick.css', array());
		wp_enqueue_style( 'popins-css', get_template_directory_uri() . '/fonts/popins/stylesheet.css' );
		wp_enqueue_style( 'font-awesome-css', '//maxcdn.bootstrapcdn.com/font-awesome/4.6.3/css/font-awesome.min.css' );
// 		wp_enqueue_style( 'lightbox-css', get_template_directory_uri() . '/css/flashy.min.css');
// 		wp_enqueue_style( 'lightbox-css', get_template_directory_uri() . '/css/flashy.min.css');
		//wp_enqueue_style( 'style-css', get_stylesheet_uri());
		//wp_enqueue_style( 'style-css', get_stylesheet_uri(), '', 'v07262019' );
		wp_enqueue_style( 'style-css', get_template_directory_uri() . '/sleepscore-bs4.min.css?v=2.0.6');
		wp_enqueue_style( 'owl-css', get_template_directory_uri() . '/css/owl.carousel.css?v2');
		//wp_enqueue_style( 'owl-theme-css', get_template_directory_uri() . '/css/owl.theme.default.min.css');
		// wp_enqueue_style( 'landingpage-css', get_template_directory_uri() . '/css/landingpage.css', array());

		//wp_register_script('bootstrap-popper', ('//cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js'), array('jquery'), false, true );
		//wp_enqueue_script('bootstrap-popper');

		wp_register_script('bootstrap-js', get_template_directory_uri() . '/js/bootstrap.min.js', array('jquery'), false, true );
		wp_enqueue_script('bootstrap-js');

		wp_register_script( 'owl', get_template_directory_uri() . '/js/owl.carousel.min.js', array('jquery'), false, true);
		wp_enqueue_script( 'owl');

		wp_register_script('lightbox-js', get_template_directory_uri() . '/js/jquery.flashy.min.js', array('jquery'), false, true );
		wp_enqueue_script('lightbox-js');

		/** Enewsletter Form */
		// wp_enqueue_script( 'enewsletter-form', get_template_directory_uri() . '/js/enewsletter-form.js', array('jquery'), false, true);
		// wp_enqueue_style( 'enewsletter-form', get_template_directory_uri() . '/css/enewsletter-form.css' );


		/**********Add easyResponsiveTabs here****************/
		wp_enqueue_script( 'easyResponsiveTabs', get_template_directory_uri() . '/js/easyResponsiveTabs.js', array( 'jquery' ), '20160816', true );

		/**********Add Slick Sliser here****************/
		wp_enqueue_script( 'slick-slider', get_template_directory_uri() . '/js/slick.min.js', array( 'jquery' ));

		/**********Add Custom js here****************/
		wp_enqueue_script( 'sleepscore-landingpage', get_template_directory_uri() . '/js/landingpage.js', array( 'jquery' ), '20160816', true );

		$nose_option   = (isset($_GET['nose_option']))   ?  $_GET['nose_option'] : '0';
		$mouth_option  = (isset($_GET['mouth_option']))  ?  $_GET['mouth_option'] : '0';
		$tongue_option = (isset($_GET['tongue_option'])) ?  $_GET['tongue_option'] : '0';
		$submit = (isset($_GET['submit'])) ?  $_GET['submit'] : '0';

		wp_localize_script('sleepscore-landingpage', 'ajax_custom_data', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'siteurl' => site_url(),
			'template_dir_uri' => get_template_directory_uri(),
			'nose_option' => $nose_option,
			'mouth_option' => $mouth_option,
			'tongue_option' => $tongue_option,
			'mailchimp_sub'=>'g'.$nose_option.$mouth_option.$tongue_option,
			'submit' => $submit,
		));

		//wp_register_script('waypoints', get_template_directory_uri() . '/js/jquery.waypoints.min.js', array('jquery'), false, true );
	    //wp_enqueue_script('waypoints');
		wp_register_script('theme-scripts', get_template_directory_uri() . '/js/theme-scripts.min.js', array('jquery'), false, true );
	    wp_enqueue_script('theme-scripts');
		wp_register_script('social-sharer', get_template_directory_uri() . '/js/sharer.min.js', false, false, true );
	    wp_enqueue_script('social-sharer');
	}
	add_action( 'wp_enqueue_scripts', 'sdwpd_load_scripts' );

 // post thumbnail support for feature image
	if ( function_exists( 'add_theme_support' ) ) {
		add_theme_support( 'post-thumbnails' );
		add_image_size( 'blog-roll', 640, 480, true );
		add_image_size( 'hd-ratio', 640, 360, true );
		}

// add class to pagination links
/*	add_filter('next_post_link', 'post_link_attributes');
	add_filter('previous_post_link', 'post_link_attributes');

	function post_link_attributes($output) {
		$code = 'class="outline-button"';
		return str_replace('<a href=', '<a '.$code.' href=', $output);
	}
*/
	add_filter('next_posts_link_attributes', 'posts_link_attributes');
	add_filter('previous_posts_link_attributes', 'posts_link_attributes');
	function posts_link_attributes() {
    return 'class="outline-button"';
	}
// No limit on white paper taxonomy
	add_action('pre_get_posts', function($query){
		if (isset($query->query_vars['news_media_categories/white-papers'])) {
			add_filter('post_limits', function($limit){
				/*
				default will be something like this 'LIMIT 0, 10'
				but we don't want a limit so return empty string
				*/
				return '';
			});
		}
	});
// Popular post counter function
	function wpb_set_post_views($postID) {
		$count_key = 'wpb_post_views_count';
		$count = get_post_meta($postID, $count_key, true);
		if($count==''){
			$count = 0;
			delete_post_meta($postID, $count_key);
			add_post_meta($postID, $count_key, '0');
		}else{
			$count++;
			update_post_meta($postID, $count_key, $count);
		}
	}
	//To keep the count accurate, lets get rid of prefetching
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
// auto increment counts
	function wpb_track_post_views ($post_id) {
		if ( !is_single() ) return;
		if ( empty ( $post_id) ) {
			global $post;
			$post_id = $post->ID;
		}
		wpb_set_post_views($post_id);
	}
	add_action( 'wp_head', 'wpb_track_post_views');

// Limit search on Marketplace
	function template_chooser($template)
	{
	  global $wp_query;
	  $post_type = get_query_var('post_type');
	  if( $wp_query->is_search && $post_type == 'sleep_shop' )
	  {
		return locate_template('archive-search.php');  //  redirect to archive-search.php
	  }
	  return $template;
	}
	add_filter('template_include', 'template_chooser');

// Theme options page
	if( function_exists('acf_add_options_page') ) {
		acf_add_options_page(array(
			'page_title' 	=> 'Theme General Settings',
			'menu_title'	=> 'Theme Settings',
			'menu_slug' 	=> 'theme-general-settings',
			'capability'	=> 'edit_posts',
			'redirect'		=> false
		));
		acf_add_options_sub_page(array(
			'page_title' 	=> 'Landing Page Settings',
			'menu_title'	=> 'Landing Page Options',
			'parent_slug'	=> 'theme-general-settings',
		));
		acf_add_options_sub_page(array(
			'page_title' 	=> 'Sleep Shop Settings',
			'menu_title'	=> 'Sleep Shop Options',
			'parent_slug'	=> 'theme-general-settings',
		));
		acf_add_options_sub_page(array(
			'page_title' 	=> 'Blog Settings',
			'menu_title'	=> 'Blog Options',
			'parent_slug'	=> 'theme-general-settings',
		));
		acf_add_options_sub_page(array(
			'page_title' 	=> 'Partner Settings',
			'menu_title'	=> 'Partner Options',
			'parent_slug'	=> 'theme-general-settings',
		));
		acf_add_options_sub_page(array(
			'page_title' 	=> 'Mega Menu Settings',
			'menu_title'	=> 'Mega Menu',
			'parent_slug'	=> 'theme-general-settings',
		));
	}

// Declare sidebar widget zone
    if (function_exists('register_sidebar')) {
    	register_sidebar(array(
    		'name' => 'header widgets',
    		'id'   => 'header-widgets',
    		'description'   => 'These are widgets for the header.',
    		'before_widget' => '<div id="%1$s" class="widget %2$s">',
    		'after_widget'  => '</div>',
    		'before_title'  => '<h2>',
    		'after_title'   => '</h2>'
    	));
		register_sidebar(array(
    		'name' => 'sidebar widgets',
    		'id'   => 'sidebar-widgets',
    		'description'   => 'These are widgets for the sidebar.',
    		'before_widget' => '<div id="%1$s" class="widget %2$s">',
    		'after_widget'  => '</div>',
    		'before_title'  => '<h2>',
    		'after_title'   => '</h2>'
    	));
    }

// navigation  system
	if (function_exists('register_nav_menus')) {
		register_nav_menus(
			array(
				'primary_nav' => 'Primary Navigation',
				'secondary_nav' => 'Secondary Navigation',
				'category_nav' => 'Category Navigation',
				'footer_nav' => 'Footer Navigation'
			)
		);
	}

// limit of excerpts
	function new_excerpt_length($length) {
	return 35; //lengthen or shorten this number in red
	}
	add_filter('excerpt_length', 'new_excerpt_length');

// read more added to excerpt
	function new_excerpt_more($more) {
       global $post;
	return '<p><a class="read-more" href="'. get_permalink($post->ID) . '" aria-label="' . $post->title . '">READ MORE</a></p>';
	}
	add_filter('excerpt_more', 'new_excerpt_more');

// offset the main query on the home page
	/* function offset_main_query_index_php ( $query ) {
		 if ( $query->is_main_query() ) {
			 $query->set( 'offset', '1' );
		}
	 }

add_action( 'pre_get_posts', 'offset_main_query_index_php' );*/
// wrapper for responsive videos
/*
	add_filter('embed_oembed_html', 'responsive_video_wrapper', 99, 4);
	function responsive_video_wrapper($cache, $url, $attr, $post_id) {
	return '<div class="embed-responsive embed-responsive-16by9">' . $cache . '</div>';
	}
*/
// add img-fluid class
	function add_image_class($class){
		$class .= ' img-fluid';
		return $class;
	}
	add_filter('get_image_tag_class','add_image_class');

// Limit posts per page on category.php
/* function custom_ppp($query) {
    if (!is_admin() && $query->is_category() && $query->is_main_query() ) {
        $query->set( 'posts_per_page', '1' );
    }
}
add_action( 'pre_get_posts', 'custom_ppp' );
*/
// add custom taxonomy filters to Post type
function filter_cpt_by_taxonomies( $post_type, $which ) {
  // Affected post types
  $post_types = array(
    'news_media',
    'sleep_shop'
  );
    // Apply this only on a specific post type
    if ( !in_array( $post_type, $post_types ) ) {
    return;
  }
  // Loop cpts
  foreach ( $post_types as $type ) {
    // Exceute only on matching type
    if ( $post_type == $type ) {
      // Get associated taxonomies names
      $taxonomies = get_object_taxonomies( $type, 'object' );
      // Loop taxonomies
      foreach ( $taxonomies as $taxonomy  ) {
            // Retrieve taxonomy terms
            $terms = get_terms( $taxonomy->name );
            // Display filter HTML
            echo "<select name='{$taxonomy->name}' id='{$taxonomy->name}' class='postform'>";
            echo '<option value="">' . sprintf( esc_html__( 'Show All %s', 'text_domain' ), $taxonomy->label ) . '</option>';
            foreach ( $terms as $term ) {
                printf(
                    '<option value="%1$s" %2$s>%3$s (%4$s)</option>',
                    $term->slug,
                    ( ( isset( $_GET[$taxonomy->name] ) && ( $_GET[$taxonomy->name] == $term->slug ) ) ? ' selected="selected"' : '' ),
                    $term->name,
                    $term->count
                );
            }
            echo '</select>';
        }
    }
  }
}
add_action( 'restrict_manage_posts', 'filter_cpt_by_taxonomies' , 10, 2);

add_action( 'mc4wp_form_redirect_url', function( $redirect_url, $form ) {
    global $wp;
    $redirect_url = add_query_arg( $_SERVER['QUERY_STRING'], '',$wp->request);
    $redirect_url = add_query_arg( array( 'submit' => 1 ), $redirect_url );
    return $redirect_url;
}, 10, 2 );

/*Theme Custom Development File*/
load_template( trailingslashit( get_template_directory() ) . 'inc/theme-functions.php' );

/**
 * Short code to check if cookie banner is hidden by layout options
 */
function show_cookie_banner($atts = array(), $content) {

	require_once('inc/layout-options.php');
	$layoutOptions = new LayoutOptions();
	
	if ($layoutOptions->isVisible('cookieBanner')) {

		// render the banner content
		return do_shortcode($content);

	} else {
		
		// enqueue the css that hides the banner
		wp_register_style( 'cookie-banner', get_template_directory_uri() . '/css/cookie-banner.css', array() );
		wp_enqueue_style( 'cookie-banner' );
		return;

	}

}

add_shortcode( 'show_cookie_banner', 'show_cookie_banner');

function get_curl_data($api_url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json', 'cache-control: no-cache', 'content-type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json_data = curl_exec($ch);
        curl_close($ch);
        return json_decode($json_data, true);
}

function add_api_information(){
    define('SHOPIFY_API_KEY','a41924c346e6dddac72f5a873635b6f8');
    define('SHOPIFY_SECRET','15bae8db15192b7d41bc1452e18c72f1');
    define('SHOPIFY_TOKEN','f8588c0d534e69b9741084b6c56ffab0');
    define('SHOPIFY_STORE_URL','sleepscore-test.myshopify.com');
}
add_action( 'init', 'add_api_information' );
function my_acf_load_all_product_by_shopify( $field ) {
    //all_product_by_shopify - field_5d7f818b41e5b
    //all_product_by_shopify_category - field_5d7f81b941e5d

    //Repetaer - field_5d7f9846b7d0d
    //lending-custom - field_5d80a8c3484d6

    $API_KEY = SHOPIFY_API_KEY;
    $SECRET = SHOPIFY_SECRET;
    $TOKEN = SHOPIFY_TOKEN;
    $STORE_URL = SHOPIFY_STORE_URL;

    //$productUrl = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/products/1505648345168.json?fields=id,title,image,handle,images';
    $productUrl = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/api/2019-10/products.json?fields=id,title&limit=250';
    $products = get_curl_data($productUrl);
    $pdata = array();
    if(isset($products) && !empty($products['products'])):
        foreach ($products['products'] as $key => $product):
                $pid = $product['id'];
                $pdata[$pid] = $product['title'];
        endforeach;
    endif;
    if(isset($pdata) && !empty($pdata)):
        $field['choices'] = $pdata;
    endif;

    return $field;
}
add_filter('acf/load_field/key=field_5d7f818b41e5b', 'my_acf_load_all_product_by_shopify');
add_filter('acf/load_field/key=field_5d7f9846b7d0d', 'my_acf_load_all_product_by_shopify');
add_filter('acf/load_field/key=field_5d80a8c3484d6', 'my_acf_load_all_product_by_shopify');

function my_acf_load_all_collections_shopify( $field ) {
    //field_5d809af21bc1b - Select Categories Shopify
    $API_KEY = SHOPIFY_API_KEY;
    $SECRET = SHOPIFY_SECRET;
    $TOKEN = SHOPIFY_TOKEN;
    $STORE_URL = SHOPIFY_STORE_URL;
    $collection_listings = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/api/2019-07/collection_listings.json';
    $collections = get_curl_data($collection_listings);

    $pdata = array();
    if(isset($collections) && !empty($collections['collection_listings'])):
        foreach ($collections['collection_listings'] as $key => $collection):
                $pid = $collection['collection_id'];
                $pdata[$pid] = $collection['title'];
        endforeach;
    endif;
    if(isset($pdata) && !empty($pdata)):
        $field['choices'] = $pdata;
    endif;

    return $field;
}
add_filter('acf/load_field/key=field_5d809af21bc1b', 'my_acf_load_all_collections_shopify');


function get_products_list_by_catid($catid){
    $API_KEY = SHOPIFY_API_KEY;
    $SECRET = SHOPIFY_SECRET;
    $TOKEN = SHOPIFY_TOKEN;
    $STORE_URL = SHOPIFY_STORE_URL;

    $productUrl = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '//admin/api/2019-07/collection_listings/'.$catid.'/product_ids.json?limit=250';
    $products = get_curl_data($productUrl);
    $pdata = array();

    if(isset($products) && !empty($products['product_ids'])):
        foreach ($products['product_ids'] as $key => $product):
                $pdata[] = $product;
        endforeach;
    endif;
    return $pdata;
}

function product_shortcode($attributes, $content) {
	return '<p>' . $content . '</p>';
}

add_shortcode( 'shortcode', 'product_shortcode' );
add_shortcode( 'shortdesc', 'product_shortcode' );

function get_shopify_product_html($product_id){
    $API_KEY = SHOPIFY_API_KEY;
    $SECRET = SHOPIFY_SECRET;
    $TOKEN = SHOPIFY_TOKEN;
    $STORE_URL = SHOPIFY_STORE_URL;
    $productUrl = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/products/'.$product_id.'.json?fields=id,title,image,handle,images,body_html';
    $product_data = get_curl_data($productUrl);

    $productUrl = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/products/'.$product_id.'.json?fields=id,title,image,handle,images,body_html';
    $variants_Url = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/api/2019-10/products/'.$product_id.'/variants.json';
    $variants_data = get_curl_data($variants_Url);
    $prices = array();
    if(isset($variants_data) && !empty($variants_data['variants'])):
        foreach ($variants_data['variants'] as $variant):
            $prices[] = $variant['price'];
        endforeach;
    endif;


    $metafieldUrl = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/products/' . $product_id . '/metafields.json';
    $metafield_data = get_curl_data($metafieldUrl);
    $meta_info = array();
    if(isset($metafield_data) && !empty($metafield_data['metafields'])):
        $meta_info = wp_list_pluck($metafield_data['metafields'],'value','namespace');

        if(isset($meta_info) && array_key_exists('SSL-PDP-02', $meta_info)):
            $product_sub_title = !empty($meta_info['SSL-PDP-02']) ? $meta_info['SSL-PDP-02'] : '';
        endif;

        if(isset($meta_info) && array_key_exists('SSL-PDP-05', $meta_info)):
            $product_sleepscore_validated = !empty($meta_info['SSL-PDP-05']) ? 1 : '';
        endif;
    endif;

    ob_start();
    if(isset($product_data) && !empty($product_data['product'])):
        $product = $product_data['product'];

    ?>
    <li>
        <div class="recommended_product_slider_wrap">
                    <?php
                    if (isset($product['images']) && !empty($product['images'])):
                        ?>
                        <div class="recommended_product_slider">
                        <?php
                        foreach ($product['images'] as $image):
                            if (isset($image) && !empty($image['src'])):
                                $image_url_main = $image['src'];
                                ?>
                                    <div>
                                        <figure style="background-image: url('<?php echo $image_url_main; ?>"></figure>
                                    </div>
                    <?php
                endif;
            endforeach;
            ?>
                        </div>
                        <div class="recommended_product_slider_thumb">
                            <?php
                            foreach ($product['images'] as $image):
                                if (isset($image) && !empty($image['src'])):
                                    $image_url_thumb = $image['src'];
                                    ?>
                                    <div>
                                        <figure style="background-image: url('<?php echo $image_url_thumb; ?>"></figure>
                                    </div>
                                    <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
                $url = 'https://shop.sleepscore.com/products/' . $product['handle'];
                if (!empty($url)):
                    $url = add_query_arg(array('rfr' => 'snorequiz'), $url);
                endif;
                $cats = array();
                $target = '_blank';
                $title = $product['title'];
                $product_excerpt = wp_trim_words($product['body_html'],40,'...');
                /*$cats = check_validated_cat($product_id);
                if(in_array('58303742032', $cats)):
                    $product_sleepscore_validated = 1;
                endif;*/
                ?>
                <div class="recommended_product_slider_content">
                    <div class="recommended_product_content_inner">
                        <?php if ($product_sleepscore_validated == 1): ?>
                            <figure>
                                <img src="<?php echo get_template_directory_uri(); ?>/images/sleepscore_validated.png" alt="" />
                            </figure>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($url); ?>" target="<?php echo $target; ?>" title="<?php echo $title; ?>"><h3><?php echo $title; ?></h3></a>
                        <?php echo!empty($product_sub_title) ? '<span class="good_for_snorers">' . $product_sub_title . '</span>' : ''; ?>

                        <?php
                            if(isset($prices) && !empty($prices) && count($prices) > 1):
                                echo '<span class="price">$'.min($prices).' - $'.max($prices).'</span>';
                            else:
                                echo '<span class="price">$'.max($prices).'</span>';
                            endif;

                        ?>
                    </div>
					<div class="recommended_product_description">
						<?php echo !empty($product_excerpt) ? wpautop(do_shortcode($product_excerpt)) : ''; ?>
					</div>

                    <a href="<?php echo esc_url($url); ?>" class="button" target="<?php echo $target; ?>" title="<?php echo $title; ?>" class="button">Shop Now</a>
                </div>
            </li>
    <?php
    endif;
    return ob_get_clean();
}

function get_shopify_product_list_html($product_id){
    $API_KEY = SHOPIFY_API_KEY;
    $SECRET = SHOPIFY_SECRET;
    $TOKEN = SHOPIFY_TOKEN;
    $STORE_URL = SHOPIFY_STORE_URL;
    $productUrl = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/products/'.$product_id.'.json?fields=id,title,image,handle';
    $product_data = get_curl_data($productUrl);

    ob_start();
    if(isset($product_data) && !empty($product_data['product'])):
        $product = $product_data['product'];
        $url = 'https://shop.sleepscore.com/products/' . $product['handle'];
        if (!empty($url)):
            $url = add_query_arg(array('rfr' => 'snorequiz-lp'), $url);
        endif;
        $target = '_blank';
        $title = $product['title'];
        $product_excerpt = wp_trim_words($product['body_html'],40,'...');
        $img_url = (isset($product) && !empty($product['image']['src'])) ? $product['image']['src'] : '';
    ?>
    <li>
        <figure style="background-image: url('<?php echo $img_url; ?>"></figure>
        <h3><a href="<?php echo esc_url($url); ?>" target="<?php echo $target; ?>" title="<?php echo $title; ?>"><?php echo $title; ?></a></h3>
        <a href="<?php echo esc_url($url); ?>" target="<?php echo $target; ?>" title="<?php echo $title; ?>" class="button">Shop Now</a>
    </li>
    <?php
    endif;
    return ob_get_clean();
}

function check_validated_cat($product_id){
    $API_KEY = SHOPIFY_API_KEY;
    $SECRET = SHOPIFY_SECRET;
    $TOKEN = SHOPIFY_TOKEN;
    $STORE_URL = SHOPIFY_STORE_URL;
    $collects_Url = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/api/2019-07/collects.json?product_id='.$product_id;
    $collects_data = get_curl_data($collects_Url);
    $cats = array();
    if(isset($collects_data) && !empty($collects_data['collects'])):
       $collects = $collects_data['collects'];
       foreach ($collects as $collect):
           $cats[] = $collect['collection_id'];
       endforeach;
    endif;
    return $cats;
}

// Set CORRECT Canonical & OpenGraph Article URLs for Yoast
function blog_canonical( $canonical ) {
	global $post;
	if( is_single() ) {
		$canonical = str_replace( home_url(), 'https://www.sleepscore.com', get_permalink( $post->ID ) );
	}
	return $canonical;
}
add_filter( 'wpseo_canonical', 'blog_canonical' );
add_filter( 'wpseo_opengraph_url', 'blog_canonical' );


	class Primary_Walker_Nav_Menu extends Walker_Nav_Menu {
		// Customize the start element output
		function start_el(&$output, $item, $depth = 0, $args = NULL, $id = 0) {

			$output .= '<li class="lean-menu-item">';
			$output .= '<a class="nav-link top-level" href="';
			$output .= $item->url;
			$output .= '">';
			$output .= $item->title; 
			if ($args->walker->has_children) {
				$output .= '<i class="caret fa fa-angle-down d-none d-lg-inline"></i>';
			}  
			$output .= '</a>';
			if ($args->walker->has_children) {
				$output .= '<button type="button" class="btn-icon d-block d-lg-none"><i class="caret fa fa-angle-down"></i></button>';
			}  

		}

		// Customize the end element output
		function end_el(&$output, $item, $depth = 0, $args = NULL) {
			$output .= '</li>';
		}
	}

?>
