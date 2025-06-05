<?php
global $post;
$sectionclass = array();
$imge_url = $imge_title = $font_style_title = $font_class = $background_type = '';
$bg = 'background: none';
$sectionclass[] = 'snoring_quiz_sec';

$heading_tag = get_sub_field('heading_tag');
$heading = get_sub_field('heading');
$subheading = get_sub_field('subheading');
$content = get_sub_field('content');
$linkdata = get_link_data(get_sub_field('button_link'));
$button_title = (isset($linkdata) && !empty($linkdata['title'])) ? $linkdata['title'] : '';

$text_align = get_sub_field('text_align');
if (!empty($text_align)):
    $sectionclass[] = 'text-' . $text_align;
endif;

while (have_rows('background_options')) : the_row();
    $background_type = get_sub_field('background_type');
    if ($background_type == 'none'):
        $bg = 'background:none';

    elseif ($background_type == 'color'):
        $color_picker = current(get_sub_field('color'));
        if (!empty($color_picker)):
            $bg = "background: $color_picker;";
        endif;


    elseif ($background_type == 'image'):
        $background_image = get_sub_field('background_image');
        if (!empty($background_image) && isset($background_image['sizes'])):
            $background_image_url = $background_image['url'];
            $bg = 'background-image: url(' . $background_image_url . ')';
        endif;
    endif;
endwhile;

while (have_rows('other_options')) : the_row();
    $custom_css_class = get_sub_field('custom_css_class');
    if (!empty($custom_css_class)):
        $sectionclass[] = $custom_css_class;
    endif;
endwhile;

if (isset($sectionclass) && !empty($sectionclass)):
    $sec_class = implode(" ", $sectionclass);
endif;
?>

<section <?php echo!empty($custom_id) ? ' id="' . $custom_id . '" ' : ''; ?> class="<?php echo $sec_class; ?>" data-parallax style="<?php echo $bg; ?>">
    <div class="main">
        <div class="snoring_quiz_content">
            <?php
            global $post;
            if (is_singular('partners')):
                if (has_post_thumbnail()):
                    $imag_url = get_the_post_thumbnail_url($post, 'full');
                    ?>
                    <figure>
                        <img src="<?php echo $imag_url; ?>" alt="<?php echo seoalttag(get_the_title($post->ID)); ?>" />
                    </figure>
                    <?php
                endif;
            endif;
            ?>
            <?php echo (!empty($heading) ) ? '<' . $heading_tag . $font_style_title . '>' . $heading . '</' . $heading_tag . '>' : ''; ?>
            <?php echo (!empty($subheading) ) ? '<h3>' . $subheading . '</h3>' : ''; ?>
            <?php echo!empty($content) ? wpautop(do_shortcode($content)) : ''; ?>

            <?php if (isset($button_title) && !empty($button_title)): ?>
                <a href="<?php echo $linkdata['url']; ?>" target="<?php echo $linkdata['target']; ?>" title="<?php echo seotitletag($button_title); ?>" class="button"><?php echo $button_title; ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>
