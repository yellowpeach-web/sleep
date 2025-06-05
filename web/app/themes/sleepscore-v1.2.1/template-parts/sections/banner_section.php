<?php 
global $post;
$sectionclass   = array();
$imge_url       = $imge_title = $font_style_title = $font_class = $background_type = '';
$bg             = 'background: none';
$sectionclass[] = 'banner_sec';

$heading_tag    = get_sub_field( 'heading_tag' );
$heading    = get_sub_field( 'heading' );
$subheading = get_sub_field( 'subheading' );
$content    = get_sub_field( 'content' );

while ( have_rows( 'background_options' ) ) : the_row();
	$background_type = get_sub_field( 'background_type' );
	if ( $background_type == 'none' ):
		$bg = 'background:none';
	elseif ( $background_type == 'color' ):
		$color_picker       = current( get_sub_field( 'color' ) );
		if ( ! empty( $color_picker ) ):
                        $bg = "background: $color_picker;";
                endif;
	elseif ( $background_type == 'image' ):
		$background_image = get_sub_field( 'background_image' );
		if ( ! empty( $background_image ) && isset( $background_image['sizes'] ) ):
                    $background_image_url = $background_image['url'];
                    $bg                   = 'background-image: url(' . $background_image_url . ')';
		endif;
	endif;
endwhile;

while ( have_rows( 'other_options' ) ) : the_row();
	$custom_css_class = get_sub_field( 'custom_css_class' );
	if ( ! empty( $custom_css_class ) ):
		$sectionclass[] = $custom_css_class;
	endif;
endwhile;

if ( isset( $sectionclass ) && ! empty( $sectionclass ) ):
	$sec_class = implode( " ", $sectionclass );
endif;
?>
<section <?php echo ! empty( $custom_id ) ? ' id="' . $custom_id . '" ' : ''; ?> class="<?php echo $sec_class; ?>" style="<?php echo $bg; ?>">
    <div class="main">
        <div class="banner_content">
            <?php echo ( ! empty( $heading ) ) ? '<'.$heading_tag . $font_style_title . '>' . $heading . '</'.$heading_tag.'>' : ''; ?>
            <?php echo ( ! empty( $subheading ) ) ? '<h3>' . $subheading . '</h3>' : ''; ?>
            <?php echo ! empty( $content ) ? wpautop( do_shortcode($content)) : ''; ?>
        </div>
    </div>
</section>