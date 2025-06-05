<div class="container<?php if(get_field('default_product_fluid_container', 'option')): echo '-fluid'; endif; ?>" <?php if(get_field('default_product_bg_color', 'option')): ?>style="background-color:<?php the_field('default_product_bg_color', 'option'); ?>" <?php endif; ?>>
	<div class="row top-bottom-padding">
		<?php if(get_field('default_product_nested_container', 'option')): ?>
			<div class="container">
		<?php endif; ?>

<?php if(get_field('title_for_products', 'option')): ?>
	<div class="row">
		<div class="col-12 headline"><?php the_field('title_for_products', 'option'); ?></div>
	</div>
<?php endif; ?>
<div class="row">
	<?php if(have_rows('shopify_product_repeater', 'option')): ?>

	<?php while(have_rows('shopify_product_repeater', 'option')): the_row(); ?>
		<?php $PRODUCT_ID =	get_sub_field('shopify_product_post_id', 'option'); ?>

		<?php
		$API_KEY = 'a41924c346e6dddac72f5a873635b6f8';
		$SECRET = '15bae8db15192b7d41bc1452e18c72f1';
		$TOKEN = 'f8588c0d534e69b9741084b6c56ffab0';
		$STORE_URL = 'sleepscore-test.myshopify.com';

		// Call one for title and image
		$productUrl = 'https://' . $API_KEY . ':' . $SECRET . '@' . $STORE_URL . '/admin/products/' . $PRODUCT_ID . '.json?fields=title,image,handle';
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
		$prodLink = 'https://shop.sleepscore.com/products/'.$productJson['product']['handle'];
		?>
		<a class="product-link <?php the_sub_field('shopify_product_classes', 'option'); ?>" href="<?php echo $prodLink; ?>">
			<?php /*?><div class="product-description bottom-padding-15"><?php echo $myDesc ?></div><?php */?>
			<div class="product-image" style="display:block; margin:0 auto; max-width: 400px;">
				<img class="img-fluid" src=<?php echo $myImg; ?> />
			</div>
			<div class="product-title product-headline"><?php echo $myTitle ?></div>
		</a>
	<?php endwhile; ?>
	<?php endif; ?>
</div><!-- row -->

	<?php if(get_field('default_product_nested_container', 'option')): ?>
		</div><!-- nested container -->
	<?php endif; ?>
	</div><!-- row -->
</div><!-- container -->
