<?php 
global $post;
$sectionclass   = array();
$imge_url       = $imge_title = $font_style_title = $font_class = $background_type = '';
$bg             = 'background: none';
$sectionclass[] = 'do_snore_sec';

$heading_tag    = get_sub_field( 'heading_tag' );
$heading    = get_sub_field( 'heading' );
$subheading = get_sub_field( 'subheading' );
$content    = get_sub_field( 'content' );
$linkdata = get_link_data(get_sub_field('button_link'));
$button_title = (isset($linkdata) && !empty($linkdata['title'])) ? $linkdata['title'] : '';
$text_align = get_sub_field( 'text_align' );
$main_image = get_sub_field('main_image');
$phone_image = get_sub_field('phone_image');



if ( ! empty( $text_align ) ):
	$sectionclass[] = 'text-' . $text_align;
endif;

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
        <div class="do_snore_content">
            <?php if(isset($main_image) && !empty($main_image['sizes'])): ?>
            <figure>
                <img src="<?php echo $main_image['sizes']['large']; ?>" alt="<?php echo seoalttag($main_image['title']); ?>" />
            </figure>
            <?php endif; ?>
            <?php echo ! empty( $content ) ? wpautop( do_shortcode($content)) : ''; ?>
            <?php if(have_rows('application_links', get_the_ID())): ?>
            <ul class="download_apps">
                <?php 
                    while (have_rows('application_links', get_the_ID())): the_row();
                    $logo = get_sub_field('logo');
                    $title = get_sub_field('title');
                    $link = get_sub_field('link');
                    if(isset($logo) && !empty($logo['sizes'])):
                    ?>
                <li>
                    <a href="<?php echo esc_url($link); ?>" target="_blank" title="<?php echo seotitletag($title); ?>">
                        <img src="<?php echo $logo['sizes']['large']; ?>" alt="<?php echo seoalttag($title); ?>" />
                    </a>
                </li>
                <?php endif; ?>
                <?php endwhile; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php if(isset($phone_image) && !empty($phone_image['sizes'])): ?>
        <figure class="mobile_img">
                <img src="<?php echo $phone_image['sizes']['large']; ?>" alt="<?php echo seoalttag($phone_image['title']); ?>" />
            </figure>
            <?php endif; ?>
    </div>
</section>