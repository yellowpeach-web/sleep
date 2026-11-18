<?php
defined( 'ABSPATH' ) || exit;

$tracked_pages = wpml_view_attr( $model, 'pages' );
?>
<div id="icl_show_source_top">
    <?php foreach ( is_array( $tracked_pages ) ? $tracked_pages : array() as $key => $tracked_page ) : ?>
        <?php $number = $key + 1; ?>
        <a href="#" data-iframe-url="<?php echo wpml_view_esc( wpml_view_attr( $tracked_page, 'iframe_url' ) ); ?>" data-url="<?php echo wpml_view_esc( wpml_view_attr( $tracked_page, 'url' ) ); ?>" onclick="var link = jQuery(this); jQuery('#icl_string_track_frame_wrap iframe').attr('src', link.attr('data-iframe-url')); jQuery('#icl_string_track_url a').text(link.attr('data-url')).attr('href', link.attr('data-url')); return false;"><?php echo wpml_view_esc( $number ); ?></a><br />
    <?php endforeach; ?>
</div>
<div id="icl_string_track_frame_wrap">
    <iframe onload="iclResizeIframe()" src="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $tracked_pages, 0 ), 'iframe_url' ) ); ?>" width="10" height="10" frameborder="0" marginheight="0" marginwidth="0"></iframe>
    <div id="icl_string_track_url" class="icl_string_track_url"><a href="<?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $tracked_pages, 0 ), 'url' ) ); ?>"><?php echo wpml_view_esc( wpml_view_attr( wpml_view_attr( $tracked_pages, 0 ), 'url' ) ); ?></a></div>
</div><?php  ?>
