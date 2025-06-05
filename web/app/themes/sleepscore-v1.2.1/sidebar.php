<aside>
    <?php if (function_exists('dynamic_sidebar') && dynamic_sidebar('Sidebar Widgets')) : else : ?>
	<?php endif; ?>
	<?php /* popular posts
	$args = array(
		'header' => 'Custom Popular Posts',
		'header_start' => '<div class="title headline">',
		'header_end' => '</div>',
		'limit' => 5,
		'range' => 'last24hours',
    	'freshness' => 1,
		'order_by' => 'views',
		'post_type' => 'post',
		'taxonomy' => 'category',
		'title_length' => 25,
		 'thumbnail_width' => 75,
    	'thumbnail_height' => 75,
		 'wpp_start' => '<ul class="wpp-list">',
    	'wpp_end' => '</ul>',
		'post_html' => '<li>{thumb} <a href="{url}">{text_title}</a></li>'
	);

	wpp_get_mostpopular( $args );
	*/ ?>
</aside>